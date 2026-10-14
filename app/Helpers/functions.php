<?php
declare(strict_types=1);

/**
 * Anaya Global - global helper functions.
 * Loaded automatically by Composer's autoloader.
 */

if (!function_exists('e')) {
    function e(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('config')) {
    function config(string $key, mixed $default = null): mixed
    {
        return \CorporateAid\Core\App::instance()->container()->get('config')->get($key, $default);
    }
}

if (!function_exists('base_path')) {
    function base_path(string $suffix = ''): string
    {
        return \CorporateAid\Core\App::instance()->basePath($suffix);
    }
}

if (!function_exists('asset')) {
    function asset(string $path): string
    {
        return '/assets/' . ltrim($path, '/');
    }
}

if (!function_exists('public_path')) {
    /**
     * Absolute filesystem path to the public directory, or a file
     * within it. Used by versioned_asset() to stat the file for
     * its modification time.
     */
    function public_path(string $suffix = ''): string
    {
        $base = \CorporateAid\Core\App::instance()->basePath('public');
        return $suffix !== ''
            ? $base . '/' . ltrim($suffix, '/')
            : $base;
    }
}

if (!function_exists('versioned_asset')) {
    /**
     * Same as asset(), but appends a ?v=<mtime> query string so
     * browsers refetch when the file changes on disk.
     *
     * Without this, CSS and JS files are cached aggressively and
     * browser hard-refresh is unreliable (Chrome's ETag cache can
     * serve stale content even after Ctrl+Shift+R). With it, every
     * file modification changes the URL, and the browser has no
     * choice but to fetch.
     *
     * Falls back to the plain asset URL if the file does not
     * exist (so a typo in a view doesn't fatal - it just renders
     * a 404 for that asset, which the dev overlay would catch).
     */
    function versioned_asset(string $path): string
    {
        $url = asset($path);
        try {
            $file = public_path('assets/' . ltrim($path, '/'));
            if (is_file($file)) {
                $url .= '?v=' . filemtime($file);
            }
        } catch (\Throwable) {
            // App not booted (rare). Fall through with unversioned URL.
        }
        return $url;
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        try {
            return \CorporateAid\Core\App::instance()
                ->container()
                ->get(\CorporateAid\Core\Csrf::class)
                ->token();
        } catch (\Throwable) {
            return '';
        }
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
    }
}

if (!function_exists('method_field')) {
    function method_field(string $method): string
    {
        return '<input type="hidden" name="_method" value="' . e(strtoupper($method)) . '">';
    }
}

if (!function_exists('recently_viewed_product_ids')) {
    /**
     * The current viewer's recently-viewed product IDs.
     *
     * Resolved once per request and cached in a static,
     * mirroring wishlist_product_ids(). Used by views that
     * want to render a "recently viewed" strip or highlight
     * already-viewed products.
     *
     * Guests read from their customer session history.
     * Logged-in customers read from commerce_recently_viewed.
     *
     * Returns an empty array on any failure.
     *
     * @return array<int, int>
     */
    function recently_viewed_product_ids(): array
    {
        /** @var array<int, int>|null $cache */
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }

        try {
            $svc = \CorporateAid\Core\App::instance()
                ->container()
                ->get(\CorporateAid\Commerce\Services\RecentViewService::class);
            $cache = $svc->productIds();
        } catch (\Throwable) {
            $cache = [];
        }

        return $cache;
    }
}

if (!function_exists('customer_recent_messages')) {
    /**
     * Recent unread messages for the current customer, newest
     * first. Used by the header bell dropdown to show a quick
     * snapshot without leaving the current page.
     *
     * Returns an empty array for guests, and an empty array
     * when the customer has no unread messages.
     *
     * Static-cached per request. The header renders on every
     * storefront page.
     *
     * Each item is a small associative array:
     *   [
     *     'id'        => int,
     *     'title'     => string,
     *     'category'  => 'system' | 'order' | 'promotion',
     *     'icon'      => string   Bootstrap icon class,
     *     'time'      => string   relative time,
     *     'link'      => ?string  optional URL,
     *   ]
     *
     * Using a plain array rather than the full
     * CustomerNotification model keeps the header light and
     * avoids coupling the view to the model.
     *
     * @return array<int, array{id:int,title:string,category:string,icon:string,time:string,link:?string}>
     */
    function customer_recent_messages(int $limit = 5): array
    {
        /** @var array<int, array<string,mixed>>|null $cache */
        static $cache = null;
        static $resolved = false;
        static $cachedLimit = 5;

        // If the limit changes between calls on the same
        // request, do not reuse a stale cache.
        if ($resolved && $cachedLimit === $limit) {
            /** @var array<int, array{id:int,title:string,category:string,icon:string,time:string,link:?string}> $c */
            $c = $cache ?? [];
            return $c;
        }

        try {
            $container = \CorporateAid\Core\App::instance()->container();
            $customerAuth = $container->get(
                \CorporateAid\Commerce\Services\CustomerAuthService::class
            );
            $customer = $customerAuth->user();
            if ($customer === null) {
                $cache = [];
                $resolved = true;
                $cachedLimit = $limit;
                return [];
            }

            $svc = $container->get(
                \CorporateAid\Commerce\Services\CustomerNotificationService::class
            );
            $list = $svc->unreadFor($customer->id, $limit);

            $out = [];
            foreach ($list as $n) {
                /** @var array{id:int,title:string,category:string,icon:string,time:string,link:?string} $row */
                $row = [
                    'id'       => (int) $n->id,
                    'title'    => (string) $n->title,
                    'category' => (string) $n->category,
                    'icon'     => (string) $n->icon(),
                    'time'     => (string) $n->relativeTime(),
                    'link'     => $n->link !== null ? (string) $n->link : null,
                ];
                $out[] = $row;
            }

            /** @var array<int, array{id:int,title:string,category:string,icon:string,time:string,link:?string}> $out */
            $cache = $out;
            $resolved = true;
            $cachedLimit = $limit;
            return $out;
        } catch (\Throwable) {
            $cache = [];
            $resolved = true;
            $cachedLimit = $limit;
            return [];
        }
    }
}

