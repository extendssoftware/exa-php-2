# Identity

The `ExtendsSoftware\ExaPHP\Identity` component represents the principal performing an operation through an immutable
`Actor`. Pass the actor explicitly to application operations that need to know who is acting.

## Create an actor

```php
<?php

declare(strict_types=1);

use ExtendsSoftware\ExaPHP\Identity\Actor;

$user = new Actor(id: '42', kind: 'user');
$service = new Actor(id: 'billing-service', kind: 'service');
$system = new Actor(id: 'scheduler', kind: 'system');
```

`id` identifies a principal within its `kind`; both values together identify the principal. For example, user `42`
and service `42` are different principals. Kinds are application-defined strings, so applications can introduce their
own categories. Both values must be non-empty and are case-sensitive, with no trimming or normalization. Choose stable
values and enforce application-specific formats before construction.

An actor represents a principal without containing or loading an application entity such as `User`. Resolve that entity
in application code when its business data is needed. Pass actors per operation rather than storing a mutable global
current actor, especially in persistent workers that process multiple operations.

## Establish trust

Constructing an actor validates its fields only. It does not verify credentials, establish that a principal exists, or
grant permissions. Establish the actor through authentication or a trusted application entry point before using it for
access decisions. Do not treat actor identifiers supplied in request or message payloads as authentication proof.

## Handle invalid values

An empty identifier or kind raises `Exception\InvalidActorException`, which extends `InvalidArgumentException` and
implements the component's root `IdentityException` contract.
