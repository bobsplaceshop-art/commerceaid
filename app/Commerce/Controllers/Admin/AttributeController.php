<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Controllers\Admin;

use CorporateAid\Core\Database;
use CorporateAid\Core\Request;
use CorporateAid\Core\Response;
use CorporateAid\Core\Session;
use CorporateAid\Commerce\Support\Validator;

/**
 * Attribute admin CRUD.
 *
 * Attributes are the vocabulary that powers variants: Color, Size,
 * Material. Each attribute has many values (Red, Blue / M, L / etc.).
 *
 * IMPORTANT: Attribute definitions are store-independent (they live
 * in `commerce_attribute_definitions` with no store_id). This is by
 * design — Color is Color across every store. What differs per store
 * is which attributes a specific product uses.
 *
 * Routes (under /commerce/ca-admin/attributes):
 *   GET    /                    list definitions + their values
 *   GET    /create              create form
 *   POST   /                    store
 *   GET    /{id}/edit           edit form (definition + values)
 *   POST   /{id}                update definition
 *   POST   /{id}/values         add a value
 *   POST   /{id}/values/{valueId}/delete   remove a value
 *   POST   /{id}/delete         delete definition
 *   GET    /{id}/delete-info    JSON for confirm modal
 */
final class AttributeController extends AdminController
{
    // =================================================================
    // Reads
    // =================================================================

    public function index(Request $request, array $params = []): Response
    {
        $defs = $this->definitions();

        // Attach value counts
        foreach ($defs as &$def) {
            $def['values'] = $this->valuesFor((int) $def['id']);
        }
        unset($def);

        return $this->render('admin.attributes.index', [
            'title'      => 'Attributes',
            'attributes' => $defs,
        ]);
    }

    public function showCreate(Request $request, array $params = []): Response
    {
        $session = $this->container->get(Session::class);

        return $this->render('admin.attributes.edit', [
            'title'     => 'New attribute',
            'attribute' => null,
            'values'    => [],
            'errors'    => $session->getFlash('attribute_errors', []),
            'old'       => $_SESSION['_old'] ?? [],
            'mode'      => 'create',
        ]);
    }

    public function showEdit(Request $request, array $params = []): Response
    {
        $session = $this->container->get(Session::class);
        $id      = (int) ($params['id'] ?? 0);

        $attribute = $this->findDefinition($id);
        if ($attribute === null) {
            return Response::redirect('/commerce/ca-admin/attributes');
        }

        return $this->render('admin.attributes.edit', [
            'title'     => 'Edit: ' . $attribute['name'],
            'attribute' => $attribute,
            'values'    => $this->valuesFor($id),
            'errors'    => $session->getFlash('attribute_errors', []),
            'old'       => $_SESSION['_old'] ?? [],
            'mode'      => 'edit',
        ]);
    }

    // =================================================================
    // Writes — definition
    // =================================================================

    public function store(Request $request, array $params = []): Response
    {
        $session = $this->container->get(Session::class);
        $db      = $this->container->get(Database::class);

        $data   = $this->collectInput($request);
        $errors = $this->validateDefinition($data, null);

        if ($errors !== []) {
            $session->flash('attribute_errors', $errors);
            $session->flashOldInput($data);
            return Response::redirect('/commerce/ca-admin/attributes/create');
        }

        $db->execute(
            "INSERT INTO `commerce_attribute_definitions`
                (`slug`, `name`, `input_type`, `is_variant_axis`, `sort_order`)
             VALUES (?, ?, ?, ?, ?)",
            [
                $data['slug'],
                $data['name'],
                $data['input_type'],
                $data['is_variant_axis'] ? 1 : 0,
                $data['sort_order'],
            ]
        );

        $session->flash('success', 'Attribute created.');
        $session->clearOldInput();
        return Response::redirect('/commerce/ca-admin/attributes');
    }

    public function update(Request $request, array $params = []): Response
    {
        $session = $this->container->get(Session::class);
        $db      = $this->container->get(Database::class);
        $id      = (int) ($params['id'] ?? 0);

        $existing = $this->findDefinition($id);
        if ($existing === null) {
            return Response::redirect('/commerce/ca-admin/attributes');
        }

        $data   = $this->collectInput($request);
        $errors = $this->validateDefinition($data, $id);

        if ($errors !== []) {
            $session->flash('attribute_errors', $errors);
            $session->flashOldInput($data);
            return Response::redirect('/commerce/ca-admin/attributes/' . $id . '/edit');
        }

        $db->execute(
            "UPDATE `commerce_attribute_definitions`
                SET `slug` = ?, `name` = ?, `input_type` = ?,
                    `is_variant_axis` = ?, `sort_order` = ?
              WHERE `id` = ?",
            [
                $data['slug'],
                $data['name'],
                $data['input_type'],
                $data['is_variant_axis'] ? 1 : 0,
                $data['sort_order'],
                $id,
            ]
        );

        $session->flash('success', 'Attribute updated.');
        $session->clearOldInput();
        return Response::redirect('/commerce/ca-admin/attributes/' . $id . '/edit');
    }