if (!function_exists('store_logo_url')) {
    /**
     * The public URL for the current store's uploaded logo, or
     * null if the store has no logo set.
     *
     * Accepts an optional Store argument. When omitted, resolves
     * the current store from the container. Useful from the
     * storefront layout where $store is already in scope, and
     * from anywhere else that needs the same value.
     *
     * Static-cached per (store id, variant) pair. The same store
     * is typically asked for the same variant twice on a page
     * (header and manifest), and the cache means we hit the DB
     * once.
     *
     * Defensive: any failure returns null. A broken media read
     * should never break a storefront page.
     */
    function store_logo_url(
        ?\CorporateAid\Commerce\Models\Store $store = null,
        string $variant = 'lg',
        string $format = 'png'
    ): ?string {
        static $cache = [];

        try {
            if ($store === null) {
                $container = \CorporateAid\Core\App::instance()->container();
                $context = $container->get(
                    \CorporateAid\Commerce\Context\StoreContext::class
                );
                if (!$context->has()) {
                    return null;
                }
                $repo = $container->get(
                    \CorporateAid\Commerce\Repositories\StoreRepository::class
                );
                $store = $repo->findById($context->id());
                if ($store === null) {
                    return null;
                }
            }

            $fileId = (int) ($store->logoFileId ?? 0);
            if ($fileId <= 0) {
                return null;
            }

            $key = $fileId . ':' . $variant . ':' . $format;
            if (array_key_exists($key, $cache)) {
                return $cache[$key];
            }

            $container = $container ?? \CorporateAid\Core\App::instance()->container();
            $media = $container->get(
                \CorporateAid\Commerce\Repositories\MediaRepository::class
            );
            $item = $media->findById($fileId);
            if ($item === null) {
                $cache[$key] = null;
                return null;
            }

            $url = $item->variantUrl($variant, $format);
            $cache[$key] = $url;
            return $url;
        } catch (\Throwable) {
            return null;
        }
    }
}

if (!function_exists('store_favicon_url')) {
    /**
     * The public URL for the current store's uploaded favicon,
     * or null if not set. Same shape as store_logo_url().
     */
    function store_favicon_url(
        ?\CorporateAid\Commerce\Models\Store $store = null,
        string $variant = 'lg',
        string $format = 'png'
    ): ?string {
        static $cache = [];

        try {
            if ($store === null) {
                $container = \CorporateAid\Core\App::instance()->container();
                $context = $container->get(
                    \CorporateAid\Commerce\Context\StoreContext::class
                );
                if (!$context->has()) {
                    return null;
                }
                $repo = $container->get(
                    \CorporateAid\Commerce\Repositories\StoreRepository::class
                );
                $store = $repo->findById($context->id());
                if ($store === null) {
                    return null;
                }
            }

            $fileId = (int) ($store->faviconFileId ?? 0);
            if ($fileId <= 0) {
                return null;
            }

            $key = $fileId . ':' . $variant . ':' . $format;
            if (array_key_exists($key, $cache)) {
                return $cache[$key];
            }

            $container = $container ?? \CorporateAid\Core\App::instance()->container();
            $media = $container->get(
                \CorporateAid\Commerce\Repositories\MediaRepository::class
            );
            $item = $media->findById($fileId);
            if ($item === null) {
                $cache[$key] = null;
                return null;
            }

            $url = $item->variantUrl($variant, $format);
            $cache[$key] = $url;
            return $url;
        } catch (\Throwable) {
            return null;
        }
    }
}

