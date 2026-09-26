# 10. Global Helpers

Section of: [BASELINE](../BASELINE.md)

## Location

All helpers live in app/Helpers/functions.php. Each is wrapped in
if (!function_exists('name')) so multiple loads are safe.

## Available Helpers

e(?string $value): string
  HTML-escape via htmlspecialchars with ENT_QUOTES | ENT_SUBSTITUTE
  and UTF-8. Use this on every string that touches HTML.

config(string $key, mixed $default = null): mixed
  Read a config value via the container. Dotted keys walk nested
  arrays.

base_path(string $suffix = ''): string
  Absolute base path of the installation. Rarely needed in views.

asset(string $path): string
  Prefixes the argument with /assets/. So asset('css/app.css')
  returns /assets/css/app.css.

csrf_token(): string
  Current CSRF token. Returns an empty string on failure.

csrf_field(): string
  A complete hidden input:

      <input type="hidden" name="_csrf" value="TOKEN">

method_field(string $method): string
  A hidden input for method spoofing:

      <input type="hidden" name="_method" value="PUT">

flash(string $key, mixed $default = null): mixed
  Reads a flash value via Session::getFlash(). Consumes on read.
  See section 4 for the full flash flow.

auth_user(): ?CorporateAid\Platform\Models\User
  The current authenticated user, or null.

view_partial(string $name, array $data = []): string
  Renders a partial and returns the HTML string. Catches all
  Throwable and returns an empty string on failure, logging the
  error.

## Usage by Context

Views may use all of these:

    e
    csrf_field
    csrf_token
    method_field
    flash (rare, mostly the layout)
    asset
    config
    view_partial
    auth_user

Controllers should use the container, not global helpers:

    $auth    = $this->container->get(Auth::class);
    $session = $this->container->get(Session::class);

The one exception: if a controller is rendering a small HTML string
inline (rare), it may call e() to escape.

Services, repositories, models: none of these use global helpers.
They receive their dependencies through constructors.

## Escaping Rule

Every string that lands in HTML must pass through e():

    <?= e($customer->name) ?>
    <a href="<?= e($url) ?>">
    <input value="<?= e($value) ?>">

The only exceptions:

1. Values you fully control and have already verified
2. HTML you built yourself with e() on the inside
3. The output of view_partial (which already escapes)
4. Numbers you have cast with (int) or (float)

If in doubt, escape.

## view_partial Behavior

view_partial never throws. If a partial fails to render, it returns
an empty string and writes the error to the log. This means a broken
partial will leave a blank spot in the page rather than crashing the
whole render.

That behavior is intentional. A missing modal should not take down
the whole admin.

## Adding a New Helper

Only add a global helper if the function is genuinely needed from
view code and cannot be reached any other way.

Before adding, ask:

1. Is this a computation over a value? -> method on the model.
2. Is this a data fetch? -> repository method.
3. Is this business logic? -> service.
4. Is this a tiny view convenience (escape, csrf field)? -> helper.

Most "I need a helper" moments are actually cases 1, 2, or 3.

When a helper is genuinely needed, wrap it in
if (!function_exists('name')) and document its return type.

## Helpers You Should Not Add

Do not add helpers that:

- Query the database
- Call HTTP APIs
- Read or write files
- Change session state
- Have side effects

Those belong in services, where they can be tested and where their
dependencies are explicit.
