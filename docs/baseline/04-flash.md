# 4. Flash Flow

Section of: [BASELINE](../BASELINE.md)

## The Flow

Controllers write flash messages. The layout reads them. Never the
other way around.

    Controller action                    Layout render
    -----------------                    -------------
    $session->flash('success', $msg)
       |
    $_SESSION['_flash']['success'] = $msg
       |
       |                                 flash('success')  <- global helper
       |                                    |
       |                                    v
       |                                 Session::getFlash('success')
       |                                    |
       |                                    v
       |                                 reads value, unsets it
       |                                    |
       |                                    v
       |                                 renders <div class="ra-alert ra-alert-success">

## Rules

1. Controllers write. Layout reads. Never the reverse.
2. Reading flash in a controller consumes it. The layout never sees
   the message.
3. The global flash() helper is safe to call from any view. It never
   throws.
4. hasFlash() is a non-destructive check if you need to peek without
   consuming.

## The Two Symbols Named Flash

There are two distinct things with the name flash in this codebase.
Confusing them is easy.

Session::flash($key, $value): void
  A method on the Session class. WRITES a flash value into
  $_SESSION['_flash'][$key]. Takes two arguments.

flash($key, $default = null): mixed
  A global helper function defined in app/Helpers/functions.php.
  READS a flash value via Session::getFlash(). Takes one argument
  plus a default. This is what the layout uses.

The global helper is a thin wrapper:

    function flash(string $key, mixed $default = null): mixed
    {
        try {
            return App::instance()
                ->container()
                ->get(Session::class)
                ->getFlash($key, $default);
        } catch (\Throwable) {
            return $default;
        }
    }

Session::getFlash() consumes on read:

    public function getFlash(string $key, mixed $default = null): mixed
    {
        if (!isset($_SESSION['_flash'][$key])) {
            return $default;
        }
        $value = $_SESSION['_flash'][$key];
        unset($_SESSION['_flash'][$key]);
        return $value;
    }

That unset() is why reading flash in a controller kills the message
for the layout.

## Common Mistake

Wrong - consumes the flash before the layout can read it:

    public function index(Request $request, array $params = []): Response
    {
        $session = $this->container->get(Session::class);
        return $this->render('admin.foo.index', [
            'flashSuccess' => $session->getFlash('success'),
        ]);
    }

Right - just render. The layout handles flash globally:

    public function index(Request $request, array $params = []): Response
    {
        return $this->render('admin.foo.index', [
            'title' => 'Foo',
        ]);
    }

## Where the Layout Reads Flash

In resources/views/layouts/app.php, lines 18 to 20:

    $flashSuccess = function_exists('flash') ? flash('success') : null;
    $flashError   = function_exists('flash') ? flash('error')   : null;
    $hasFlash     = ($flashSuccess !== null || $flashError !== null);

And lines 74 to 78 render them:

    <?php if ($flashSuccess !== null): ?>
        <div class="ra-alert ra-alert-success"><?= e((string) $flashSuccess) ?></div>
    <?php endif; ?>
    <?php if ($flashError !== null): ?>
        <div class="ra-alert ra-alert-error"><?= e((string) $flashError) ?></div>
    <?php endif; ?>

That is the entire flash surface. Every admin page inherits it. No
view ever needs to render flash itself.
