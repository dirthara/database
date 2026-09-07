# Exceptions

Exceptions in Dirthara follow the same convention as PHP's built-in exceptions. Each package has a base exception that 
extends from `Exception`. All exceptions thrown by the Dirthara package extend from this base exception.

The package base exception accepts an optional `array<string, mixed> $context` as
the fourth constructor argument, after message, code, and previous. It exposes
this data through `getContext(): array` and stores it in a protected property.
Specialized exceptions inherit this behavior.

Exceptions carry data without logging themselves or depending on a logger package.
The application exception handler can pass `getContext()` to a PSR-3 logger,
adding the caught exception under the `exception` key. That key must contain the
caught exception even if context already contains an `exception` entry.

Include useful diagnostic metadata, such as the connection name, driver, and
operation. Do not include passwords, credential-bearing DSNs, or raw query
parameter values in context.
