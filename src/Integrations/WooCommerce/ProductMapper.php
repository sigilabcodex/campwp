<?php

declare(strict_types=1);

namespace CampWP\Integrations\WooCommerce;

final class ProductMapper
{
    public const META_KEY = '_campwp_wc_product_id';
    public const NONCE_ACTION = 'campwp_save_wc_product_map';
    public const NONCE_NAME = 'campwp_wc_product_map_nonce';
    public const INPUT_NAME = 'campwp_wc_product_id';

    public function getMappedProductId(int $contentId): int
    {
        return absint(get_post_meta($contentId, self::META_KEY, true));
    }

    public function setMappedProductId(int $contentId, int $productId): void
    {
        if ($productId <= 0) {
            delete_post_meta($contentId, self::META_KEY);
            return;
        }

        update_post_meta($contentId, self::META_KEY, $productId);
    }

    public function canMapToProduct(int $productId): bool
    {
        if ($productId <= 0) {
            return true;
        }

        return get_post_type($productId) === 'product';
    }
}