if (!function_exists('product_image_url')) {
    /**
     * The public URL for a product's primary image, or null
     * when the product has no images.
     *
     * Accepts an optional Product argument. When omitted,
     * resolves nothing (returns null) - the caller must pass
     * a Product in scope. Every storefront view that renders
     * a product has it.
     *
     * Variant and format default to the product card shape:
     * 400x500 WebP. Callers that render a different size
     * (product detail, cart line, thumbnail) pass their own.
     *
     * Static-cached per (product id, variant, format). The
     * same product appears multiple times on a page and the
     * cache means we hit the DB once.
     *
     * Defensive: any failure returns null. A broken media read
     * should never break a storefront page. Callers fall back
     * to a picsum placeholder when this returns null.
     */
    function product_image_url(
        ?\CorporateAid\Commerce\Models\Product $product = null,
        string $variant = 'card',
        string $format = 'webp'
    ): ?string {
        static $cache = [];

        if ($product === null || $product->id <= 0) {
            return null;
        }

        $key = $product->id . ':' . $variant . ':' . $format;
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }

        try {
            $container = \CorporateAid\Core\App::instance()->container();

            $imageRepo = $container->get(
                \CorporateAid\Commerce\Repositories\ProductImageRepository::class
            );
            $primary = $imageRepo->primaryFor((int) $product->id);
            if ($primary === null) {
                $cache[$key] = null;
                return null;
            }

            $media = $container->get(
                \CorporateAid\Commerce\Repositories\MediaRepository::class
            );
            $item = $media->findById($primary->fileId);
            if ($item === null) {
                $cache[$key] = null;
                return null;
            }

            $url = $item->variantUrl($variant, $format);
            $cache[$key] = $url;
            return $url;
        } catch (\Throwable) {
            $cache[$key] = null;
            return null;
        }
    }
}

if (!function_exists('category_image_url')) {
    /**
     * The public URL for a category's image, or null when the
     * category has no image.
     *
     * Accepts an optional Category argument. Same shape as
     * product_image_url.
     *
     * Variant and format default to the category card shape:
     * 800x800 WebP. Category heroes and cards render at
     * different sizes; callers pass their own variant when
     * needed.
     *
     * Static-cached per (category id, variant, format).
     * Defensive: any failure returns null.
     */
    function category_image_url(
        ?\CorporateAid\Commerce\Models\Category $category = null,
        string $variant = 'md',
        string $format = 'webp'
    ): ?string {
        static $cache = [];

        if ($category === null || $category->id <= 0) {
            return null;
        }

        $fileId = (int) ($category->imageFileId ?? 0);
        if ($fileId <= 0) {
            return null;
        }

        $key = $fileId . ':' . $variant . ':' . $format;
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }

        try {
            $container = \CorporateAid\Core\App::instance()->container();
            $media = $container->get(
                \CorporateAid\Commerce\Repositories\MediaRepository::class
            );
            $item = $media->findById($fileId);
            if ($item === null) {
                $cache[$key] = null;
                return null;
            }

            $url = $item->variantUrl($variant, $format);
            $cache[$key] = $url;
            return $url;
        } catch (\Throwable) {
            $cache[$key] = null;
            return null;
        }
    }
}

if (!function_exists('product_picture_url')) {
    /**
     * The public URL for a product's primary image variant, or
     * a local placeholder SVG when the product has no image.
     *
     * Never returns null. This is the correct call for any
     * storefront template that renders an image on a card or a
     * thumbnail. It removes the need to branch at every call
     * site.
     *
     * Uses product_image_url() under the hood. That helper
     * returns null when the product has no image or when the
     * media read fails. This wrapper substitutes the placeholder
     * SVG in that case.
     *
     * The placeholder is a static asset served from the store's
     * own domain. No external origin. picsum.photos was retired
     * in 008G.4a.2 session 3.
     */
    function product_picture_url(
        ?\CorporateAid\Commerce\Models\Product $product = null,
        string $variant = 'card'
    ): string {
        static $cache = [];

        if ($product === null || $product->id <= 0) {
            return '/assets/img/product-placeholder.svg';
        }

        $key = $product->id . ':' . $variant;
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }

        $url = product_image_url($product, $variant, 'webp');
        $cache[$key] = $url ?? '/assets/img/product-placeholder.svg';
        return $cache[$key];
    }
}

if (!function_exists('category_picture_url')) {
    /**
     * The public URL for a category's image variant, or a local
     * placeholder SVG when the category has no image.
     *
     * Same shape as product_picture_url. Never returns null.
     * Uses category_image_url() under the hood.
     */
    function category_picture_url(
        ?\CorporateAid\Commerce\Models\Category $category = null,
        string $variant = 'md'
    ): string {
        static $cache = [];

        if ($category === null || $category->id <= 0) {
            return '/assets/img/category-placeholder.svg';
        }

        $key = $category->id . ':' . $variant;
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }

        $url = category_image_url($category, $variant, 'webp');
        $cache[$key] = $url ?? '/assets/img/category-placeholder.svg';
        return $cache[$key];
    }
}

