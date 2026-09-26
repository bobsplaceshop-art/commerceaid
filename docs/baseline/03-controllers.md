# 3. Controller Convention

Section of: [BASELINE](../BASELINE.md)

## Template

    <?php
    declare(strict_types=1);

    namespace CorporateAid\Commerce\Controllers\Admin;

    use CorporateAid\Core\Request;
    use CorporateAid\Core\Response;
    use CorporateAid\Core\Session;

    final class FooController extends AdminController
    {
        private const PER_PAGE = 25;

        public function index(Request $request, array $params = []): Response
        {
            $repo    = $this->container->get(FooRepository::class);
            $session = $this->container->get(Session::class);

            $filter = (string) $request->query('status', 'pending');
            $result = $repo->paginate(['status' => $filter], 1, self::PER_PAGE);

            return $this->render('admin.foo.index', [
                'title'         => 'Foo',
                'result'        => $result,
                'currentFilter' => $filter,
            ]);
        }

        public function action(Request $request, array $params = []): Response
        {
            $id      = (int) ($params['id'] ?? 0);
            $repo    = $this->container->get(FooRepository::class);
            $session = $this->container->get(Session::class);

            $ok = $repo->doThing($id);

            if ($ok) {
                $session->flash('success', 'Done.');
            } else {
                $session->flash('error', 'Failed.');
            }

            return Response::redirect('/commerce/ca-admin/foo');
        }
    }

## Rules

1. No constructor. Parent's protected Container $container is the injection point.
2. Every dependency resolved via $this->container->get(...) inside the method.
3. Route handler signature is always (Request $request, array $params = []): Response.
4. Route params: (int) ($params['id'] ?? 0) - always cast from string.
5. return $this->render('admin.foo.bar', [...]) - dot-notation.
6. Flash: write only. Never read in a method that returns a rendered view.
7. return Response::redirect(...) - always return.
8. $data['title'] overrides the shell default 'Admin'. Set it on every render.

## Why No Constructor

The Router does this:

    $controller = new $class($this->container);
    return $controller->{$method}($request, $params);

It passes exactly one argument: the Container. Any controller with a
constructor that expects more arguments, or that fails to call
parent::__construct, will throw at dispatch time.

The parent's constructor is:

    public function __construct(protected Container $container)
    {
    }

Because there is no subclass constructor, the parent's runs
unchanged and $this->container is available in every method.

## Why Params Are Always Strings

The Router compiles {id} to the regex ([^/]+). preg_match captures
are always strings. So $params['id'] is "42", not 42. Casting with
(int) at the point of use is the convention.
