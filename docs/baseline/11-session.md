# 11. Session API

Section of: [BASELINE](../BASELINE.md)

## Location

app/Core/Session.php. The Session is a singleton bound in the
container and resolved via $this->container->get(Session::class) in
controllers.

## Writes

flash(string $key, mixed $value): void
  Writes a one-shot flash value. Survives exactly one subsequent
  request. See section 4 for the full flow.

flashOldInput(array $input): void
  Writes old form input for repopulation after validation failure.
  Strips password, password_confirmation, and _csrf before storing.

set(string $key, mixed $value): void
  Writes an arbitrary session value. Rarely used directly. Prefer
  flash() for one-shot data.

forget(string $key): void
  Removes a session key.

regenerate(bool $deleteOld = true): void
  Regenerates the session id.

destroy(): void
  Clears $_SESSION, unsets the cookie, and calls session_destroy().

## Reads (Consuming)

getFlash(string $key, mixed $default = null): mixed
  Reads a flash value and removes it. The unset() is what makes it
  one-shot. Never call this in a controller method that returns a
  rendered view.

old(string $key, mixed $default = ''): mixed
  Reads a value from $_SESSION['_old']. Non-consuming. Available
  across multiple reads within the same request.

## Reads (Non-Consuming)

get(string $key, mixed $default = null): mixed
  Reads an arbitrary session value.

has(string $key): bool
  True if the key exists.

hasFlash(string $key): bool
  True if a flash value exists without consuming it. Useful if you
  need to peek.

## Old Input Pattern

On a validation failure, a controller does:

    $session->flashOldInput($data);
    $session->flash('errors', $validator->errors());
    return Response::redirect('/commerce/ca-admin/foo/create');

On the next request, showCreate() reads:

    $errors = $session->getFlash('errors', []);
    $old    = $_SESSION['_old'] ?? [];

And passes both to the view. The view's $val closure reads from
$old first, then falls back to model properties, then to defaults.

Old input is not auto-consumed. It persists until the session is
regenerated or a successful save clears it. Clear explicitly after
a successful submission:

    $session->clearOldInput();

## Session Lifecycle

start()
  Called once by the Kernel before any middleware runs. Configures
  cookie parameters, enables strict mode, enforces idle timeout,
  and rotates the session id if stale.

enforceIdleTimeout()
  Private. Runs on every start(). If $_SESSION['_last_activity'] is
  older than the configured lifetime, the session is destroyed and
  restarted. Default lifetime is 7200 seconds (2 hours).

rotateIfStale()
  Private. Runs on every start(). Regenerates the session id every
  300 seconds.

## Storage Keys

$_SESSION['_flash'][$key]
  Flash messages. One-shot.

$_SESSION['_old'][$key]
  Old form input for repopulation.

$_SESSION['_last_activity']
  Timestamp of the last request. Used for idle timeout.

$_SESSION['_rotated_at']
  Timestamp of the last session id rotation.

$_SESSION['_reauth_at']
  Timestamp of the last re-authentication.

Never read or write these keys directly in application code. Use
the Session methods.

## Re-Authentication Tracking

markReauth(): void
  Marks the current session as freshly re-authenticated. Call after
  a successful password confirmation.

reauthAt(): int
  Returns the timestamp of the last re-auth, or 0 if never.

hasRecentReauth(int $minutes): bool
  True if the user has re-authenticated within the given window.
  Useful for sensitive actions like bulk deletion or approval of
  high-value transactions.

clearReauth(): void
  Clears the re-auth timestamp. Called on logout.

No module currently uses hasRecentReauth(). The API is available
for future gates.

## The Session Singleton

Session is bound once and shared across the request. There is no
per-controller session instance. Every $this->container->get(Session::class)
returns the same object.

Because of this, there is no way to have two sessions in one
request. Cross-store queries cannot be smuggled through the session
layer.

## What Not to Do

Do not read $_SESSION directly in a controller, view, or service.
The Session wrapper exists so that cookie configuration, idle
timeout, and rotation all stay in one place. Bypassing it means
those features can be silently skipped.

Do not store large objects in the session. Sessions live in the
server's file store. A few kilobytes is fine. Serializing a full
model graph is not.

Do not use the session for cross-request caching. That is what
repositories, config, and the file system are for.
