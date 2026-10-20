<?php
declare(strict_types=1);
namespace MediaWiki\Extension\SOCA;

final class Hooks {
    public static function registration(): void {
        require_once dirname(__DIR__) . '/vendor/autoload.php';
        // SOCA must never remove the site's recovery authentication provider.
        if (empty($GLOBALS['wgPluggableAuth_EnableLocalLogin'])) {
            throw new \RuntimeException('SOCA requires $wgPluggableAuth_EnableLocalLogin = true before loading PluggableAuth.');
        }
        if (!empty($GLOBALS['wgPluggableAuth_EnableAutoLogin'])) {
            throw new \RuntimeException('SOCA requires PluggableAuth automatic login to remain disabled for local recovery.');
        }
    }
    public static function schema($updater): void {
        $type = $updater->getDB()->getType();
        $updater->addExtensionTable('soca_identity', dirname(__DIR__) . '/sql/' . $type . '.sql');
    }
    public static function readWhitelist($title, $user, &$whitelisted): void {
        // Do not expose the administration page, or any other special page.
        if ($title->isSpecial('SOCA') && in_array($title->getSubpageText(), ['metadata', 'jwks', 'callback'], true)) { $whitelisted = true; }
    }
}