    // =================================================================
    // Writes — values
    // =================================================================

    public function addValue(Request $request, array $params = []): Response
    {
        $session = $this->container->get(Session::class);
        $db      = $this->container->get(Database::class);
        $id      = (int) ($params['id'] ?? 0);

        $attribute = $this->findDefinition($id);
        if ($attribute === null) {
            return Response::redirect('/commerce/ca-admin/attributes');
        }

        $value = trim((string) $request->input('value', ''));
        $slug  = trim((string) $request->input('slug', ''));
        if ($slug === '') {
            $slug = $this->slugify($value);
        }
        $colorHex = trim((string) $request->input('color_hex', ''));

        $errors = [];

        if ($value === '') {
            $errors['value'] = 'Value is required.';
        } elseif (($e = Validator::maxLength($value, 120, 'Value'))) {
            $errors['value'] = $e;
        }

        if ($slug === '') {
            $errors['slug'] = 'Slug could not be generated.';
        } elseif (($e = Validator::slug($slug, 80, 'Slug'))) {
            $errors['slug'] = $e;
        } elseif ($this->valueSlugExists($id, $slug)) {
            $errors['slug'] = 'This value already exists for this attribute.';
        }

        if ($colorHex !== '' && ($e = Validator::hexColor($colorHex, 'Color'))) {
            $errors['color_hex'] = $e;
        }

        if ($errors !== []) {
            $session->flash('attribute_errors', $errors);
            $session->flashOldInput(['value' => $value, 'slug' => $slug, 'color_hex' => $colorHex]);
            return Response::redirect('/commerce/ca-admin/attributes/' . $id . '/edit');
        }

        $db->execute(
            "INSERT INTO `commerce_attribute_values`
                (`attribute_id`, `slug`, `value`, `color_hex`, `sort_order`)
             VALUES (?, ?, ?, ?, ?)",
            [
                $id,
                $slug,
                $value,
                $colorHex !== '' ? $colorHex : null,
                $this->nextValueSortOrder($id),
            ]
        );

        $session->flash('success', 'Value added.');
        $session->clearOldInput();
        return Response::redirect('/commerce/ca-admin/attributes/' . $id . '/edit');
    }

    public function removeValue(Request $request, array $params = []): Response
    {
        $db         = $this->container->get(Database::class);
        $id         = (int) ($params['id'] ?? 0);
        $valueId    = (int) ($params['valueId'] ?? 0);
        $session    = $this->container->get(Session::class);

        // Guard: cannot remove a value that's in use by any product
        // or variant.
        $inUse = (int) $db->scalar(
            "SELECT COUNT(*) FROM `commerce_product_attribute_values` WHERE `attribute_value_id` = ?",
            [$valueId]
        ) + (int) $db->scalar(
            "SELECT COUNT(*) FROM `commerce_variant_attribute_values` WHERE `attribute_value_id` = ?",
            [$valueId]
        );

        if ($inUse > 0) {
            $session->flash('error', 'Cannot remove this value — it is used by ' . $inUse . ' product(s) or variant(s).');
            return Response::redirect('/commerce/ca-admin/attributes/' . $id . '/edit');
        }

        $db->execute(
            "DELETE FROM `commerce_attribute_values`
              WHERE `id` = ? AND `attribute_id` = ?",
            [$valueId, $id]
        );

        $session->flash('success', 'Value removed.');
        return Response::redirect('/commerce/ca-admin/attributes/' . $id . '/edit');
    }

    // =================================================================
    // Writes — definition lifecycle
    // =================================================================

    public function destroy(Request $request, array $params = []): Response
    {
        $db      = $this->container->get(Database::class);
        $id      = (int) ($params['id'] ?? 0);
        $session = $this->container->get(Session::class);

        // Guard: cannot delete a definition that any product uses
        $inUse = (int) $db->scalar(
            "SELECT COUNT(*) FROM `commerce_product_attribute_values` WHERE `attribute_id` = ?",
            [$id]
        );

        if ($inUse > 0) {
            $session->flash('error', 'Cannot delete this attribute — it is used by ' . $inUse . ' product(s).');
            return Response::redirect('/commerce/ca-admin/attributes');
        }

        $db->execute("DELETE FROM `commerce_attribute_definitions` WHERE `id` = ?", [$id]);
        $session->flash('success', 'Attribute deleted.');
        return Response::redirect('/commerce/ca-admin/attributes');
    }

