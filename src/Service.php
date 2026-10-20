<?php
declare(strict_types=1);
namespace MediaWiki\Extension\SOCA;

use MediaWiki\MediaWikiServices;
use RESOAuth\Soca\{Failure, Json, Policy};

final class Service {
    public Store $store;
    public Configuration $configuration;
    public function __construct() { $this->store = new Store(); $this->configuration = new Configuration($this->store); }
    private function browser($session): string {
        $browser = $session->getSecret('SOCA.browser');
        if (!is_string($browser)) { $browser = Json::b64(random_bytes(32)); $session->setSecret('SOCA.browser', $browser); }
        return $browser;
    }
    public function begin($session, bool $test, int $operator = 0): string {
        if (Configuration::emergency()) { $this->store->invalidate(); }
        Failure::require(!Configuration::emergency(), 'emergency_disabled');
        $index = $session->get('SOCA.transactions', []); $now = time();
        foreach ($index as $key => $started) {
            if (!is_int($started) || $started < $now - 300) { $session->remove('SOCA.tx.' . $key); unset($index[$key]); }
        }
        $session->set('SOCA.transactions', $index);
        Failure::require(count($index) < 8, 'too_many_pending_logins');
        $client = $this->configuration->client(); $config = $this->configuration->effective();
        Failure::require($test ? $operator > 0 : ($config['enabled'] && $this->store->ready($client->revision())), 'profile_not_active');
        $login = $client->start(); $tx = $login['transaction'] + ['test' => $test, 'operator' => $operator];
        $key = hash('sha256', $login['state']); $index[$key] = $now;
        $session->setSecret('SOCA.tx.' . $key, $tx); $session->set('SOCA.transactions', $index);
        $session->persist(); $this->store->ledger($login['state'], $this->browser($session));
        return $login['url'];
    }
    /** Returns test=true or creates an encrypted one-use handoff to PluggableAuth. */
    public function callback($session, array $query, int $operator): bool {
        if (Configuration::emergency()) { $this->store->invalidate(); }
        Failure::require(!Configuration::emergency(), 'emergency_disabled');
        Failure::require(is_string($query['state'] ?? null) && (bool)preg_match('/^[A-Za-z0-9_-]{43}$/D', $query['state']), 'callback_state');
        $key = hash('sha256', $query['state']); $tx = $session->getSecret('SOCA.tx.' . $key);
        Failure::require(is_array($tx), 'transaction_not_in_browser');
        $session->remove('SOCA.tx.' . $key); $index = $session->get('SOCA.transactions', []); unset($index[$key]); $session->set('SOCA.transactions', $index);
        $this->store->consume($query['state'], $this->browser($session));
        $client = $this->configuration->client(); $config = $this->configuration->effective();
        Failure::require($tx['test'] ? $operator > 0 && $operator === $tx['operator'] : $config['enabled'] && $this->store->ready($client->revision()), 'profile_disabled_or_operator_changed');
        $claims = $client->complete($tx, $query); Policy::assertAllowed($claims, $config);
        if ($tx['test']) {
            $this->store->evidence('login', $client->revision()); $this->configuration->audit('candidate_test_succeeded'); return true;
        }
        $user = $this->resolve($claims, $config);
        $session->resetId(); $session->persist();
        $ticket = Json::b64(random_bytes(32));
        $session->setSecret('SOCA.completed', ['user_id' => $user->getId(), 'ticket' => $ticket, 'revision' => $client->revision()]);
        $this->store->ledger($ticket, $this->browser($session), 60);
        return false;
    }
    public function takeLogin($session): ?int {
        if (Configuration::emergency()) { $this->store->invalidate(); throw new Failure('emergency_disabled'); }
        $completed = $session->getSecret('SOCA.completed');
        if (!is_array($completed)) { return null; }
        $session->remove('SOCA.completed');
        Failure::require(!Configuration::emergency() && $this->configuration->effective()['enabled'], 'profile_disabled');
        Failure::require($completed['revision'] === $this->configuration->client()->revision(), 'configuration_changed');
        $this->store->consume($completed['ticket'], $this->browser($session));
        $user = MediaWikiServices::getInstance()->getUserFactory()->newFromId((int)$completed['user_id']);
        $this->eligible($user, $this->configuration->effective());
        return $user->getId();
    }
    public function eligible($user, array $config): void {
        Failure::require($user->isRegistered() && !$user->isLocked() && $user->getBlock() === null, 'local_account_ineligible');
        $groups = MediaWikiServices::getInstance()->getUserGroupManager()->getUserGroups($user);
        Failure::require(!array_intersect($groups, ['sysop', 'bureaucrat', 'interface-admin']) || in_array($user->getId(), $config['privileged_user_ids'], true), 'sensitive_account_requires_local_approval');
    }
    public function resolve(array $claims, array $config) {
        $services = MediaWikiServices::getInstance(); $issuer = $config['issuer']; $subject = $claims['sub'];
        $id = $this->store->linked($issuer, $subject);
        if ($id !== null) { $user = $services->getUserFactory()->newFromId($id); $this->eligible($user, $config); return $user; }
        Failure::require($config['provision'], 'identity_link_required');
        // Subject-derived immutable names avoid email/name collisions and account matching.
        $name = 'SOCA ' . substr(Store::identityKey($issuer, $subject), 0, 32);
        $db = $this->store->db();
        return $db->doAtomicSection(__METHOD__, function () use ($name, $services, $claims, $issuer, $subject, $config) {
            $id = $this->store->linked($issuer, $subject);
            if ($id !== null) { $user = $services->getUserFactory()->newFromId($id); $this->eligible($user, $config); return $user; }
            $user = $services->getUserFactory()->newFromName($name);
            Failure::require($user !== null && !$user->isRegistered(), 'local_username_collision');
            $user->setEmail($claims['email']);
            if (isset($claims['name'])) { Failure::require(is_string($claims['name']) && mb_strlen($claims['name']) <= 255 && !preg_match('/[\x00-\x1f\x7f<>]/u', $claims['name']), 'invalid_profile_name'); $user->setRealName($claims['name']); }
            $status = $user->addToDatabase(); Failure::require($status->isGood(), 'account_creation_failed');
            $this->store->link($issuer, $subject, $user->getId());
            $this->configuration->audit('account_and_identity_created');
            return $user;
        }, $db::ATOMIC_CANCELABLE);
    }
}
