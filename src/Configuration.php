<?php
declare(strict_types=1);
namespace MediaWiki\Extension\SOCA;

use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
use RESOAuth\Soca\{Client, Failure, Http, Json};

final class Configuration {
    public function __construct(private Store $store) {}
    public function deployment(): array {
        $config = MediaWikiServices::getInstance()->getMainConfig()->get('SOCAConfig');
        Failure::require(is_array($config), 'deployment_config_array');
        if ($file = getenv('SOCA_CONFIG_FILE')) {
            Failure::require(str_starts_with($file, '/') && is_readable($file) && filesize($file) <= 65536, 'deployment_config_file');
            $config = array_replace($config, Json::decode(file_get_contents($file)));
        }
        Failure::require(!array_key_exists('private_key_pem', $config), 'use_deployment_secret_reference');
        return $config;
    }
    public function effective(?array $candidate = null): array {
        $main = MediaWikiServices::getInstance()->getMainConfig();
        $server = rtrim($main->get('CanonicalServer') ?: $main->get('Server'), '/');
        $base = $server . $main->get('Script');
        $defaults = ['enabled' => false, 'provision' => false, 'issuer' => 'https://auth.resoauth.cloud',
            'metadata_mode' => 'hosted', 'proxy_template' => 'https://dynamic-cimd.resoauth.cloud/cimd/%s',
            'allowed_domains' => [], 'allowed_emails' => [], 'denied_domains' => [], 'denied_emails' => [],
            'client_name' => $main->get('Sitename'), 'client_uri' => $server,
            'metadata_uri' => $base . '?title=Special%3ASOCA%2Fmetadata',
            'jwks_uri' => $base . '?title=Special%3ASOCA%2Fjwks',
            'redirect_uri' => $base . '?title=Special%3ASOCA%2Fcallback', 'privileged_user_ids' => []];
        $logo = $main->get('Logos')['1x'] ?? $main->get('Logo');
        if (is_string($logo) && $logo !== '') {
            $url = str_starts_with($logo, '/') && !str_starts_with($logo, '//') ? $server . $logo : $logo;
            if (str_starts_with($url, 'https://')) { $defaults['logo_uri'] = $url; }
        }
        $privacy = Title::newFromText(wfMessage('privacypage')->inContentLanguage()->text());
        if ($privacy && $privacy->exists()) { $defaults['policy_uri'] = $privacy->getCanonicalURL(); }
        $stored = $candidate ?? $this->store->settings()['value'];
        unset($stored['_empty']);
        $effective = array_replace($defaults, $stored, $this->deployment());
        Failure::require(is_bool($effective['enabled']) && is_bool($effective['provision']) && is_array($effective['privileged_user_ids']), 'configuration_type');
        Failure::require(count(array_filter($effective['privileged_user_ids'], 'is_int')) === count($effective['privileged_user_ids']), 'privileged_user_ids');
        return $effective;
    }
    public function client(?array $candidate = null): Client {
        $config = $this->effective($candidate); unset($config['enabled']);
        $file = getenv('SOCA_PRIVATE_KEY_FILE') ?: ($config['private_key_file'] ?? '');
        Failure::require(is_string($file) && str_starts_with($file, '/') && is_file($file) && is_readable($file) && filesize($file) <= 32768, 'private_key_file_required');
        Failure::require((fileperms($file) & 0007) === 0, 'private_key_file_permissions');
        $config['private_key_pem'] = file_get_contents($file);
        return new Client($config);
    }
    public function save(array $candidate, int $expected): void {
        $effective = $this->effective($candidate); $client = $this->client($candidate);
        Failure::require(!$effective['enabled'] || $this->store->ready($client->revision()), 'successful_documents_and_test_login_required');
        $this->store->save('config', $candidate, $expected);
        if (!$effective['enabled']) { $this->store->invalidate(); }
        $this->audit('configuration_changed');
    }
    public static function emergency(): bool { return in_array(strtolower((string)getenv('SOCA_EMERGENCY_DISABLE')), ['1', 'true', 'yes'], true); }
    public function audit(string $event): void {
        // Event type only; never callback data, usernames, subject or provider claims.
        \MediaWiki\Logger\LoggerFactory::getInstance('SOCA')->info($event);
    }
}