    public function deleteInfo(Request $request, array $params = []): Response
    {
        $db = $this->container->get(Database::class);
        $id = (int) ($params['id'] ?? 0);

        $attribute = $this->findDefinition($id);
        if ($attribute === null) {
            return Response::json(['error' => 'Attribute not found'], 404);
        }

        $usage = (int) $db->scalar(
            "SELECT COUNT(*) FROM `commerce_product_attribute_values` WHERE `attribute_id` = ?",
            [$id]
        );
        $valueCount = count($this->valuesFor($id));

        return Response::json([
            'name'          => $attribute['name'],
            'usage_count'   => $usage,
            'value_count'   => $valueCount,
        ]);
    }

    // =================================================================
    // Helpers
    // =================================================================

    /** @return array<int, array<string,mixed>> */
    private function definitions(): array
    {
        return $this->container->get(Database::class)
            ->select("SELECT * FROM `commerce_attribute_definitions`
                       ORDER BY `sort_order` ASC, `name` ASC");
    }

    /** @return array<string,mixed>|null */
    private function findDefinition(int $id): ?array
    {
        return $this->container->get(Database::class)->selectOne(
            "SELECT * FROM `commerce_attribute_definitions` WHERE `id` = ? LIMIT 1",
            [$id]
        );
    }

    /** @return array<int, array<string,mixed>> */
    private function valuesFor(int $attributeId): array
    {
        return $this->container->get(Database::class)->select(
            "SELECT * FROM `commerce_attribute_values`
              WHERE `attribute_id` = ?
              ORDER BY `sort_order` ASC, `value` ASC",
            [$attributeId]
        );
    }

    private function valueSlugExists(int $attributeId, string $slug): bool
    {
        $count = (int) $this->container->get(Database::class)->scalar(
            "SELECT COUNT(*) FROM `commerce_attribute_values`
              WHERE `attribute_id` = ? AND `slug` = ?",
            [$attributeId, $slug]
        );
        return $count > 0;
    }

    private function nextValueSortOrder(int $attributeId): int
    {
        $max = (int) $this->container->get(Database::class)->scalar(
            "SELECT COALESCE(MAX(`sort_order`), 0) FROM `commerce_attribute_values`
              WHERE `attribute_id` = ?",
            [$attributeId]
        );
        return $max + 1;
    }

    /** @return array<string,mixed> */
    private function collectInput(Request $request): array
    {
        $slug = (string) $request->input('slug', '');
        if ($slug === '') {
            $slug = $this->slugify((string) $request->input('name', ''));
        }

        return [
            'name'            => trim((string) $request->input('name', '')),
            'slug'            => strtolower(trim($slug)),
            'input_type'      => (string) $request->input('input_type', 'select'),
            'is_variant_axis' => (bool) $request->input('is_variant_axis', false),
            'sort_order'      => max(0, (int) $request->input('sort_order', 0)),
        ];
    }

    /** @return array<string,string> */
    private function validateDefinition(array $data, ?int $excludeId): array
    {
        $errors = [];

        if ($e = Validator::required($data['name'], 'Name')) {
            $errors['name'] = $e;
        } elseif ($e = Validator::maxLength($data['name'], 100, 'Name')) {
            $errors['name'] = $e;
        }

        if ($data['slug'] === '') {
            $errors['slug'] = 'Slug is required.';
        } elseif ($e = Validator::slug($data['slug'], 60, 'Slug')) {
            $errors['slug'] = $e;
        } elseif ($this->slugExists($data['slug'], $excludeId)) {
            $errors['slug'] = 'This slug is already used by another attribute.';
        }

        if (!in_array($data['input_type'], ['select', 'color', 'text', 'number'], true)) {
            $errors['input_type'] = 'Invalid input type.';
        }

        return $errors;
    }

    private function slugExists(string $slug, ?int $excludeId): bool
    {
        $sql = "SELECT COUNT(*) FROM `commerce_attribute_definitions` WHERE `slug` = ?";
        $params = [$slug];

        if ($excludeId !== null) {
            $sql .= " AND `id` <> ?";
            $params[] = $excludeId;
        }

        return (int) $this->container->get(Database::class)->scalar($sql, $params) > 0;
    }

    private function slugify(string $text): string
    {
        $text = strtolower(trim($text));
        $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';
        return trim($text, '-');
    }
}
