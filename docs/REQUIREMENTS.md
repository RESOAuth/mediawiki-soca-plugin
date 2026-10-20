# Requirements assessment

Assessed requirements revision: `60ea4db827457b4a4a7b11d79e86b654612a75e1` from [soca-plugins](https://github.com/RESOAuth/soca-plugins/blob/60ea4db827457b4a4a7b11d79e86b654612a75e1/requirements.md).

Every row is assessed for plugin `0.1.0`, 21 October 2026 (Europe/London), on the commit containing this document. Protected surface: MediaWiki web sign-in through PluggableAuth. Both hosted and proxy signed fixtures pass; real end-user login for either mode is unverified. Partial means a required capability or verification item remains; operator disablement does not remove requirements. No not-applicable exceptions are used.

| ID | Status | Evidence | Result / remaining gap |
| --- | --- | --- | --- |
| F-01 | Partial | lib/soca/src/Client.php; core tests | One explicit issuer; multiple isolated profiles and prior activation compatibility diagnostics remain. |
| F-02 | Partial | Client::start/complete; core signed fixtures; Service.php | Code, S256, independent state/nonce and asymmetric assertions implemented; live provider/browser completion remains unverified. |
| F-03 | Partial | Client::verify/complete; core negative/assurance tests | Signature, identity, response issuer, UserInfo and configured assurance checks implemented; broader provider semantics and conformance pending. |
| F-04 | Partial | Client::metadata/checkDocuments; native HTTP routes | CIMD/JWKS, branding/privacy defaults and exact checks implemented; cache invalidation/public reachability across real deployments unverified. |
| F-05 | Partial | Core hosted/proxy fixtures; live public check | Deterministic default/custom proxy, exact content check, preview/reset and size caps implemented; overlapping-key sets/rollover not implemented. |
| F-06 | Supported | Keys::jwk; core public-boundary tests; OPERATIONS.md | Public inline or referenced keys; no secrets/admission/user data published. Proxy visibility documented. |
| F-07 | Partial | Client::revision; Service.php; OPERATIONS.md | Revision binding invalidates changed clients/keys. Routine overlapping key/client migrations and in-flight snapshot continuity remain. |
| F-08 | Partial | Keys.php; Configuration.php; demo runtime secret | Persistent deployment PEM, stable IDs and protected references. Non-exportable keys, coordinated key manager and tested restore/lost-key recovery remain. |
| F-09 | Planned | OPERATIONS.md manual key-change procedure | No unattended rotation, cache warming, overlapping keys or atomic promotion. Manual changes deliberately disable SSO. |
| F-10 | Partial | Core signed ES256/ES384/RS256 mode matrix | Independent ES256 defaults/pinning and explicit ES384/RS256 supported. ES512, PS256/384/512, Ed25519/Ed448 and ML-DSA are gaps. |
| F-11 | Partial | Client::verify; provider key negative tests | Fresh bounded JWKS fetch per callback, no stale trust or refresh retries. Revocation feeds and shared rate-limited caches remain. |
| F-12 | Supported | Policy.php; core domain/deny tests; CLI policy preview | Deny by default; strict verified email, exact/wildcard labels and deny precedence; admission reapplied on each login. |
| F-13 | Partial | Service::resolve/eligible; native account tests | Unique links, blocked/locked/sensitive checks and preserved profiles. Broader permission/surface/session eligibility combinations remain unverified. |
| F-14 | Partial | Service::resolve; native provisioning/rollback tests | Opt-in standard accounts with invalid password and atomic link/user creation. Multi-worker database fault/race evidence remains. |
| F-15 | Planned | SECURITY.md; OPERATIONS.md | Identity and permissions separated, no privilege grants. Full allowlisted claim mappings, elevation approvals and bounded affected-session revocation remain. |
| F-16 | Partial | Store.php; maintenance/soca.php; native link tests | Unique issuer/subject, explicit CLI administrator approval and unlink implemented. Interactive recovery/local reauthentication and richer audit records remain. |
| F-17 | Partial | OPERATIONS.md manual migration/recovery | No email-based or silent relinking. Coordinated identity/client migration and audited conflict-recovery tooling remain. |
| F-18 | Partial | Service::resolve; native profile preservation test | Validated name/email on creation; existing local fields preserved. Full claim preview, custom fields and per-field synchronisation policies remain. |
| F-19 | Planned | SECURITY.md privacy boundary | No claim capture feature. Operator-bound encrypted expiring inspection reports remain. |
| F-20 | Partial | Configuration.php; SpecialSOCA.php; CLI; native stale/activation tests | Headless settings/check/status/maintenance, deployment locks and candidate gate implemented. Multi-profile operation, UI rollback/claim preview and browser test-mode evidence remain. |
| F-21 | Partial | SocaPlugin.php; Service handoff; native login UI check | Native PluggableAuth sessions and local logout. RP/back-channel logout, assurance/session lifetime/revocation and redirect automation remain. |
| F-22 | Partial | Hooks::registration; native recovery UI/emergency tests | Local login retained and deployment emergency block implemented. End-to-end recovery/password login and upgrade recovery drills remain. |
| F-23 | Partial | Hooks/schema; native installer; OPERATIONS.md | Host extension/account/session APIs, disable invalidation and preserved local accounts. Full lifecycle/upgrade/multi-site deletion audits remain. |
| F-24 | Supported | Client::complete; SECURITY.md token boundary | Minimum sign-in scopes, no offline request, no code/access/ID/refresh token retention. Delegated-access profile unsupported. |
| F-25 | Partial | Service.php native encrypted sessions; shared DB replay; native ledger tests | No extra cookies/browser storage; encrypted PKCE, browser binding and atomic consume. Multi-tab/browser and multi-worker load evidence remains. |
| N-01 | Partial | Maintained firebase/php-jwt; core/native negative tests | Secure defaults/native CSRF and escaping implemented. Full threat model, PHP security analysis and independent review remain. |
| N-02 | Partial | Http.php; unsafe URL/IP tests; live TLS document check | HTTPS, pinned public DNS answers, no redirects, response/time limits. Resolver total deadline, rebinding/slow-response runtime tests remain. |
| N-03 | Partial | SECURITY.md; OPERATIONS.md; demo redacted logs | Minimal links, encrypted native transactions and no token retention/telemetry. Verified infrastructure-wide retention/deletion/backup controls remain operator work. |
| N-04 | Partial | Shared native DB/session design; native SQLite tests | Shared replay and rollback implemented. MySQL/PostgreSQL runtime, multi-site failover/restore and rolling upgrade evidence remain. |
| N-05 | Partial | OPERATIONS.md documented bounds; Service pending cap | Small replay ledger, bounded pending sessions/network/metadata and cleanup. Measured throughput/resource budgets and sustained abuse tests remain. |
| N-06 | Partial | Native labelled controls, safe error reasons and event logs | Accessible structure and redacted events implemented. WCAG audit, assistive checks, tamper-evident detailed audits and metrics remain. |
| N-07 | Partial | .github/workflows; tools/build.py; SECURITY.md | Locked deps, SHA-pinned Actions, audit, deterministic ZIP/SPDX/provenance workflow. Published signed release, full vulnerability/security gates and migration rollback remain. |
| N-08 | Partial | TESTING.md; core/native test suites | 129 signed synthetic checks, 20 native assertions and real document check. Real logins both modes, all combinations, browsers, conformance/external review remain. |
| N-09 | Partial | This assessment; OPERATIONS.md bounds; TESTING.md | Every ID assessed at version/date with reproducible evidence and gaps. Complete acceptance catalogue and live provider/surface/algorithm evidence remain. |

Evidence commands, exact tested host/runtime versions and distinctions between simulation, real public documents and real login are in [TESTING.md](TESTING.md). Configuration defaults, units, hard limits, session/retention effects and manual key/client-ID migration constraints are in [OPERATIONS.md](OPERATIONS.md). This table makes no production-readiness or conformance claim.
