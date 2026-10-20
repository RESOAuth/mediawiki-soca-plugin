# Operator guide

## Deployment and precedence

Use a configured canonical HTTPS origin in `$wgCanonicalServer`. Never derive it
from an incoming Host or forwarded header. MediaWiki is responsible for trusted
proxies and secure native session cookies. Metadata routes use the canonical script
path and query-form titles, so installation prefixes are preserved.

Defaults come from MediaWiki, then saved `Special:SOCA` settings, then
`$wgSOCAConfig`, then the object in `SOCA_CONFIG_FILE`. Deployment keys override and
lock the corresponding GUI controls. Environment `SOCA_PRIVATE_KEY_FILE` overrides
the optional deployment `private_key_file` reference. No key value belongs in a GUI,
database setting, repository or image. `SOCA_EMERGENCY_DISABLE=1`, `true` or `yes`
overrides every activation setting and blocks pending callbacks and test login.

Every supported configuration key is accepted in `$wgSOCAConfig` or the deployment
JSON object. For a reproducible headless installation:

```json
{
  "issuer": "https://auth.resoauth.cloud",
  "metadata_mode": "proxy",
  "allowed_domains": ["example.com", "*.partner.example.com"],
  "denied_emails": ["blocked@example.com"],
  "provision": false,
  "enabled": false
}
```

Do not deployment-lock `enabled` to false if you intend to enable through the GUI or
CLI. Changing that boolean alone does not change the candidate revision. Security,
key, metadata, profile or policy changes invalidate existing activation evidence.
Read-only status shows effective values and sources. Private key material is never
returned; private key references remain deployment-managed.

```sh
php extensions/SOCA/maintenance/soca.php --status
php extensions/SOCA/maintenance/soca.php --config /secure/soca-settings.json
php extensions/SOCA/maintenance/soca.php --check
php extensions/SOCA/maintenance/soca.php --preview-email person@example.com
php extensions/SOCA/maintenance/soca.php --enable
php extensions/SOCA/maintenance/soca.php --disable
php extensions/SOCA/maintenance/soca.php --cleanup
```

The `--config` option replaces saved GUI settings with the supplied object using
compare-and-swap; repeating it is safe but increments the database revision. A
deployment file can also be changed atomically without saving any form. Status and
validation always use the same effective configuration as login. Preserve previous
deployment versions for rollback and rerun checks after any security change.

Checking documents does not prove that the provider accepts CIMD. The existing
administrator must start a candidate test through a CSRF-protected POST to
`Special:SOCA` with `soca_action=test` and the native edit token. An automated native
admin session may submit the same endpoint without visiting the settings form.
The browser follows the real provider flow and returns to the same callback used
for ordinary login. The test applies full protocol and admission checks, records
only revision/time, and does not create/link accounts, synchronise profiles or
replace the operator's session. Evidence remains valid for its exact revision until
configuration/key changes. Both document and test results are required for activation.

## Supported settings

| Key | Default and validation |
| --- | --- |
| `issuer` | `https://auth.resoauth.cloud`, exact trusted HTTPS issuer, no query or fragment. |
| `enabled`, `provision` | Boolean false. Provisioning creates standard MediaWiki accounts only. |
| `allowed_domains`, `denied_domains` | Empty arrays. Exact DNS names or one leading `*.` followed by a dotted DNS name. |
| `allowed_emails`, `denied_emails` | Empty arrays of valid addresses. Deny takes precedence; email must be verified by a boolean true claim. |
| `metadata_mode` | `hosted` or `proxy`. |
| `proxy_template` | `https://dynamic-cimd.resoauth.cloud/cimd/%s`; one placeholder in the HTTPS path, no query. GUI provides reset and URL preview. |
| `metadata_jwks_mode` | `uri` for hosted mode, `inline` for proxy mode. Only public keys are published. |
| `client_name`, `client_uri`, `logo_uri`, `policy_uri`, `tos_uri` | Platform identity and published privacy page where available. Optional unavailable URLs are omitted. Overrides require HTTPS. |
| `redirect_uri`, `metadata_uri`, `jwks_uri` | Canonical host-generated routes. Deploy overrides only when routing and public documents actually match. |
| `private_key_file` | Optional deployment reference, overridden by `SOCA_PRIVATE_KEY_FILE`. Absolute readable PEM path, at most 32 KiB, no world permissions. |
| `key_id` | Stable SHA-256 public JWK thumbprint by default. Optional 1 to 200 safe characters. |
| `assertion_alg`, `id_token_alg` | Independent ES256 defaults. ES384 and RS256 require explicit configuration and matching discovery support. Other requested algorithms are not implemented. |
| `assertion_audience` | `token_endpoint`; alternatively `issuer` after confirming provider semantics. |
| `userinfo` | `optional`, `required` or `disabled`. Returned subject/identity conflicts always fail. ID-token claims take precedence. |
| `scopes` | `openid email profile`, restricted to those scopes. No offline access. |
| `max_age` | Null or integer 0 to 86,400 seconds. Zero requests fresh authentication. Validated `auth_time` is mandatory when configured. |
| `required_acr`, `required_amr` | Empty allowlists by default, at most 20 non-empty values each. Configure only with documented issuer semantics; verified email is not MFA. |
| `privileged_user_ids` | Empty deployment list of integer local IDs. Sysop, bureaucrat and interface-admin accounts otherwise fail eligibility checks. |

