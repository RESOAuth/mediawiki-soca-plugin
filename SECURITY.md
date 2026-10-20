# Security and support

Version 0.1.0 is an alpha. Arrange a security review and complete the outstanding
[requirements](docs/REQUIREMENTS.md) before production deployment. The maintained
target is MediaWiki 1.43 LTS with supported PHP and PluggableAuth releases.

Report suspected vulnerabilities privately through
[GitHub security reporting](https://github.com/RESOAuth/mediawiki-soca-plugin/security/advisories/new).
Do not put tokens, callback query strings, private keys or user claims in public
issues. The project aims to acknowledge reports within three working days and
provide a triage or remediation update within ten working days. These are project
targets, with no availability or response SLA.

Releases must not proceed with unresolved critical or high findings. CI audits the
locked Composer dependency and uses CodeQL for GitHub Actions and Python packaging
code. [CodeQL does not support PHP](https://docs.github.com/en/code-security/concepts/code-scanning/codeql/codeql-code-scanning),
so this does not represent static analysis of the PHP authentication paths. Threat
modelling, independent review, conformance testing and additional PHP analysis remain
release requirements. Manual package workflows create a deterministic ZIP, SPDX SBOM,
checksum and GitHub build provenance; no package or release is published by opening a PR.

The trusted issuer controls validated identity, verified email and authentication
assurance. Admission rules do not prove local account ownership. Links require
explicit local administrator approval, and privileged local users need separate
deployment approval. A malicious approved issuer can impersonate its linked subjects.

Protect the host database, native session secret and signing key separately. Use a
shared MediaWiki session backend across workers and retain its normal secure cookies.
There are no plugin cookies and no JavaScript browser storage. The native encrypted
session stores PKCE material; a minimal shared database ledger enforces single use.
Configure admission/rate limits, audit access/retention and trustworthy reverse proxies
at the host. Do not put login callback queries into access logs.

SSO defaults to disabled, provisioning defaults to disabled, admission defaults to
deny, and administrator accounts are excluded without local deployment approval.
There is no TLS bypass, private-network HTTP allowance or silent algorithm fallback.
Keep a tested local recovery login. `SOCA_EMERGENCY_DISABLE=1` blocks ordinary and
candidate logins even when saved settings enable SSO. Existing MediaWiki sessions
follow host policy; global SSO-session revocation remains an explicit gap.
