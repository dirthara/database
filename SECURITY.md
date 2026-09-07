# Security Policy

## Supported versions

The package is pre-1.0. Only the latest release line receives fixes; there are
no backports to earlier ones.

| Version | Supported |
| --- | --- |
| 0.1.x | Yes |
| Older | No |

## Reporting a vulnerability

Report vulnerabilities privately through GitHub, using
[Report a vulnerability](https://github.com/dirthara/database/security/advisories/new)
on the repository's Security tab. That opens a private advisory visible only to
you and the maintainers.

Please do not open a public issue, pull request, or discussion for a
vulnerability. A public report tells everyone running the package about the
problem before there is a version that fixes it.

Include what you have:

- Which version you found it in.
- What an attacker can do, and what they need to already have to do it.
- The smallest code or configuration that shows the problem.
- The database and PDO driver, if the behaviour depends on them.

You will get an acknowledgement that the report was received and an assessment
once the report has been reproduced. If a fix is warranted, the advisory is
published together with the release that contains it, crediting you unless you
ask otherwise.

## Scope

The package builds DSNs, SQL fragments, and log context from configuration and
from application input. Anything that gets past those boundaries is in scope,
including:

- Values reaching a DSN or a statement in a way that lets them change its
  structure, rather than being rejected or bound as a parameter.
- Credentials appearing in exception messages, exception context, dumps, or
  stack traces.
- A transaction that reports success without committing, or that leaves a
  connection with uncommitted work after `run()` returns.
- Connection state surviving `disconnect()` in a way that leaks between
  requests or between tenants.

Out of scope:

- SQL injection in an application's own queries. String interpolation into SQL
  is the caller's responsibility, and the package documents bound parameters as
  the alternative.
- Bugs in PHP, PDO, or a database driver extension. Report those upstream; if
  the package can defend against one, that is worth reporting here too.
- Credentials leaked by an application logging its own configuration, or by
  passing something other than `getContext()` to a logger.
- A database misconfiguration the package faithfully connected to, such as an
  account with more privileges than it needs.

## Hardening notes

Two of the package's own rules exist for security reasons, and overriding them
weakens the guarantees above.

A charset, a savepoint prefix, and DSN fields such as a host or database name
end up in text that cannot be parameterised, so they are validated on
construction and rejected when they do not match a strict pattern. A driver of
your own should use the `PdoDriver` helpers rather than concatenating a DSN
directly.

Exception context is written to logs. It carries the connection name, driver,
host, port, database, operation, SQLSTATE, and the SQL — never a password, a
credential-bearing DSN, or a bound parameter value. Keep that split in your own
drivers, middleware, and exceptions.