if (!function_exists('product_picture_tag')) {
    /**
     * Render a complete <picture> block for a product's
     * primary image.
     *
     * Emits the srcset for every format the context declares
     * (webp, jpeg, avif when it ships). Emits a plain <img>
     * with the local placeholder SVG when the product has no
     * image.
     *
     * Options:
     *   variant       string  Base variant for the <img>
     *                         src fallback. Default: 'card'.
     *   alt           ?string Alt text. Default: product name.
     *   class         ?string CSS class on the <img>.
     *   sizes         string  The sizes attribute.
     *                         Default: '(max-width: 640px) 50vw, 320px'.
     *   loading       string  'lazy' or 'eager'. Default: 'lazy'.
     *   fetchpriority ?string 'high', 'low', or null.
     *   width         int     Rendered width for CLS. Default: 400.
     *   height        int     Rendered height for CLS. Default: 500.
     *   decoding      string  'async' or 'sync'. Default: 'async'.
     *   attrs         array<string,mixed>  Extra attributes
     *                 for the <img>. A key whose value is
     *                 true renders as a boolean attribute
     *                 (e.g. 'data-sf-gallery-main' => true).
     *
     * @param array<string,mixed> $options
     */
    function product_picture_tag(
        ?\CorporateAid\Commerce\Models\Product $product = null,
        array $options = []
    ): string {
        $variant       = (string) ($options['variant'] ?? 'card');
        $alt           = $options['alt'] ?? null;
        $class         = $options['class'] ?? null;
        $sizes         = (string) ($options['sizes'] ?? '(max-width: 640px) 50vw, 320px');
        $loading       = (string) ($options['loading'] ?? 'lazy');
        $fetchpriority = $options['fetchpriority'] ?? null;
        $width         = (int) ($options['width'] ?? 400);
        $height        = (int) ($options['height'] ?? 500);
        $decoding      = (string) ($options['decoding'] ?? 'async');
        $extraAttrs    = $options['attrs'] ?? [];

        // Resolve the MediaItem for this product's primary image.
        $media = product_primary_media_item($product);

        // Alt text resolution.
        if ($alt === null) {
            $alt = ($product !== null && $product->name !== '') ? $product->name : '';
        }

        // Common <img> attribute string used in both paths.
        $imgAttrs = _picture_tag_img_attrs(
            $alt, $class, $loading, $fetchpriority, $width, $height, $decoding, $extraAttrs
        );

        // No image: emit the placeholder <img> only.
        if ($media === null) {
            $placeholder = '/assets/img/product-placeholder.svg';
            return '<img src="' . e($placeholder) . '"' . $imgAttrs . '>';
        }

        // With image: build the <picture> block.
        $spec       = \CorporateAid\Commerce\Support\MediaVariantSet::for(
            \CorporateAid\Commerce\Support\MediaVariantSet::CONTEXT_PRODUCT_IMAGE
        );
        $sourcesHtml = _picture_tag_sources($media, $spec, $sizes);
        $primarySrc  = $media->variantUrl($variant, _picture_tag_primary_format($spec));

        return '<picture>'
            . $sourcesHtml
            . '<img src="' . e($primarySrc) . '"' . $imgAttrs . '>'
            . '</picture>';
    }
}

if (!function_exists('category_picture_tag')) {
    /**
     * Render a complete <picture> block for a category's
     * image. Same shape and options as product_picture_tag().
     *
     * Options default to the category card shape:
     *   variant 'md', sizes '(max-width: 640px) 50vw, 400px',
     *   width 500, height 500.
     *
     * @param array<string,mixed> $options
     */
    function category_picture_tag(
        ?\CorporateAid\Commerce\Models\Category $category = null,
        array $options = []
    ): string {
        $variant       = (string) ($options['variant'] ?? 'md');
        $alt           = $options['alt'] ?? null;
        $class         = $options['class'] ?? null;
        $sizes         = (string) ($options['sizes'] ?? '(max-width: 640px) 50vw, 400px');
        $loading       = (string) ($options['loading'] ?? 'lazy');
        $fetchpriority = $options['fetchpriority'] ?? null;
        $width         = (int) ($options['width'] ?? 500);
        $height        = (int) ($options['height'] ?? 500);
        $decoding      = (string) ($options['decoding'] ?? 'async');
        $extraAttrs    = $options['attrs'] ?? [];

        $media = category_primary_media_item($category);

        if ($alt === null) {
            $alt = ($category !== null && $category->name !== '') ? $category->name : '';
        }

        $imgAttrs = _picture_tag_img_attrs(
            $alt, $class, $loading, $fetchpriority, $width, $height, $decoding, $extraAttrs
        );

        if ($media === null) {
            $placeholder = '/assets/img/category-placeholder.svg';
            return '<img src="' . e($placeholder) . '"' . $imgAttrs . '>';
        }

        $spec        = \CorporateAid\Commerce\Support\MediaVariantSet::for(
            \CorporateAid\Commerce\Support\MediaVariantSet::CONTEXT_CATEGORY_IMAGE
        );
        $sourcesHtml = _picture_tag_sources($media, $spec, $sizes);
        $primarySrc  = $media->variantUrl($variant, _picture_tag_primary_format($spec));

        return '<picture>'
            . $sourcesHtml
            . '<img src="' . e($primarySrc) . '"' . $imgAttrs . '>'
            . '</picture>';
    }
}

