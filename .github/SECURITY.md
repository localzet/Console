# Security policy

[Русская версия](SECURITY_ru.md)

## Supported versions

Security fixes are evaluated against the default development branch and the latest published release. No maintenance or backport schedule is published for older releases.

## Reporting a vulnerability

Report suspected vulnerabilities privately to Ivan Zorin at [creator@localzet.com](mailto:creator@localzet.com). Do not publish exploit details, credentials, personal data, or production output in an issue, discussion, or pull request.

When safe, include the affected package version or commit, PHP and operating system versions, the affected command or terminal helper, impact, and a minimal reproduction using synthetic data.

Reports are reviewed as availability permits. No response-time or remediation SLA is published. Coordinate public disclosure with the maintainer after a fix or mitigation is available.

## Security boundary

Localzet Console provides command registration and terminal helpers; it does not sandbox commands or authorize operating-system actions. Applications must validate arguments, restrict access to sensitive commands, protect configuration and credentials, and avoid writing untrusted text directly to a terminal.

Copyright © 2016-2026 [Localzet Group](https://www.localzet.com/). All rights reserved.
