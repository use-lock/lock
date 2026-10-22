# Security Policy

## Reporting a Vulnerability

Report suspected vulnerabilities privately to [manuel@christlieb.eu](mailto:manuel@christlieb.eu).
Please include the affected version or image tag, a description of the impact, and reproduction
steps or a minimal proof of concept. Remove credentials, signing keys, tokens, and personal
data from reports. Do not open a public issue or pull request containing vulnerability details
before a fix and disclosure have been coordinated.

Vulnerabilities in the identity provider protocol itself (the OAuth and OpenID Connect endpoints,
sign-in, consent, tokens) usually live in [use-lock/server](https://github.com/use-lock/server).
Report them the same way; they are fixed there and shipped in a Lock release.

## Supported Versions

Only the latest published release and its `ghcr.io/use-lock/lock` image receive security fixes.
Reports about any version are welcome; include whether the issue also affects the latest release
if you can verify it. Older releases do not receive backported fixes.

The supported runtime is the published image, or PHP 8.5 with PostgreSQL 16 when you run the
application yourself.

## Compatibility Before 1.0

During `0.x`, minor releases may include breaking changes. Patch releases are intended to
preserve compatibility; a security fix may require a breaking change, which will be identified
in the release notes. Review the release notes before upgrading a deployment.
