# Symfony Framework — Architecture

## Purpose

Symfony is a set of reusable PHP components and a full-stack web application framework.
This repository contains the canonical source for all Symfony components, bridges, bundles,
and contracts. Each component is independently installable via Composer.

## Directory Structure

```
src/
  Symfony/
    Bundle/          Full-stack bundles (FrameworkBundle, SecurityBundle, TwigBundle, …)
    Bridge/          Third-party integration bridges (Doctrine, Twig, Monolog, PSR-HTTP, …)
    Component/       Standalone framework components (see below)
    Contracts/       Lightweight PHP interfaces and abstract contracts (no concrete deps)
```

### Key Components

| Component          | Purpose |
|--------------------|---------|
| HttpFoundation     | HTTP abstraction: Request, Response, Cookie, Session, File uploads |
| HttpKernel         | Request-to-response pipeline: Kernel, HttpKernel, events, controller resolution |
| Routing            | URL matching and generation |
| DependencyInjection| Service container: definitions, compilation, dumping |
| Console            | CLI applications: Application, Command, Input/Output abstractions |
| Security           | Authentication and authorisation: authenticators, voters, firewalls, tokens |
| Cache              | PSR-6/PSR-16 cache adapters: array, filesystem, Redis, APCu, Doctrine DBAL |
| EventDispatcher    | Symfony-flavoured PSR-14 event dispatcher |
| Form               | Form building, validation, and rendering |
| Validator          | Constraint-based validation engine |
| Serializer         | Object ↔ array/JSON/XML/CSV serialisation |
| Config             | Configuration loading, caching, and validation |

## Key Design Decisions

### Request / Response (HttpFoundation)
- `Request` and `Response` model HTTP messages as mutable PHP objects.
- Proxy-trusted IP detection uses a bitmask of `Request::HEADER_*` constants
  rather than a string list, preventing header-injection attacks.
- `Cookie` uses an immutable fluent builder (`withSecure()`, `withSameSite()`, …).

### Kernel Boot Sequence (HttpKernel)
1. `Kernel::boot()` registers bundles and builds/loads the DI container.
2. `HttpKernel::handle()` dispatches `KernelEvents::REQUEST` → resolves controller
   → dispatches `KernelEvents::CONTROLLER` → resolves arguments → calls controller
   → dispatches `KernelEvents::RESPONSE` → dispatches `KernelEvents::FINISH_REQUEST`.
3. The container is compiled and dumped to PHP on first boot for performance.
   Subsequent boots load the dumped class directly (O(1) instead of O(n_services)).

### Security Layer
- Authenticators implement `AuthenticatorInterface` and produce `Passport` objects.
- `Passport` bundles user identity with credential badges (PasswordCredentials, CsrfTokenBadge, …).
- `TokenInterface` carries the authenticated `UserInterface` and is stored in the session.
- Access decisions are made by `AccessDecisionManager` composing multiple `VoterInterface`.

### Cache Adapters
- All adapters extend `AbstractAdapter` which handles deferred saves, tagging, and
  namespace prefixing.
- PSR-6 (`CacheItemPoolInterface`) and PSR-16 (`CacheInterface`) are both supported;
  `Psr16Cache` wraps a PSR-6 pool to expose the simpler PSR-16 API.
- `TagAwareAdapter` wraps any adapter and adds tag-based invalidation.

### Console Application
- `Application` maintains a registry of `Command` instances (eager or lazy via `CommandLoaderInterface`).
- Commands declare their `InputDefinition` in `configure()` and implement logic in `execute()`.
- Signal handling (SIGINT/SIGTERM/…) is delegated to `SignalRegistry` when `pcntl` is available.

## Extension Points

- **Custom Request/Response subtypes**: call `Request::setFactory()` to override the request class.
- **Custom bundles**: implement `BundleInterface` (or extend `Bundle`) and register in `Kernel::registerBundles()`.
- **Custom authenticators**: implement `AuthenticatorInterface` and register under a firewall.
- **Custom cache adapters**: extend `AbstractAdapter` and implement `doFetch/doHave/doDelete/doSave/doClear`.
- **Custom console commands**: extend `Command`, implement `configure()` + `execute()`, and register with `Application::add()`.
- **Custom voters**: implement `VoterInterface` and tag with `security.voter`.

## Dependency Flow

```
Contracts (no deps)
    ^
    |
Component  <-- each component declares minimal Composer deps
    ^
    |
Bridge     <-- bridges depend on Component + third-party lib
    ^
    |
Bundle     <-- bundles depend on Component/Bridge + FrameworkBundle
```

Components must not depend on bundles or bridges. Bridges must not depend on bundles.
This layering guarantees components remain usable standalone without the full framework.