if (!function_exists('media_picture_tag')) {
    /**
     * Render a complete <picture> block for an already-
     * resolved MediaItem.
     *
     * Same shape as product_picture_tag() and
     * category_picture_tag(), but takes the MediaItem
     * directly. Use this when the caller already has the
     * media item in hand:
     *
     *   - Product detail thumbnails (built from $gallery).
     *   - Swatch preview images (built from $swatchImages).
     *   - Any other view that resolves MediaItems itself.
     *
     * When $media is null, emits the fallback <img> with the
     * provided placeholder.
     *
     * @param array<string,mixed> $options
     */
    function media_picture_tag(
        ?\CorporateAid\Commerce\Models\MediaItem $media,
        string $context,
        array $options = []
    ): string {
        $variant       = (string) ($options['variant'] ?? 'card');
        $alt           = (string) ($options['alt'] ?? '');
        $class         = $options['class'] ?? null;
        $sizes         = (string) ($options['sizes'] ?? '(max-width: 640px) 50vw, 320px');
        $loading       = (string) ($options['loading'] ?? 'lazy');
        $fetchpriority = $options['fetchpriority'] ?? null;
        $width         = (int) ($options['width'] ?? 400);
        $height        = (int) ($options['height'] ?? 500);
        $decoding      = (string) ($options['decoding'] ?? 'async');
        $extraAttrs    = $options['attrs'] ?? [];
        $placeholder   = (string) ($options['placeholder'] ?? '/assets/img/product-placeholder.svg');

        $imgAttrs = _picture_tag_img_attrs(
            $alt, $class, $loading, $fetchpriority, $width, $height, $decoding, $extraAttrs
        );

        if ($media === null) {
            return '<img src="' . e($placeholder) . '"' . $imgAttrs . '>';
        }

        $spec        = \CorporateAid\Commerce\Support\MediaVariantSet::for($context);
        $sourcesHtml = _picture_tag_sources($media, $spec, $sizes);
        $primarySrc  = $media->variantUrl($variant, _picture_tag_primary_format($spec));

        return '<picture>'
            . $sourcesHtml
            . '<img src="' . e($primarySrc) . '"' . $imgAttrs . '>'
            . '</picture>';
    }
}

if (!function_exists('product_primary_media_item')) {
    /**
     * Resolve the MediaItem for a product's primary image.
     * Returns null when the product has no image or the media
     * read fails. Static-cached per product id.
     */
    function product_primary_media_item(
        ?\CorporateAid\Commerce\Models\Product $product = null
    ): ?\CorporateAid\Commerce\Models\MediaItem {
        static $cache = [];

        if ($product === null || $product->id <= 0) {
            return null;
        }

        $key = $product->id;
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }

        try {
            $container = \CorporateAid\Core\App::instance()->container();
            $imageRepo = $container->get(
                \CorporateAid\Commerce\Repositories\ProductImageRepository::class
            );
            $primary = $imageRepo->primaryFor((int) $product->id);
            if ($primary === null) {
                return $cache[$key] = null;
            }
            $mediaRepo = $container->get(
                \CorporateAid\Commerce\Repositories\MediaRepository::class
            );
            return $cache[$key] = $mediaRepo->findById($primary->fileId);
        } catch (\Throwable) {
            return $cache[$key] = null;
        }
    }
}

if (!function_exists('category_primary_media_item')) {
    /**
     * Resolve the MediaItem for a category's image. Returns
     * null when the category has no image or the media read
     * fails. Static-cached per category id.
     */
    function category_primary_media_item(
        ?\CorporateAid\Commerce\Models\Category $category = null
    ): ?\CorporateAid\Commerce\Models\MediaItem {
        static $cache = [];

        if ($category === null || $category->id <= 0) {
            return null;
        }

        $fileId = (int) ($category->imageFileId ?? 0);
        if ($fileId <= 0) {
            return null;
        }

        $key = $fileId;
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }

        try {
            $container = \CorporateAid\Core\App::instance()->container();
            $mediaRepo = $container->get(
                \CorporateAid\Commerce\Repositories\MediaRepository::class
            );
            return $cache[$key] = $mediaRepo->findById($fileId);
        } catch (\Throwable) {
            return $cache[$key] = null;
        }
    }
}

if (!function_exists('_picture_tag_sources')) {
    /**
     * Build the <source> blocks for a <picture>. One source
     * per format that the context declares AND that the
     * MediaItem actually has a variant for.
     *
     * The order matches the industry-standard preference:
     * avif first, then webp, then jpeg. Browsers try sources
     * in declaration order and pick the first supported
     * format.
     *
     * Cached per (uuid, format, context-variant-set). The
     * expensive part is variantUrl() calls and possible DB
     * reads; the string assembly is cheap.
     */
    function _picture_tag_sources(
        \CorporateAid\Commerce\Models\MediaItem $media,
        \CorporateAid\Commerce\Support\MediaContextSpec $spec,
        string $sizes
    ): string {
        static $cache = [];

        $uuid = (string) $media->file->uuid;
        $key  = $uuid . ':' . $sizes;

        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }

        // Collect every format the context declares.
        $declaredFormats = [];
        foreach ($spec->variants as $vSpec) {
            foreach ($vSpec->formats as $format) {
                $declaredFormats[$format] = true;
            }
        }

        // Preference order: avif, webp, jpeg. Anything else
        // (png, gif) is appended after.
        $ordered = [];
        foreach (['avif', 'webp', 'jpeg'] as $fmt) {
            if (isset($declaredFormats[$fmt])) {
                $ordered[] = $fmt;
                unset($declaredFormats[$fmt]);
            }
        }
        foreach (array_keys($declaredFormats) as $fmt) {
            $ordered[] = $fmt;
        }

        $mimeTypes = [
            'avif' => 'image/avif',
            'webp' => 'image/webp',
            'jpeg' => 'image/jpeg',
            'png'  => 'image/png',
            'gif'  => 'image/gif',
        ];

        $html = '';
        foreach ($ordered as $format) {
            $srcsetEntries = [];
            foreach ($spec->variants as $vSpec) {
                if (!in_array($format, $vSpec->formats, true)) {
                    continue;
                }
                $url = $media->variantUrl($vSpec->name, $format);
                $srcsetEntries[] = e($url) . ' ' . $vSpec->width . 'w';
            }
            if ($srcsetEntries === []) {
                continue;
            }

            $type = $mimeTypes[$format] ?? 'application/octet-stream';
            $html .= '<source type="' . e($type) . '"'
                   . ' srcset="' . implode(', ', $srcsetEntries) . '"'
                   . ' sizes="' . e($sizes) . '">';
        }

        return $cache[$key] = $html;
    }
}

