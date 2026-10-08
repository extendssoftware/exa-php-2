# Authentication

The `ExtendsSoftware\ExaPHP\Authentication` component defines credential verification that returns an authenticated
[Identity actor](../identity/README.md). Applications supply credential objects and an `Authenticator` implementation.

## Authenticate credentials

Inject `Authenticator` into the application service responsible for authentication and pass a `Credentials` object:

```php
<?php

declare(strict_types=1);

// $authenticator implements Authenticator; $credentials implements Credentials.
$actor = $authenticator->authenticate($credentials);
```

Successful return establishes the principal represented by the actor. Use that actor in subsequent application
operations and [authorization requests](../authorization/README.md). Authentication does not grant permissions or
create a session.

## Implement verification

`Credentials` is a marker contract for application-defined credential objects. Define the fields required by your
chosen authentication mechanism and implement `Authenticator::authenticate()` to verify them. Inject dependencies
needed for verification through the constructor. Return an `Actor` only after verification succeeds.

Reject unsupported credential types with `Exception\UnsupportedCredentialsException`. Reject credentials that do not
establish a principal with `Exception\InvalidCredentialsException`. Translate expected operational failures into
`Exception\AuthenticationFailedException`, preserving the original cause as the previous exception. Unexpected
programming errors should propagate rather than being reported as rejected credentials.

Treat credential contents as sensitive. Do not include them in exception messages or retain them as exception
properties. Use PHP's `SensitiveParameter` attribute on concrete implementation parameters receiving credentials
and on constructors receiving secrets to redact those arguments from stack traces. The marker interface does not
provide automatic redaction; avoid logging credential objects and review lower-level exception messages for secrets.

## Handle failures

All component exceptions implement `AuthenticationException`:

- `Exception\AuthenticationFailedException` extends `RuntimeException`: verification could not complete because of
  an operational failure. Keep this distinct from credential rejection when reporting or retrying failures.
- `Exception\InvalidCredentialsException` extends `RuntimeException`: credentials were rejected. Present a generic
  failure without exposing account details or the submitted credentials.
- `Exception\UnsupportedCredentialsException` extends `InvalidArgumentException`: the configured authenticator cannot
  handle the credential type. Check credential extraction and authenticator wiring.

These exceptions accept the standard exception message, code, and previous cause. Their messages are diagnostic data;
choose public error responses separately at the application's transport boundary.