Wildcard `*.example.com` permits `a.example.com` and `a.b.example.com`, but excludes
`example.com` and `badexample.com`. Malformed rules fail on configuration and startup.
All admission checks run again at every login. Claim-based role grants are not
implemented, so existing permissions are preserved.

## Identity approval, recovery and lifecycle

An authenticated administrator can approve an existing account through the host CLI:

```sh
php extensions/SOCA/maintenance/soca.php --link-user 'Local user' --subject 'exact-provider-subject' --approve-link
php extensions/SOCA/maintenance/soca.php --unlink --subject 'exact-provider-subject'
```

The issuer is always the configured trusted issuer. Approval never uses an email
match. The unique issuer/subject digest is stored together with the exact values and
local ID. Conflicting links fail. Unlinking invalidates pending login transactions,
preserves local users and does not terminate existing native sessions. Record
administrator approval in your access-controlled audit process; richer tamper-evident
identity audit records and an interactive recovery/migration wizard remain gaps.

Provisioning is opt-in. A deterministic subject-derived username avoids reusing
mutable names/emails. User creation, default permissions and identity link share a
native database atomic section with a rollback savepoint. There is no usable
default password. Existing names, email, profile edits and privileges remain intact.
New accounts may receive a validated name at creation only. Full field mapping
policies and additional groups are future work.

Keep local login at `Special:UserLogin`. SOCA refuses to load if PluggableAuth local
login is disabled or automatic login is enabled. Emergency disable blocks ordinary
and candidate logins; pending entries expire after five minutes. Disable in settings
or CLI also deletes the replay ledger immediately. Remove the SOCA entry from
`$wgPluggableAuth_Config` before unloading the extension. Re-enabling needs a current
configuration and valid evidence. Local accounts and permissions are never deleted
by disable/uninstall. To permanently remove plugin tables/keys, first disable,
invalidate transactions, remove identity links through an approved export/deletion
process, then explicitly drop the three `soca_*` tables and remove the secret. Review
backup retention separately. Do not restore old replay tables or revoked links/keys.

## Metadata, keys, bounds and privacy

Hosted metadata and JWKS use public 60-second cache lifetimes. Proxy metadata uses
recursively lexicographically sorted JSON, compact UTF-8, unescaped slashes and
Unicode, then unpadded base64url, excluding `client_id`. The proxy adds the exact URL
as `client_id`. The payload is capped at 6,144 bytes and URL at 8,192 characters.
Document checks compare the complete returned document and keys exactly. Proxy URLs
and decoded metadata are public and visible to the proxy, provider and logs.

Client keys persist in deployment secret storage. Automatic rotation, overlapping
key sets and non-exportable KMS keys are not implemented. For a manual change,
disable SSO, retain the previous key securely for rollback, publish/check the new
documents, test and enable. Inline proxy keys change the client ID, potentially
changing pairwise subjects and consent. Never infer the replacement subject from
email. Approve each replacement link independently. This manual procedure has a
planned SSO outage and does not satisfy the unattended-rotation target.

Transactions last 300 seconds, at most eight pending per native browser session.
Handoff tickets last 60 seconds, assertions last 60 seconds, token clock skew is
60 seconds, ID-token lifetimes are capped at 86,400 seconds. Provider JWKS is fetched
once per callback, never served stale and never automatically retried. Network limits
are a 3-second connect timeout, 12-second cURL total timeout (DNS resolution occurs first), 256 KiB response, 32 KiB
headers, HTTPS only, all DNS answers public, pinned connection address and no
redirects. Deployment rate limiting must protect login, metadata, admin checks and
provider endpoints. Daily cleanup removes expired replay entries; login also prunes
them. Broader cache/performance/fault/load and concurrency certification is pending.

Codes/access tokens exist only during callback processing; no refresh/ID tokens are
retained. Transactions are encrypted with native MediaWiki session secrets. Identity
links persist until approved removal. No claim inspection report or telemetry is
implemented. The demo redacts query strings from Apache logs. Configure every proxy,
CDN and upstream logger to redact callback codes/state, and define native session,
audit log and backup retention. The default external services are
`https://auth.resoauth.cloud` and, in proxy mode,
`https://dynamic-cimd.resoauth.cloud`; review your operator's terms and privacy notices.
