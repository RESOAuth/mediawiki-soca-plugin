<?php
declare(strict_types=1);
namespace MediaWiki\Extension\SOCA;

use MediaWiki\MediaWikiServices;
use RESOAuth\Soca\{Failure, Json};

/** Minimal shared replay and issuer/subject stores; secrets stay in native sessions. */
final class Store {
    public function db() { return MediaWikiServices::getInstance()->getConnectionProvider()->getPrimaryDatabase(); }
    public function settings(string $key = 'config'): array {
        $row = $this->db()->selectRow('soca_setting', ['value', 'revision'], ['setting_key' => $key], __METHOD__);
        return $row ? ['value' => Json::decode($row->value), 'revision' => (int)$row->revision] : ['value' => [], 'revision' => 0];
    }
    public function save(string $key, array $value, int $expected): void {
        $db = $this->db();
        $db->doAtomicSection(__METHOD__, function () use ($db, $key, $value, $expected): void {
            if ($expected === 0) {
                $db->insert('soca_setting', ['setting_key' => $key, 'value' => Json::canonical($value ?: ['_empty' => true]), 'revision' => 1], __METHOD__, ['IGNORE']);
            } else {
                $db->update('soca_setting', ['value' => Json::canonical($value ?: ['_empty' => true]), 'revision' => $expected + 1], ['setting_key' => $key, 'revision' => $expected], __METHOD__);
            }
            Failure::require($db->affectedRows() === 1, 'stale_configuration_edit');
        }, $db::ATOMIC_CANCELABLE);
    }
    public function ledger(string $state, string $browser, int $ttl = 300): void {
        $db = $this->db();
        $db->delete('soca_replay', ['expires < ' . $db->addQuotes(time())], __METHOD__);
        $db->insert('soca_replay', ['state_hash' => hash('sha256', $state), 'browser_hash' => hash('sha256', $browser), 'expires' => time() + $ttl], __METHOD__);
    }
    public function consume(string $state, string $browser): void {
        $db = $this->db();
        $db->delete('soca_replay', ['state_hash' => hash('sha256', $state), 'browser_hash' => hash('sha256', $browser), 'expires >= ' . $db->addQuotes(time())], __METHOD__);
        Failure::require($db->affectedRows() === 1, 'transaction_replayed_or_wrong_browser');
    }
    public function invalidate(): void { $this->db()->delete('soca_replay', '*', __METHOD__); }
    public static function identityKey(string $issuer, string $subject): string { return hash('sha256', $issuer . "\0" . $subject); }
    public function linked(string $issuer, string $subject): ?int {
        $row = $this->db()->selectRow('soca_identity', ['issuer', 'subject', 'user_id'], ['identity_key' => self::identityKey($issuer, $subject)], __METHOD__);
        if (!$row) { return null; }
        Failure::require($row->issuer === $issuer && $row->subject === $subject, 'identity_conflict');
        return (int)$row->user_id;
    }
    public function link(string $issuer, string $subject, int $user): void {
        Failure::require($user > 0 && strlen($issuer) <= 2048 && (bool)preg_match('/^[\x20-\x7e]{1,255}$/D', $subject), 'identity_link_input');
        $db = $this->db();
        $db->insert('soca_identity', ['identity_key' => self::identityKey($issuer, $subject), 'issuer' => $issuer, 'subject' => $subject, 'user_id' => $user, 'created' => time()], __METHOD__, ['IGNORE']);
        Failure::require($db->affectedRows() === 1, 'identity_already_linked');
    }
    public function evidence(string $kind, string $revision): void {
        $key = 'evidence:' . $kind; $old = $this->settings($key);
        $this->save($key, ['revision' => $revision, 'at' => time()], $old['revision']);
    }
    public function ready(string $revision): bool {
        foreach (['documents', 'login'] as $kind) {
            $evidence = $this->settings('evidence:' . $kind)['value'];
            if (($evidence['revision'] ?? '') !== $revision || ($evidence['at'] ?? 0) <= 0) { return false; }
        }
        return true;
    }
}
