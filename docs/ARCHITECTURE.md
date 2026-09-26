# Anaya Global Commerce Platform - Architecture

**Status:** Locked 2026-09-25
**Supersedes:** prior ARCHITECTURE.md (Recruit Aid heritage)

---

## 1. Purpose

This document defines the layered architecture of the Anaya Global
commerce platform. It exists because the previous foundation
(inherited from Recruit Aid) mixed product knowledge into the
framework, producing a cascade of bugs when a second product was
built on top of it.

This document is the contract. Code that violates it is wrong by
definition and must be corrected before merge.

---

## 2. Three Layers - One Direction

    Commerce
       |
       | depends on
       v
    Platform
       |
       | depends on
       v
    Core

**Hard rules:**

- Core has zero knowledge of Platform or Commerce.
- Platform has zero knowledge of Commerce.
- Commerce may use anything in Core and Platform.
- Never the reverse.

A single `use` statement that violates this direction is a bug.

---

## 3. Layer Responsibilities

### 3.1 Core - Framework

**Path:** `app/Core/`
**Namespace:** `CorporateAid\Core\*`

Owns:
- HTTP primitives: Request, Response, Router, Kernel
- Persistence primitive: Database
- Session, CSRF, Config, Container
- View renderer
- Migrator
- App bootstrapper
- HttpException

Core knows nothing about:
- Users, roles, permissions
- Custom fields, files, notifications, audit
- Products, stores, orders

Core is generic infrastructure. It could ship with any PHP project.

### 3.2 Platform - Shared Infrastructure

**Path:** `app/Platform/`
**Namespace:** `CorporateAid\Platform\*`

Owns:
- Identity: User model, UserRepository, RoleRepository, Auth
- Support: Logger, Hasher, EmailService, RateLimiter, PasswordPolicy, ActivityLogger, AuditLogger, CodeGenerator
- Services: SystemSettingService, NotificationService, NotificationRuleService, FileService, CustomFieldService
- CustomFields engine
- Deletion engine
- Middleware: Auth, Guest, CSRF, RateLimit, Permission, AuditLogger

Platform knows nothing about:
- Stores, products, orders, customers, carts, checkout
- Storefront routes
- Admin routes specific to a product

Platform is the same for every product built on this framework.
Recruit Aid, HR Aid, CRM Aid, Commerce Aid all share the exact same
Platform code.

### 3.3 Commerce - This Product

**Path:** `app/Commerce/`
**Namespace:** `CorporateAid\Commerce\*`

Owns:
- Store, Product, Variant, Category, Order, Cart, Customer models
- Commerce repositories, services, controllers
- Commerce middlewares: StoreResolution, SetupGate
- Commerce routes
- Commerce views
- Commerce seeders

Commerce may depend on:
- Core: freely
- Platform: freely

Commerce must never be referenced from Core or Platform.

---

## 4. App Bootstrapping

`Core\App` is a **bootstrapper**. It does exactly three things:

1. Registers Core services
2. Registers Platform services
3. Delegates product registration to a single product provider

    $providerClass = 'CorporateAid\Commerce\CommerceServiceProvider';
    if (class_exists($providerClass)) {
        $provider = new $providerClass();
        $provider->register($container);
        $provider->routes($router);
    }

`App.php` has **zero** `use` statements from `Commerce\*` or
`Recruit\*` or any future product. This is the invariant.

Adding HR Aid to a deployment means dropping `app/HR/` in and
extending the provider check. No changes to `App.php` logic.

---

## 5. The Product Provider Contract

Every product exposes one class:

    namespace CorporateAid\{Product};

    interface ServiceProvider
    {
        public function register(Container $c): void;
        public function routes(Router $router): void;
    }

`register()` binds everything the product needs into the container.

`routes()` registers all product routes. It is called after Core and
Platform services are bound, so the product can reference them.

The provider is the ONLY file App.php knows about for a product.

---

## 6. Middleware

Middlewares live in the layer that owns their concern.

### Platform middlewares (aliases in Kernel)

    auth        AuthMiddleware
    guest       GuestMiddleware
    csrf        CsrfMiddleware
    permission  PermissionMiddleware
    ratelimit   RateLimitMiddleware
    audit       AuditLoggerMiddleware

Platform middlewares must not hardcode product-specific redirect
targets. Any redirect destination is a constructor parameter:

    new GuestMiddleware($auth, '/commerce/ca-admin')

