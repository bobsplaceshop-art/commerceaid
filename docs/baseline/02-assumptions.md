# 2. Assumptions vs Reality

Section of: [BASELINE](../BASELINE.md)

Every place where a convention was assumed and later found to be
different. Read this before writing new code. Most bugs we've
shipped trace back to one of these rows.

## The Twenty-One Corrections

### 1. Controller construction

- Assumed: controllers declare `__construct(private readonly FooRepository $foo)`
- Actually: no constructor. Inherit `AdminController::__construct(protected Container $container)`. Resolve deps per method via `$this->container->get(...)`.
- Found in: `ProductController.php` has no constructor; grep for `__construct` in 3 sibling controllers returns 0 matches.

### 2. Route method signature

- Assumed: `(Request $request, int $id)`
- Actually: `(Request $request, array $params = [])`. Params come as associative array of strings. Cast inside: `(int) ($params['id'] ?? 0)`.
- Found in: `Router::invoke()` calls `$controller->{$method}($request, $params)`.

### 3. View path notation

- Assumed: slashes - `'admin/customer_approvals/index'`
- Actually: dot-notation - `'admin.customer_approvals.index'`.
- Found in: `AdminController::render()` docblock: `@param string $view dot-notation view name, e.g. 'admin.dashboard'`.

### 4. Controller instantiation

- Assumed: `new FooController($this->container)`
- Actually: correct - Router does exactly this.
- Found in: `Router::invoke()`: `$controller = new $class($this->container);`.

### 5. Flash reading in controller

- Assumed: flash messages can be read in controller and passed to view.
- Actually: never. Controller writes flash; layout reads and consumes it. Reading in controller swallows the message before layout sees it.
- Found in: `Session::getFlash()` calls `unset()` after read; layout calls global `flash()` which delegates to `getFlash()`.

### 6. Flash rendering in views

- Assumed: views render flash themselves via `$flashSuccess` / `$flashError` vars.
- Actually: layout handles flash globally via `flash('success')` / `flash('error')`. Views never touch flash.
- Found in: `layouts/app.php` lines 18-20, 74-78.

### 7. Partial include notation

- Assumed: `view_partial('admin/foo/_bar')` - path with slash.
- Actually: dot-notation preferred - `view_partial('admin.foo._bar')`. Both forms tolerated by the View class.
- Found in: `products/index.php` line 318 uses slash form; `AdminController::render()` docblock uses dot form.

### 8. Redirect return value

- Assumed: `Response::redirect()` might return nothing.
- Actually: returns `Response`. Always `return Response::redirect(...)`.
- Found in: every controller method.

### 9. Audit middleware

- Assumed: POST routes need `'audit'` in the middleware array.
- Actually: Router injects `audit` automatically for POST/PUT/PATCH/DELETE at outermost position.
- Found in: `Router::dispatch()`: `if (in_array($method, ['POST','PUT','PATCH','DELETE'])) array_unshift($middleware, 'audit');`.

### 10. Route parameter types

- Assumed: route params arrive as native types (int for `{id}`).
- Actually: always strings. Cast in the controller.
- Found in: `Router::compilePattern()` regex `([^/]+)` captures as strings.

### 11. Auth user shape

- Assumed: `Auth::user()` returns something without `.id`.
- Actually: returns `?Platform\Models\User`, and `User::$id` is `public int`. Direct access works.
- Found in: `app/Core/Auth.php:100` and `app/Platform/Models/User.php:11`.

### 12. The word "flash" means two things

- Assumed: session helper `flash()` writes a flash.
- Actually: two distinct things. `Session::flash($key, $value): void` WRITES. Global `flash($key, $default = null): mixed` READS via `getFlash`.
- Found in: `Session.php:120` vs `functions.php:66`.

### 13. hasFlash is non-consuming

- Assumed: `$session->hasFlash($key)` reads.
- Actually: non-destructive check. `getFlash()` is the consuming read.
- Found in: `Session.php:128-131`.

### 14. paginate return shape

- Assumed: repository `paginate()` returns only `items` and `total`.
- Actually: also returns `page`, `per_page`, `pages`. Pass whole shape through as `$result`.
- Found in: `CustomerRepository::paginate()`.

### 15. View layout

- Assumed: views need a layout include or `$this->extends()`.
- Actually: no engine. `AdminController::render()` calls `View::render($view, $data, 'layouts.app')`. Views are pure template fragments.
- Found in: `AdminController::render()`.

### 16. Variable defaults in views

- Assumed: every view can assume all context vars are set.
- Actually: default-guard every optional var at top: `$x = $x ?? default;`.
- Found in: `products/index.php`, `customer_groups/index.php`, `sidebar.php`, `layouts/app.php`.

### 17. Table wrapper class

- Assumed: `.table-responsive` (Bootstrap).
- Actually: custom class `.ra-table-responsive-wrap`. Secondary columns get `.ra-hide-sm`.
- Found in: `products/index.php`, `customer_groups/index.php`; `RESPONSIVENESS.md`.

### 18. Pagination partial

- Assumed: pagination uses a shared partial.
- Actually: inline block copied verbatim into each list view (~30 lines).
- Found in: `products/index.php` lines 273+.

### 19. Confirmation modals

- Assumed: modals go through a JS framework.
- Actually: mix. Destructive actions use either `onclick="return confirm('...')"` (customer groups) or a dedicated partial like `_delete-modal.php` opened via a global JS class (`RA_DeleteProduct.open(...)`).
- Found in: `customer_groups/index.php` inline confirm vs `products/index.php` modal.

### 20. Sidebar structure

- Assumed: sidebar groups require a specific structure to work.
- Actually: `$groups` array with `id`, `label`, `items`. Items: `label`, `href`, `icon`, optional `permission`, optional `locked`. `$isGroupActive()` auto-detects by href prefix.
- Found in: `sidebar.php` full read.

### 21. Prefix-matching active state breaks on strict-prefix routes

- Assumed: `$isActive()` prefix matching works for every nav item.
- Actually: `/commerce/ca-admin` (Dashboard) is a strict prefix of every other admin route. Prefix matching marked Dashboard active on every admin page. This was latent from Stage 005, exposed when scroll-to-active was added in 007B (the scroll picked Dashboard, so the sidebar jumped to top).
- Detection: `document.querySelectorAll('.ra-nav-item.is-active').length` should always be 1 (or 0 on pages not in the nav). If > 1, a strict-prefix route is matching.
- Fix: exact-match special case in `$isActive` for `/commerce/ca-admin`. Any future route that becomes a strict prefix of its siblings needs the same treatment.
- Found in: Stage 007B M3-6i, DOM inspection showed 2 active items on `/commerce/ca-admin/customer-approvals`.
- Full pattern writeup: `docs/REMEMBER.md` Pattern 22.

## Two Most Dangerous Corrections

Items 5, 6 (flash) and 7 (dot-notation view path) - both cause
silent no-op behavior that a linter never catches.

If a controller "runs without error" but the flash message never
appears, or a view "renders fine" but shows nothing, check these
two items first.