if (!function_exists('_picture_tag_primary_format')) {
    /**
     * The format used for the <img src> fallback. Prefer webp
     * (universal support today), fall back to jpeg, then to
     * whatever the spec declares first.
     */
    function _picture_tag_primary_format(
        \CorporateAid\Commerce\Support\MediaContextSpec $spec
    ): string {
        $all = [];
        foreach ($spec->variants as $vSpec) {
            foreach ($vSpec->formats as $format) {
                $all[$format] = true;
            }
        }
        if (isset($all['webp'])) return 'webp';
        if (isset($all['jpeg'])) return 'jpeg';
        if (isset($all['avif'])) return 'avif';
        return (string) array_key_first($all);
    }
}

if (!function_exists('_picture_tag_img_attrs')) {
    /**
     * Build the attribute string for the <img> element.
     *
     * @param array<string,mixed> $extra
     *              Extra attributes. A value of true renders
     *              as a boolean attribute name only.
     */
    function _picture_tag_img_attrs(
        string $alt,
        ?string $class,
        string $loading,
        ?string $fetchpriority,
        int $width,
        int $height,
        string $decoding,
        array $extra
    ): string {
        $parts = [];
        $parts[] = ' alt="' . e($alt) . '"';
        if ($class !== null && $class !== '') {
            $parts[] = ' class="' . e($class) . '"';
        }
        $parts[] = ' loading="' . e($loading) . '"';
        if ($fetchpriority !== null && $fetchpriority !== '') {
            $parts[] = ' fetchpriority="' . e($fetchpriority) . '"';
        }
        $parts[] = ' decoding="' . e($decoding) . '"';
        $parts[] = ' width="' . $width . '"';
        $parts[] = ' height="' . $height . '"';

        foreach ($extra as $name => $value) {
            $name = (string) $name;
            if ($name === '' || !preg_match('/^[a-zA-Z_][a-zA-Z0-9_:-]*$/', $name)) {
                continue;
            }
            if ($value === true) {
                $parts[] = ' ' . $name;
            } elseif ($value !== false && $value !== null) {
                $parts[] = ' ' . $name . '="' . e((string) $value) . '"';
            }
        }

        return implode('', $parts);
    }
}

if (!function_exists('customer_unread_message_count')) {
    /**
     * Unread message count for the currently logged-in customer.
     *
     * Returns null when no customer is logged in. Returns an
     * int (possibly 0) when a customer is logged in.
     *
     * The null-vs-int distinction matters for the header bell:
     * a logged-in customer with zero unread messages shows the
     * bell with no badge; a guest shows no bell at all. Both
     * would return 0 if we did not distinguish.
     *
     * Static-cached per request. The header renders on every
     * storefront page, so this helper is called on every page.
     * Caching avoids a redundant container resolve and DB
     * query if anything else on the same page also needs the
     * count.
     *
     * Defensive: any failure returns null. A broken read hides
     * the bell rather than crashing the page.
     */
    function customer_unread_message_count(): ?int
    {
        static $cache = null;
        static $resolved = false;
        if ($resolved) {
            return $cache;
        }
        $resolved = true;

        try {
            $container = \CorporateAid\Core\App::instance()->container();
            $customerAuth = $container->get(
                \CorporateAid\Commerce\Services\CustomerAuthService::class
            );
            $customer = $customerAuth->user();
            if ($customer === null) {
                $cache = null;
                return null;
            }

            $svc = $container->get(
                \CorporateAid\Commerce\Services\CustomerNotificationService::class
            );
            $cache = $svc->unreadCount($customer->id);
            return $cache;
        } catch (\Throwable) {
            $cache = null;
            return null;
        }
    }
}

if (!function_exists('generate_uuid')) {
    /**
     * RFC 4122 v4 UUID.
     *
     * Pulled out of FileService so other services can produce
     * UUIDs without duplicating the generator. Used for file
     * storage names, and will be used for other identifiers
     * (import batch ids, API keys) as the codebase grows.
     *
     * Returns a canonical 8-4-4-4-12 string.
     */
    function generate_uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40); // version 4
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80); // variant

        return vsprintf(
            '%s%s-%s-%s-%s-%s%s%s',
            str_split(bin2hex($data), 4)
        );
    }
}

if (!function_exists('wishlist_product_ids')) {
    /**
     * The current viewer's wishlist product IDs.
     *
     * Resolved once per request and cached in a static. Views
     * that render many product cards (a 24-item catalog grid,
     * a home page with three product sections) call this once
     * per card - the static cache means the container resolve
     * and the DB query happen once total, not once per card.
     *
     * Guests read from their customer session wishlist.
     * Logged-in customers read from commerce_wishlists.
     *
     * Returns an empty array on any failure. A broken wishlist
     * read must not break a storefront page.
     *
     * @return array<int, int>
     */
    function wishlist_product_ids(): array
    {
        /** @var array<int, int>|null $cache */
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }

        try {
            $svc = \CorporateAid\Core\App::instance()
                ->container()
                ->get(\CorporateAid\Commerce\Services\WishlistService::class);
            $cache = $svc->productIds();
        } catch (\Throwable) {
            $cache = [];
        }

        return $cache;
    }
}