### Commerce middlewares (aliases in Kernel)

    store       StoreResolutionMiddleware
    setup.gate  SetupGateMiddleware

Commerce middlewares may use any Platform service.

### Kernel registration

Kernel's `$map` array holds every alias. Adding a product means
adding its middleware aliases. Adding a middleware to Platform means
adding one line.

---

## 7. Routes

Route files live at `routes/`. App loads them in fixed order:

    auth
    web
    commerce
    ... (future products add here)

A file that does not exist is silently skipped. This lets a
deployment ship with only the products it has installed.

Route files may only use:
- Core classes
- Platform classes
- Their own product classes

`routes/auth.php` points at `Commerce\Controllers\AuthController`.
If Recruit Aid were installed alongside, it would have its own
`routes/recruit-auth.php` (or similar) and its own login controller.
Platform never provides a login controller - that's a product
decision.

---

## 8. Container Conventions

Every service is registered as a singleton. The registration order
matters only when resolving eagerly; we use lazy closures everywhere,
so order is free.

Registration pattern:

    $c->singleton(FooRepository::class,
        fn() => new FooRepository($c->get(Database::class)));

    $c->singleton(FooService::class,
        fn() => new FooService(
            $c->get(FooRepository::class),
            $c->get(Auth::class)
        ));

No globals. No `App::instance()` inside services. Everything is
injected.

Views may use `app()` (helper) if unavoidable, but this is
discouraged.

---

## 9. Config

- `config/app.php` - deployment-neutral defaults
- `config/branding.php` - this deployment's brand identity
- `config/database.php` - neutral DB defaults
- `config/mail.php` - neutral mail defaults
- `config/local.php` - overrides (gitignored, web)
- `config/cli.php` - overrides (gitignored, CLI)

Config files loaded by App: everything in `config/` except `local.php`
and `cli.php`, then `local.php` merged on top.

Config is read via `config('app.name')` (helper) or
`$container->get('config')->get('app.name')` (direct).

---

## 10. Dev vs Production

### Development

- `composer.json`: `"classmap-authoritative": false` - new classes
  are discoverable immediately via PSR-4
- `config/local.php`: `'debug' => true`
- Apache should be **reloaded after every `app/` change** to clear
  OPcache

### Production

- `composer.json`: `"classmap-authoritative": true` - small perf win,
  hardened
- `config/local.php`: `'debug' => false`
- OPcache fully enabled
- Never expose `_diag.php` or similar

The dev/prod flip is done at deployment time, not committed to the
dev branch.

---

## 11. Error Handling

- Every exception is logged to `storage/logs/app.log`
- 500s generate a short `ERR-xxxxxx` reference ID
- The error page shows the reference ID
- Support greps the log for the reference to find the full trace
- Error pages live at `resources/views/errors/{403,404,500}.php`
- Error pages must not reference any path that assumes a product;
  "back" links point at `/` (which always exists in some form)

---

## 12. Testing Invariants

After every layer change, verify:

1. `php -l` on every modified file
2. `composer dump-autoload -o`
3. Apache reload
4. GET `/` returns storefront placeholder
5. GET `/login` returns login page
6. POST `/login` with valid credentials returns 302
7. GET `/commerce/ca-admin` returns admin placeholder

Any failure indicates a layer violation. Fix the violation, not the
symptom.

---

## 13. Adding a New Product Later

To add HR Aid to an install:

1. Drop `app/HR/` into the codebase
2. Create `HR\HRServiceProvider`
3. Add it to `App.php`'s provider check list
4. Add its middleware aliases to Kernel's `$map`
5. Add its routes file to `App::loadRoutes()`'s loop

Nothing else changes. If a change is required anywhere else, the
architecture has failed and the fix belongs in Core or Platform.

---

## 14. What This Document Forbids

- Product-specific code in Core or Platform
- Product-specific table names in Platform SQL
- Product-specific route paths in Platform middleware
- Product-specific permission slugs in Platform code
- `use` statements from Platform to Commerce, or Core to Platform
- Hardcoded redirect destinations in reusable middleware
- Framework-level awareness of what a "store" or "order" is
- Anything that makes adding the next product require editing Core

If a rule here feels too strict, the correct move is to add a new
rule, discuss it, and update the document - not to break the rule
silently.
