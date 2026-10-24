<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Services\Builder;

use CorporateAid\Commerce\Models\PageBlock;
use CorporateAid\Commerce\Repositories\CategoryRepository;
use CorporateAid\Commerce\Repositories\ProductRepository;
use CorporateAid\Commerce\Support\Blocks\BlockTypeRegistry;
use CorporateAid\Core\View;

/**
 * The single dispatch site for rendering a PageBlock.
 *
 * Every place that renders a block calls render(PageBlock):
 * the storefront home page loop, the admin preview pane,
 * and the future tree renderer (Tier 0.7). One class, one
 * prefetch path, one partial resolution.
 *
 * Two behaviours the pre-renderer code got wrong:
 *
 *   1. The admin preview showed 8 products regardless of
 *      the block's configured limit. The storefront showed
 *      the configured limit. Two paths, two behaviours.
 *
 *   2. The admin preview showed all categories regardless
 *      of the block's category_ids and limit. The storefront
 *      applied both filters. Again, two paths.
 *
 * Both bugs are silently wrong today because the preview
 * is a preview — it looks plausible. This class removes the
 * possibility: prepareViewData() is called from one place,
 * with one behaviour, from every caller.
 *
 * Unknown type → RuntimeException naming the type and
 * block id. Missing partial → RuntimeException wrapping the
 * View's throw, also naming the type and block id. The
 * silent empty-string failure mode that view_partial() has
 * (Pattern 30) does not apply here: render() uses
 * View::render directly, which throws on missing file
 * (read 228: renderFile's require throws, the try/catch
 * rethrows).
 *
 * Stage 023, Tier 0.3.
 */
final class BlockRenderer
{
    public function __construct(
        private View $view,
        private ProductRepository $products,
        private CategoryRepository $categories,
        private \CorporateAid\Commerce\Repositories\ReportRepository $reports,
        private \CorporateAid\Commerce\Repositories\MediaRepository $media,
    ) {}

    public function render(PageBlock $block): string
    {
        if (!BlockTypeRegistry::has($block->blockType)) {
            throw new \RuntimeException(
                "Unknown block type '{$block->blockType}' on block #{$block->id}"
            );
        }

        $partial  = BlockTypeRegistry::partial($block->blockType);
        $viewData = $this->prepareViewData($block);

        // Tree-aware render. Child-capable block types (Section,
        // Container — added in Tier 0.8) receive the rendered
        // HTML of their children in $viewData['children_html'].
        // The partial writes it wherever children belong
        // inside its own markup.
        //
        // Leaf types (accepts_children false) never see this
        // key; the partial does not reference it. That is the
        // current state of all eight types. Section and
        // Container are the first to set accepts_children true.
        // The renderer is ready before the type is.
        if (BlockTypeRegistry::acceptsChildren($block->blockType)) {
            $childrenHtml = '';
            foreach ($block->children as $child) {
                $childrenHtml .= $this->render($child);
            }
            $viewData['children_html'] = $childrenHtml;
        }

        try {
            return $this->view->render($partial, $viewData);
        } catch (\Throwable $e) {
            throw new \RuntimeException(
                "Failed to render block #{$block->id} "
                . "(type '{$block->blockType}', partial '{$partial}'): "
                . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Build the view data a partial needs.
     *
     * This method used to exist in two places (home.php's
     * inline block and AdminPageController::renderBlockPartial).
     * They disagreed: home.php used the block's configured
     * limit for products and applied the category filter,
     * while the admin preview hardcoded 8 and skipped the
     * filter entirely. That is the exact class of bug the
     * single dispatch site eliminates.
     *
     * @return array<string,mixed>
     */
    private function prepareViewData(PageBlock $block): array
    {
        $viewData = ['config' => $block->config];

        // Resolve numeric file ids to UUID-based URLs for any
        // block that renders a single background/image file.
        // The partial receives a ready-to-use bg_url.
        if (!empty($block->config['image_file_id'])) {
            $mediaItem = $this->media->findById((int) $block->config['image_file_id']);
            if ($mediaItem !== null) {
                $viewData['bg_url'] = $mediaItem->variantUrl('lg', 'jpg');
            }
        }

        if ($block->blockType === 'product-grid') {
            $limit = max(1, (int) ($block->config['limit'] ?? 4));
            $source = $block->config['source'] ?? 'newest';

            $categoryId = null;
            if (!empty($block->config['category_slug'])) {
                $cat = $this->categories->findBySlug((string) $block->config['category_slug']);
                if ($cat !== null) {
                    $categoryId = (int) $cat->id;
                } else {
                    // Slug doesn't exist (deleted). Force empty result.
                    $categoryId = -1;
                }
            }

            if ($source === 'best_sellers') {
                if ($categoryId === -1) {
                    $viewData['products'] = [];
                } else {
                    $viewData['products'] = $this->reports->listBestSellers($categoryId, $limit);
                }
            } else {
                $filters = ['sort' => $source];
                if ($categoryId !== null) {
                    $filters['category_id'] = $categoryId;
                }
                $result = $this->products->paginatePublished($filters, 1, $limit);
                $viewData['products'] = $result['items'];
            }
        } elseif ($block->blockType === 'category-grid') {
            $all = $this->categories->listAll(false);

            // Filter to selected categories when set.
            // Empty selection means "all, in sort_order" —
            // listAll() returns them ordered by the
            // merchant's manual sort.
            $selectedIds = (array) ($block->config['category_ids'] ?? []);
            if ($selectedIds !== []) {
                $selectedIds = array_map('intval', $selectedIds);
                $all = array_values(array_filter(
                    $all,
                    static fn ($c) => in_array((int) $c->id, $selectedIds, true)
                ));
            }

            $limit = max(1, min(12, (int) ($block->config['limit'] ?? 5)));
            $viewData['categories'] = array_slice($all, 0, $limit);
        }

        return $viewData;
    }
}
