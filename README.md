# SOCA for MediaWiki

An installable [PluggableAuth](https://www.mediawiki.org/wiki/Extension:PluggableAuth)
provider for OpenID Connect services supporting OAuth Client ID Metadata Documents.
It publishes public metadata and keys, signs ES256 client assertions, uses PKCE S256,
validates identity, and hands an eligible local account to MediaWiki's native login.

**Version 0.1.0 is an alpha for review.** The supported target is MediaWiki 1.43 LTS,
PHP 8.2 or newer, and PluggableAuth 7.0 or newer. Native testing uses the official
1.43 container and PluggableAuth 7.5.0. See the [requirements assessment](docs/REQUIREMENTS.md)
for the full target and remaining gaps, including key rotation, further algorithms,
logout protocols and independent security review. No live end-user login or OIDC
certification is claimed.

## Install

1. Put this repository at `extensions/SOCA` and run `composer install --no-dev` there,
   or extract the package produced by `python3 tools/build.py` into `extensions/`.
2. Install PluggableAuth and retain local administrator authentication:

   ```php
   $wgPluggableAuth_EnableLocalLogin = true;
   $wgPluggableAuth_EnableAutoLogin = false;
   wfLoadExtension('PluggableAuth');
   wfLoadExtension('SOCA');
   $wgPluggableAuth_Config = ['SOCA' => ['plugin' => 'SOCA', 'buttonLabelMessage' => 'soca']];
   ```

3. Supply a persistent P-256 private key through `SOCA_PRIVATE_KEY_FILE`, a readable
   absolute path outside the web root, without world permissions. Keep it in managed
   secret storage and back it up securely. Run `php maintenance/run.php update`.
4. Open `Special:SOCA`, configure admission, check documents, and perform an
   administrator-bound candidate test. SSO and account creation start disabled.
   Enable SSO only after both checks pass for the current configuration.

Returning users need an administrator-approved `(issuer, subject)` association.
Email never selects an existing local account. Optional provisioning creates a
standard account with an invalid local password and no additional group grants.

The [operator guide](docs/OPERATIONS.md) covers complete headless setup, settings
precedence, linking, recovery, TLS, upgrades and deletion. The
[security policy](SECURITY.md) explains reporting and release limitations.

## Demo and tests

Set `SOCA_DEMO_PASSWORD` to a strong disposable password, then run
`podman-compose -f demo/compose.yaml up --build`. Visit
`https://wiki.localhost:8443` and trust the demo's local Caddy CA in the browser.
Use **Admin** and the supplied password. The demo generates its key in a persistent
runtime volume; the image contains no private key. Proxy mode publishes only public
metadata. A real compatible provider is needed for interactive SSO.

```sh
composer install
composer test
sh tests/container.sh
```

Docker is supported by `CONTAINER_ENGINE=docker sh tests/container.sh`.
The [dated results](docs/TESTING.md) distinguish signed synthetic provider tests,
native host tests, public document checks and unverified live logins.

Licensed [AGPL-3.0-only](LICENSE). The bundled `firebase/php-jwt` dependency retains
its BSD-3-Clause licence. SOCA is developed by [RESOAuth](https://github.com/RESOAuth).
