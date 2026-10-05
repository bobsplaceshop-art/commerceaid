<?php
/**
 * Catalog toolbar: result count + sort dropdown.
 *
 * Shared between the /products catalog page and the
 * /categories/{slug} category detail page.
 *
 * Callers pass:
 *   $result          array{items, total, page, per_page, pages}
 *   $currentFilters  array<string,string>   must contain 'sort'
 *   $sortOptions     array<string,string>   sort key => label
 *   $formId          string                 the filter form's id
 *
 * The sort <select> is bound to the filter form via the
 * HTML5 form attribute. Changing it submits the form,
 * which re-reads every filter and preserves them.
 *
 * Renders nothing when the result set is empty. An empty
 * page has no sort to apply.
 */

$result         = $result         ?? ['items' => [], 'total' => 0];
$currentFilters = $currentFilters ?? [];
$sortOptions    = $sortOptions    ?? ['newest' => 'Newest'];
$formId         = $formId         ?? 'sf-catalog-filter-form';

$items = $result['items'] ?? [];
$total = (int) ($result['total'] ?? 0);

if ($total <= 0) {
    return;
}
?>

<div class="sf-catalog-toolbar" data-sf-catalog-block="sort">
    <span class="sf-catalog-count-inline">
        Showing <?= count($items) ?> of <?= $total ?>
    </span>
    <div class="sf-catalog-sort">
        <label for="sf-sort" class="sf-filter-label">Sort by</label>
        <select id="sf-sort"
                name="sort"
                class="sf-filter-input"
                form="<?= e($formId) ?>"
                onchange="document.getElementById('<?= e($formId) ?>').submit()">
            <?php foreach ($sortOptions as $key => $label): ?>
                <option value="<?= e($key) ?>"
                    <?= ($currentFilters['sort'] ?? 'newest') === $key ? 'selected' : '' ?>>
                    <?= e($label) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
</div>
