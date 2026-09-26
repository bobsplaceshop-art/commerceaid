# 8. Repository Pattern

Section of: [BASELINE](../BASELINE.md)

## Template

    final class FooRepository extends CommerceRepository
    {
        public function paginate(array $filters = [], int $page = 1, int $perPage = 25): array
        {
            $page    = max(1, $page);
            $perPage = min(100, max(1, $perPage));
            $offset  = ($page - 1) * $perPage;

            [$where, $params] = $this->buildFilters($filters);

            $total = (int) $this->db->scalar(
                "SELECT COUNT(*) FROM `commerce_foos` WHERE {$where}",
                $params
            );

            $rows = $this->db->select(
                "SELECT * FROM `commerce_foos`
                  WHERE {$where}
                  ORDER BY `created_at` DESC
                  LIMIT {$perPage} OFFSET {$offset}",
                $params
            );

            return [
                'items'    => array_map(fn ($r) => Foo::fromRow($r), $rows),
                'total'    => $total,
                'page'     => $page,
                'per_page' => $perPage,
                'pages'    => max(1, (int) ceil($total / $perPage)),
            ];
        }

        public function findById(int $id): ?Foo { }

        public function count(): int
        {
            return (int) $this->db->scalar(
                "SELECT COUNT(*) FROM `commerce_foos` WHERE `store_id` = ?",
                [$this->storeId()]
            );
        }

        public function create(Foo $foo): int { }

        public function update(Foo $foo): void { }

        private function buildFilters(array $filters): array
        {
            $where  = ["`store_id` = ?"];
            $params = [$this->storeId()];
            return [implode(' AND ', $where), $params];
        }
    }

## Rules

1. Every repository extends CommerceRepository.
2. Every query filters by $this->storeId(). No exceptions.
3. $this->storeId() throws StoreContextMissingException if no store
   has been resolved for the current request. This is intentional
   defense-in-depth for tenant isolation.
4. find* methods return ?Model or a typed array. Never raw rows.
5. paginate() returns the standard shape. Pass it through to the view
   as $result.
6. buildFilters() is a private helper for filter construction. It
   returns [where-string, params-array].

## Method Categories

Reads:

    paginate($filters, $page, $perPage) : array
    findById($id) : ?Model
    findBySlug($slug) : ?Model
    findByEmail($email) : ?Model
    count() : int
    countPending() : int
    list*() : array<int, Model>

Writes:

    create(Model $m) : int          -- returns inserted id
    update(Model $m) : void
    archive($id) : bool
    restore($id) : bool
    softDelete($id) : bool

Domain writes (approval, publishing, etc.):

    approve($id, $userId) : bool
    reject($id, $userId, $reason) : bool
    resetToPending($id) : bool

Each domain write is idempotent. The WHERE clause includes a guard
like "AND approval_status <> 'approved'" so re-applying the same
state is a no-op and returns false (affected rows = 0).

## Binding in the Provider

Repositories are registered as singletons in
CommerceServiceProvider::register():

    $c->singleton(FooRepository::class,
        fn() => new FooRepository(
            $c->get(Database::class),
            $c->get(StoreContext::class)
        ));

Store-independent repositories (those that query global vocabulary
like attribute definitions) skip the StoreContext:

    $c->singleton(AttributeRepository::class,
        fn() => new AttributeRepository($c->get(Database::class)));

## The Base Class

CommerceRepository provides:

    protected Database $db;
    protected StoreContext $context;

    protected function storeId(): int
    {
        // throws StoreContextMissingException if not resolved
    }

Subclasses never call the Database class directly. They call
$this->db->select(), ->selectOne(), ->scalar(), ->execute(),
->insert().

## The StoreContext Rule

In a web request, StoreResolutionMiddleware populates the
StoreContext before any controller runs. Every repository query
immediately after has access.

In a CLI script (seeder, cron, smoke test), there is no middleware
pipeline. You must set the context manually:

    $container->get(StoreContext::class)->set($storeId, $storeSlug, 'cli');

Otherwise every repository method that touches the DB throws
"Store has not been resolved for this request".

That exception is not a bug. It is the guarantee that no query can
accidentally return data from the wrong store.

## Filter Construction

The buildFilters() helper pattern:

    private function buildFilters(array $filters): array
    {
        $where  = ["c.`store_id` = ?"];
        $params = [$this->storeId()];

        if (!empty($filters['q'])) {
            $like = '%' . $filters['q'] . '%';
            $where[]  = "(c.`name` LIKE ? OR c.`sku` LIKE ?)";
            $params[] = $like;
            $params[] = $like;
        }

        if (!empty($filters['status'])) {
            $where[]  = "c.`status` = ?";
            $params[] = (string) $filters['status'];
        }

        return [implode(' AND ', $where), $params];
    }

Every filter is opt-in. Empty filters produce a WHERE clause with
only the store_id guard. No filter builds up into a query that
returns nothing unintended.

## Adding a New Repository

1. Create the class in app/Commerce/Repositories/.
2. Extend CommerceRepository.
3. Add one use statement and one $c->singleton(...) block in
   CommerceServiceProvider::register().
4. No changes to App.php, routes, or anything else.
