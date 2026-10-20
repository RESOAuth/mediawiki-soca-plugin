<?php
declare(strict_types=1);
require dirname(__DIR__) . '/vendor/autoload.php';
$manifest = json_decode(file_get_contents(dirname(__DIR__) . '/extension.json'), true, 32, JSON_THROW_ON_ERROR);
if ($manifest['attributes']['PluggableAuth']['SOCA']['class'] !== 'MediaWiki\\Extension\\SOCA\\SocaPlugin') { throw new RuntimeException('Invalid native plugin registration'); }
foreach (['mysql', 'sqlite', 'postgres'] as $db) {
    $sql = file_get_contents(dirname(__DIR__) . '/sql/' . $db . '.sql');
    if (!str_contains($sql, 'soca_identity') || !str_contains($sql, 'soca_replay') || !str_contains($sql, 'soca_setting')) { throw new RuntimeException('Missing schema'); }
}
if (MediaWiki\Extension\SOCA\Store::identityKey('https://a.example.com', 'bc') === MediaWiki\Extension\SOCA\Store::identityKey('https://a.example.comb', 'c')) { throw new RuntimeException('Identity boundaries'); }
echo "Host packaging checks passed. Runtime behaviour is tested separately in the real MediaWiki container.\n";
