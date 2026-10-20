# Verification evidence

Assessed on 21 October 2026 (Europe/London), against requirements commit
`60ea4db827457b4a4a7b11d79e86b654612a75e1`, plugin version `0.1.0`.
The PR commit identifies the exact implementation; rerun the commands on that commit.

| Verification | Actual result | Scope |
| --- | --- | --- |
| `composer test` | 129 core assertions plus host packaging checks pass | Signed synthetic provider, ES256/ES384/RS256, hosted and proxy, no real-provider claim. |
| `sh tests/container.sh` | 24 native assertions and HTTP route/recovery checks pass | Official MediaWiki 1.43.11, PHP 8.3.35, PluggableAuth 7.5.0, SQLite; runtime installs schema and extension, then recreates the container using persisted configuration, database and signing key. |
| Public RESOAuth discovery plus Dynamic CIMD | Passed 20 October 2026 23:25 UTC, 21 October locally | Real TLS read-only document check; `client_id_metadata_document_supported` absent, provider support unconfirmed. |
| Real RESOAuth end-user login, hosted mode | Not performed | Credentials and interactive provider consent not available. |
| Real RESOAuth end-user login, proxy mode | Not performed | Credentials and interactive provider consent not available. |
| Other providers, MySQL/PostgreSQL host runtime, multi-worker races, browser automation, WCAG audit, load/failover, external review, OIDC conformance | Not performed | Required before making corresponding support/certification claims. |

Core fixtures validate private-key client assertions, audience, assertion lifetime,
PKCE verifier and provider code single use before returning a genuinely signed test
ID token. Tests cover wrong signature/header/issuer/subject/audience/nonce/times,
callback state/issuer/code, expired/revision-changed transactions, emergency disable,
UserInfo conflicts versus optional outage, assurance, incompatible discovery,
metadata mismatch, default/custom proxy safety/encoding, verified email/domain
admission, wildcard boundaries, deny precedence, unsafe URLs/IPs and duplicate JSON.
These are simulations of provider behaviour, not live interoperability evidence.

Native tests exercise an installed MediaWiki database and user/session APIs:
browser-bound single-use ledger, no email-based account matching, unique links,
returning-profile preservation, actual local provisioning/link/default permissions,
failure rollback, stale settings, pre-activation evidence and emergency test disable.
The HTTP checks assert public metadata and the native local-password/SSO login UI.

Use an isolated fixture site. Never run the account-creating integration test against
production. The container script builds immutable upstream references, creates a
fresh SQLite wiki and generated disposable administrator password, runs the checks
and removes the container. No real credentials or private keys are in the repository.

Acceptance for a real provider: publish/check the selected metadata mode, configure
verified admission, approve a local link or enable least-privilege provisioning,
start an administrator-bound candidate test, complete the actual provider flow,
record redacted provider/mode/surface/revision/time, enable, then test ordinary native
login, copied callback/replay failure and local recovery. Repeat for both metadata
modes and every supported provider/algorithm before claiming that coverage.
