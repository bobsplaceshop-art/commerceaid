<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Models;

/**
 * ProductReview model.
 *
 * One row per (store_id, customer_id, product_id). The unique
 * key on that triple enforces "one review per customer per
 * product" at the database level.
 *
 * customer_id is nullable. When a customer is anonymized
 * under DSGVO Art. 17, their reviews are not deleted — the
 * rating stays valid for the product's aggregate. The
 * customer_id is nulled instead, and the rendering layer
 * shows "Anonymous" as the author.
 *
 * order_id is nullable and records the order that qualified
 * the review for the verified-purchase badge. It is null
 * when the customer had not purchased the product before
 * reviewing, and it is nulled if the order is purged.
 *
 * The status column gates storefront rendering. Only
 * 'approved' reviews render on the product detail page.
 *
 * See docs/stages/008g.5_reviews-plan.md.
 */
final class ProductReview
{
    public int     $id               = 0;
    public int     $storeId          = 0;
    public int     $productId        = 0;
    public ?int    $customerId       = null;
    public ?int    $orderId          = null;
    public int     $rating           = 0;
    public ?string $title            = null;
    public string  $body             = '';
    public bool    $verifiedPurchase = false;
    public string  $status           = 'pending';
    public ?string $createdAt        = null;
    public ?string $updatedAt        = null;

    /**
     * @param array<string,mixed> $row
     */
    public static function fromRow(array $row): self
    {
        $r = new self();
        $r->id               = (int) ($row['id'] ?? 0);
        $r->storeId          = (int) ($row['store_id'] ?? 0);
        $r->productId        = (int) ($row['product_id'] ?? 0);
        // The row always contains these columns. The values may
        // be NULL. Use array_key_exists so a NULL value maps to
        // a null property rather than being treated as a missing
        // column. Same idiom as ProductImage::fromRow().
        $r->customerId       = array_key_exists('customer_id', $row) && $row['customer_id'] !== null
            ? (int) $row['customer_id']
            : null;
        $r->orderId          = array_key_exists('order_id', $row) && $row['order_id'] !== null
            ? (int) $row['order_id']
            : null;
        $r->rating           = (int) ($row['rating'] ?? 0);
        $r->title            = array_key_exists('title', $row) && $row['title'] !== null && $row['title'] !== ''
            ? (string) $row['title']
            : null;
        $r->body             = (string) ($row['body'] ?? '');
        $r->verifiedPurchase = ((int) ($row['verified_purchase'] ?? 0)) === 1;
        $r->status           = (string) ($row['status'] ?? 'pending');
        $r->createdAt        = isset($row['created_at']) ? (string) $row['created_at'] : null;
        $r->updatedAt        = isset($row['updated_at']) ? (string) $row['updated_at'] : null;
        return $r;
    }

    /**
     * Whether the review has passed moderation and can render
     * on the storefront.
     */
    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    /**
     * Whether the review is currently awaiting moderation.
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Whether the review was rejected by a moderator.
     */
    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }
}