if (!function_exists('flash')) {
    function flash(string $key, mixed $default = null): mixed
    {
        try {
            return \CorporateAid\Core\App::instance()
                ->container()
                ->get(\CorporateAid\Core\Session::class)
                ->getFlash($key, $default);
        } catch (\Throwable) {
            return $default;
        }
    }
}

if (!function_exists('auth_user')) {
    function auth_user(): ?\CorporateAid\Platform\Models\User
    {
        try {
            return \CorporateAid\Core\App::instance()
                ->container()
                ->get(\CorporateAid\Core\Auth::class)
                ->user();
        } catch (\Throwable) {
            return null;
        }
    }
}

if (!function_exists('view_partial')) {
    /**
     * Render a partial template. Returns HTML string.
     * Partials are expected to be self-contained (no layout).
     *
     * @param array<string,mixed> $data
     */
    function view_partial(string $name, array $data = []): string
    {
        try {
            /** @var \CorporateAid\Core\View $view */
            $view = \CorporateAid\Core\App::instance()
                ->container()
                ->get(\CorporateAid\Core\View::class);
            return $view->render($name, $data);
        } catch (\Throwable $e) {
            \CorporateAid\Platform\Support\Logger::error('view_partial(' . $name . ') failed: ' . $e->getMessage());
            return '';
        }
    }
}

if (!function_exists('store_font_selections')) {
    /**
     * Read the store's font selections and return the resolved
     * FontCatalog entries.
     *
     * @param ?\CorporateAid\Commerce\Models\Store $store
     * @return array{body:?array<string,mixed>,display:?array<string,mixed>,script:?array<string,mixed>}
     */
    function store_font_selections(
        ?\CorporateAid\Commerce\Models\Store $store = null
    ): array {
        static $cache = [];

        $default = ['body' => null, 'display' => null, 'script' => null];

        try {
            if ($store === null) {
                $container = \CorporateAid\Core\App::instance()->container();
                $context = $container->get(
                    \CorporateAid\Commerce\Context\StoreContext::class
                );
                if (!$context->has()) {
                    return $default;
                }
                $repo = $container->get(
                    \CorporateAid\Commerce\Repositories\StoreRepository::class
                );
                $store = $repo->findById($context->id());
                if ($store === null) {
                    return $default;
                }
            }

            $storeId = (int) ($store->id ?? 0);
            if ($storeId <= 0) {
                return $default;
            }

            if (isset($cache[$storeId])) {
                return $cache[$storeId];
            }

            $container = \CorporateAid\Core\App::instance()->container();
            $fontRepo = $container->get(
                \CorporateAid\Commerce\Repositories\FontRepository::class
            );
            $slugs = $fontRepo->getSelections($storeId);

            // Platform defaults. When a slot is empty (the merchant
            // has not chosen a font, or cleared it), fall back to
            // the catalog entry for the platform default. This
            // guarantees the storefront always has a self-hosted
            // font for every slot, never a silent system fallback.
            $defaults = [
                'body'    => 'inter',
                'display' => 'playfair-display',
                'script'  => 'dancing-script',
            ];

            $out = ['body' => null, 'display' => null, 'script' => null];
            foreach ($slugs as $slot => $slug) {
                if ($slug === null || $slug === '') {
                    $slug = $defaults[$slot] ?? null;
                }
                if ($slug === null) {
                    continue;
                }
                $font = \CorporateAid\Commerce\Support\Fonts\FontCatalog::find($slug);
                if ($font === null) {
                    continue;
                }
                if ($slot === 'body') {
                    $out['body'] = $font;
                } elseif ($slot === 'display') {
                    $out['display'] = $font;
                } elseif ($slot === 'script') {
                    $out['script'] = $font;
                }
            }

            $cache[$storeId] = $out;
            return $out;
        } catch (\Throwable $e) {
            return $default;
        }
    }
}

if (!function_exists('store_font_face_css')) {
    /**
     * Build the @font-face CSS for the store's selected fonts.
     *
     * Returns an empty string when the store uses platform
     * defaults. Otherwise emits one @font-face rule per
     * (font, weight) pair. Only weights declared by the
     * FontCatalog are emitted - the downloader wrote exactly
     * those files at save time.
     */
    function store_font_face_css(
        ?\CorporateAid\Commerce\Models\Store $store = null
    ): string {
        $selections = store_font_selections($store);
        $blocks = [];

        foreach ($selections as $slot => $font) {
            if ($font === null) {
                continue;
            }

            $slug    = (string) ($font['slug']   ?? '');
            $family  = (string) ($font['family'] ?? '');
            $weights = (array)  ($font['weights'] ?? []);

            if ($slug === '' || $family === '') {
                continue;
            }

            $safeFamily = addcslashes($family, "'\\");

            foreach ($weights as $weight) {
                $weightStr = (string) $weight;
                $url = '/assets/fonts/'
                    . rawurlencode($slug) . '/'
                    . rawurlencode($slug . '-' . $weightStr . '.woff2');

                $blocks[] = "@font-face {"
                    . "font-family: '{$safeFamily}';"
                    . "font-style: normal;"
                    . "font-weight: {$weightStr};"
                    . "font-display: swap;"
                    . "src: url('{$url}') format('woff2');"
                    . "}";
            }
        }

        return implode("\n", $blocks);
    }
}


