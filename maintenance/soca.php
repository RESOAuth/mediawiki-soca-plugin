<?php
declare(strict_types=1);

$IP = getenv('MW_INSTALL_PATH') ?: dirname(__DIR__, 3);
require_once $IP . '/maintenance/Maintenance.php';

use MediaWiki\Extension\SOCA\{Configuration, Service};
use MediaWiki\MediaWikiServices;
use RESOAuth\Soca\{Failure, Http, Json, Policy};

final class SocaMaintenance extends Maintenance {
    public function __construct() {
        parent::__construct();
        $this->addDescription('SOCA headless configuration, checks, identity links and recovery.');
        $this->requireExtension('SOCA');
        foreach (['status', 'check', 'enable', 'disable', 'cleanup', 'approve-link', 'unlink'] as $name) { $this->addOption($name, $name); }
        foreach (['config', 'link-user', 'subject', 'preview-email'] as $name) { $this->addOption($name, $name, false, true); }
    }
    public function execute() {
        try {
            $service = new Service(); $configuration = $service->configuration;
            if (Configuration::emergency()) { $service->store->invalidate(); }
            if ($this->hasOption('disable')) {
                Failure::require(!array_key_exists('enabled', $configuration->deployment()), 'deployment_enabled_is_locked_use_emergency_environment');
                $row = $service->store->settings(); $candidate = $row['value']; $candidate['enabled'] = false;
                $service->store->save('config', $candidate, $row['revision']); $service->store->invalidate(); $configuration->audit('administrator_disabled_sso');
                $this->output("SOCA disabled; pending transactions invalidated. Local accounts and sessions preserved.\n"); return;
            }
            if ($this->hasOption('config')) {
                $file = $this->getOption('config'); Failure::require(is_readable($file) && filesize($file) <= 65536, 'config_input');
                $candidate = Json::decode(file_get_contents($file));
                Failure::require(!isset($candidate['private_key_pem']), 'use_deployment_secret_reference');
                $configuration->save($candidate, $service->store->settings()['revision']);
            }
            $client = $configuration->client(); $config = $configuration->effective();
            if ($this->hasOption('check')) { $client->checkDocuments(); $service->store->evidence('documents', $client->revision()); $this->output("Documents passed; real candidate login remains a separate check.\n"); }
            if ($this->hasOption('enable') || $this->hasOption('disable')) {
                Failure::require(!array_key_exists('enabled', $configuration->deployment()), 'deployment_enabled_is_locked');
                $row = $service->store->settings(); $candidate = $row['value']; $candidate['enabled'] = $this->hasOption('enable'); $configuration->save($candidate, $row['revision']);
            }
            if ($this->hasOption('link-user') || $this->hasOption('unlink')) {
                $subject = $this->getOption('subject'); Failure::require(is_string($subject), 'subject_required');
                if ($this->hasOption('unlink')) {
                    $db = $service->store->db(); $db->delete('soca_identity', ['identity_key' => $service->store::identityKey($config['issuer'], $subject)], __METHOD__);
                    $service->store->invalidate(); $configuration->audit('administrator_identity_unlinked');
                } else {
                    Failure::require($this->hasOption('approve-link'), 'explicit_administrator_link_approval_required');
                    $user = MediaWikiServices::getInstance()->getUserFactory()->newFromName($this->getOption('link-user'));
                    Failure::require($user !== null, 'local_user_required'); $service->eligible($user, $config);
                    $service->store->link($config['issuer'], $subject, $user->getId()); $configuration->audit('administrator_identity_linked');
                }
            }
            if ($this->hasOption('preview-email')) { Policy::assertAllowed(['email' => $this->getOption('preview-email'), 'email_verified' => true], $config); $this->output("Policy permits this verified-email example; this is not authentication or ownership evidence.\n"); }
            if ($this->hasOption('cleanup')) { $db = $service->store->db(); $db->delete('soca_replay', ['expires < ' . $db->addQuotes(time())], __METHOD__); }
            $sources = [];
            foreach ($config as $key => $value) { $sources[$key] = array_key_exists($key, $configuration->deployment()) ? 'deployment' : (array_key_exists($key, $service->store->settings()['value']) ? 'database' : 'platform/default'); }
            unset($config['private_key_pem']);
            $this->output(Json::canonical(['version' => '0.1.0', 'emergency_disabled' => Configuration::emergency(), 'revision' => $client->revision(),
                'ready' => $service->store->ready($client->revision()), 'effective' => $config, 'sources' => $sources, 'client_id' => $client->clientId()]) . "\n");
        } catch (Failure $e) { $this->fatalError('SOCA: ' . $e->reason); }
    }
}
$maintClass = SocaMaintenance::class;
require_once RUN_MAINTENANCE_IF_MAIN;
