#!/bin/sh
set -eu
if [ -z "${SOCA_DEMO_PASSWORD:-}" ]; then
  echo 'Set SOCA_DEMO_PASSWORD to a strong local administrator password.' >&2
  exit 1
fi
mkdir -p /var/www/data /run/secrets
if [ ! -f "$SOCA_PRIVATE_KEY_FILE" ]; then
  php -r '$key = openssl_pkey_new(["private_key_type" => OPENSSL_KEYTYPE_EC, "curve_name" => "prime256v1"]); openssl_pkey_export($key, $pem); file_put_contents(getenv("SOCA_PRIVATE_KEY_FILE"), $pem);'
  chown www-data:www-data "$SOCA_PRIVATE_KEY_FILE"
  chmod 0600 "$SOCA_PRIVATE_KEY_FILE"
fi
if [ ! -f LocalSettings.php ]; then
  php maintenance/run.php install --dbtype sqlite --dbpath /var/www/data --dbname socademo \
    --server "$SOCA_DEMO_ORIGIN" --scriptpath '' --pass "$SOCA_DEMO_PASSWORD" 'SOCA demo' Admin
  cat >> LocalSettings.php <<'PHP'

$wgCanonicalServer = getenv('SOCA_DEMO_ORIGIN') ?: 'https://wiki.localhost:8443';
$wgPluggableAuth_EnableLocalLogin = true;
$wgPluggableAuth_EnableAutoLogin = false;
wfLoadExtension('PluggableAuth');
wfLoadExtension('SOCA');
$wgSOCAConfig = ['metadata_mode' => getenv('SOCA_METADATA_MODE') ?: 'proxy'];
$wgPluggableAuth_Config = ['SOCA' => ['plugin' => 'SOCA', 'buttonLabelMessage' => 'soca']];
$wgDebugLogGroups['SOCA'] = '/var/www/data/soca-audit.log';
PHP
fi
php maintenance/run.php update --quick
chown -R www-data:www-data /var/www/data
touch /var/www/data/soca-ready
exec docker-php-entrypoint "$@"