if (!function_exists('admin_pending_review_count')) {
    /**
     * Number of product reviews currently awaiting moderation.
     * Used by the admin sidebar badge. Static-cached per
     * request. Returns 0 on any failure.
     *
     * Defensive: a broken repository cannot break the admin
     * shell. A store with no reviews table (edge case) returns
     * 0 and the badge stays hidden.
     */
    function admin_pending_review_count(): int
    {
        static $cached = null;

        if ($cached !== null) {
            return $cached;
        }

        try {
            $container = \CorporateAid\Core\App::instance()->container();
            $repo = $container->get(
                \CorporateAid\Commerce\Repositories\ProductReviewRepository::class
            );
            return $cached = $repo->countPending();
        } catch (\Throwable) {
            return $cached = 0;
        }
    }

    /**
     * Orders awaiting merchant action. Counts orders in
     * status pending_payment or paid - the "new" tab of the
     * admin orders queue.
     *
     * Used by the admin sidebar badge and the dashboard tile.
     * Static-cached per request. Returns 0 on any failure.
     *
     * Defensive: a broken repository cannot break the admin
     * shell.
     */
    function admin_pending_order_count(): int
    {
        static $cached = null;

        if ($cached !== null) {
            return $cached;
        }

        try {
            $container = \CorporateAid\Core\App::instance()->container();
            $repo = $container->get(
                \CorporateAid\Commerce\Repositories\OrderRepository::class
            );
            return $cached = $repo->countByStatus('pending_payment')
                           + $repo->countByStatus('paid');
        } catch (\Throwable) {
            return $cached = 0;
        }
    }
}

if (!function_exists('tax_note')) {
    /**
     * Render the PAngV § 6 note for a pricing breakdown.
     *
     * Reads the tax classes returned by the pricing engine
     * and produces one of:
     *
     *   "inkl. 19% MwSt."              inclusive-only cart
     *   "zzgl. 19% MwSt."              exclusive-only cart
     *   "inkl. 19% MwSt. zzgl. 7% MwSt."  mixed cart
     *   ""                              no tax
     *
     * The rate percent comes from the class entries. When
     * multiple classes exist with different rates, each
     * regime lists its own rates joined by " + ".
     *
     * @param array<string,mixed> $pricing The pricing result
     *        from OrderPricingService (either preview() or
     *        compute()).
     */
    function tax_note(array $pricing): string
    {
        $classes = $pricing['tax_classes'] ?? [];
        if (!is_array($classes) || $classes === []) {
            return '';
        }

        $inclusiveRates = [];
        $exclusiveRates = [];

        foreach ($classes as $c) {
            if (!is_array($c)) {
                continue;
            }
            $rate = (float) ($c['rate_percent'] ?? 0);
            if ($rate <= 0.0) {
                continue;
            }
            $isInc = !empty($c['is_inclusive']);
            $label = rtrim(rtrim(number_format($rate, 2, '.', ''), '0'), '.');
            if ($isInc) {
                $inclusiveRates[$label] = true;
            } else {
                $exclusiveRates[$label] = true;
            }
        }

        $parts = [];
        if ($inclusiveRates !== []) {
            $parts[] = 'inkl. ' . implode(' + ', array_keys($inclusiveRates)) . '% MwSt.';
        }
        if ($exclusiveRates !== []) {
            $parts[] = 'zzgl. ' . implode(' + ', array_keys($exclusiveRates)) . '% MwSt.';
        }

        return implode(' ', $parts);
    }
}

if (!function_exists('brand_name')) {
    /**
     * The brand name for the current request.
     *
     * Preference order:
     *   1. The resolved store's name (commerce_stores.name).
     *   2. config('branding.product_name').
     *   3. 'Store'.
     */
    function brand_name(?\CorporateAid\Commerce\Models\Store $store = null): string
    {
        if ($store !== null && $store->name !== '') {
            return (string) $store->name;
        }

        if (function_exists('config')) {
            $name = (string) config('branding.product_name', '');
            if ($name !== '') {
                return $name;
            }
        }

        return 'Store';
    }
}

if (!function_exists('brand_short')) {
    /**
     * The brand short name for the current request.
     *
     * Preference order:
     *   1. The store's PWA short name (commerce_stores.pwa_short_name).
     *   2. config('branding.product_short').
     *   3. brand_name() as a last resort.
     */
    function brand_short(?\CorporateAid\Commerce\Models\Store $store = null): string
    {
        if ($store !== null
            && $store->pwaShortName !== null
            && $store->pwaShortName !== ''
        ) {
            return (string) $store->pwaShortName;
        }

        if (function_exists('config')) {
            $short = (string) config('branding.product_short', '');
            if ($short !== '') {
                return $short;
            }
        }

        return brand_name($store);
    }
}
