<?php

declare(strict_types=1);

namespace CampWP\Integrations\WooCommerce;

final class WooCommerceService
{
    private ProductMapper $productMapper;

    public function __construct()
    {
        $this->productMapper = new ProductMapper();
    }

    public function register(): void
    {
        add_action('init', [$this, 'registerProductMappingMeta']);
        add_action('add_meta_boxes', [$this, 'registerProductMappingMetaBoxes']);

        foreach ($this->getAlbumPostTypes() as $albumPostType) {
            add_action('save_post_' . $albumPostType, [$this, 'saveProductMappingMetaBox'], 10, 2);
        }

        add_action('save_post_' . $this->getTrackPostType(), [$this, 'saveProductMappingMetaBox'], 10, 2);
    }

    public function registerProductMappingMeta(): void
    {
        foreach ($this->getAlbumPostTypes() as $albumPostType) {
            $this->registerProductMetaForPostType($albumPostType);
        }

        $this->registerProductMetaForPostType($this->getTrackPostType());
    }

    public function registerProductMappingMetaBoxes(): void
    {
        foreach ($this->getAlbumPostTypes() as $albumPostType) {
            add_meta_box(
                'campwp-woocommerce-product-map',
                __('WooCommerce Product Mapping', 'campwp'),
                [$this, 'renderProductMappingMetaBox'],
                $albumPostType,
                'side',
                'default'
            );
        }

        add_meta_box(
            'campwp-woocommerce-product-map',
            __('WooCommerce Product Mapping', 'campwp'),
            [$this, 'renderProductMappingMetaBox'],
            $this->getTrackPostType(),
            'side',
            'default'
        );
    }

    /**
     * @param \WP_Post $post
     */
    public function renderProductMappingMetaBox($post): void
    {
        wp_nonce_field(ProductMapper::NONCE_ACTION, ProductMapper::NONCE_NAME);

        $contentId = (int) $post->ID;
        $currentProductId = $this->productMapper->getMappedProductId($contentId);
        $products = $this->getProducts();

        echo '<p>' . esc_html__('Optionally link this release to a WooCommerce product. This is a mapping only and does not affect checkout or entitlements yet.', 'campwp') . '</p>';
        echo '<label for="campwp_wc_product_id" class="screen-reader-text">' . esc_html__('WooCommerce product', 'campwp') . '</label>';
        echo '<select id="campwp_wc_product_id" name="campwp_wc_product_id" class="widefat">';
        echo '<option value="0">' . esc_html__('— No linked product —', 'campwp') . '</option>';

        foreach ($products as $product) {
            $productId = (int) $product->ID;
            $label = sprintf(
                /* translators: 1: product title, 2: product ID. */
                __('%1$s (#%2$d)', 'campwp'),
                get_the_title($productId),
                $productId
            );

            echo '<option value="' . esc_attr((string) $productId) . '"' . selected($currentProductId, $productId, false) . '>' . esc_html($label) . '</option>';
        }

        echo '</select>';

        if ($currentProductId > 0) {
            $editLink = get_edit_post_link($currentProductId);

            if (is_string($editLink) && $editLink !== '') {
                echo '<p><a href="' . esc_url($editLink) . '">' . esc_html__('Edit linked product', 'campwp') . '</a></p>';
            }
        }
    }

    /**
     * @param \WP_Post $post
     */
    public function saveProductMappingMetaBox(int $postId, $post): void
    {
        if (! isset($_POST[ProductMapper::NONCE_NAME]) || ! wp_verify_nonce(sanitize_text_field(wp_unslash((string) $_POST[ProductMapper::NONCE_NAME])), ProductMapper::NONCE_ACTION)) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (wp_is_post_revision($postId) || wp_is_post_autosave($postId)) {
            return;
        }

        if (! current_user_can('edit_post', $postId)) {
            return;
        }

        if (! in_array($post->post_type, $this->getSupportedPostTypes(), true)) {
            return;
        }

        $productId = isset($_POST[ProductMapper::INPUT_NAME]) ? absint(wp_unslash((string) $_POST[ProductMapper::INPUT_NAME])) : 0;

        if (! $this->productMapper->canMapToProduct($productId)) {
            $productId = 0;
        }

        $this->productMapper->setMappedProductId($postId, $productId);
    }

    /**
     * @return list<\WP_Post>
     */
    private function getProducts(): array
    {
        $products = get_posts([
            'post_type' => 'product',
            'post_status' => ['publish', 'private', 'draft', 'pending', 'future'],
            'posts_per_page' => 100,
            'orderby' => 'title',
            'order' => 'ASC',
            'suppress_filters' => false,
        ]);

        return is_array($products) ? $products : [];
    }

    private function registerProductMetaForPostType(string $postType): void
    {
        register_post_meta(
            $postType,
            ProductMapper::META_KEY,
            [
                'type' => 'integer',
                'single' => true,
                'default' => 0,
                'show_in_rest' => true,
                'sanitize_callback' => 'absint',
                'auth_callback' => static fn (): bool => current_user_can('edit_posts'),
            ]
        );
    }

    /**
     * @return list<string>
     */
    private function getAlbumPostTypes(): array
    {
        $postTypes = apply_filters('campwp_album_post_types', ['campwp_album']);

        if (! is_array($postTypes)) {
            return ['campwp_album'];
        }

        return array_values(array_unique(array_filter(array_map('sanitize_key', $postTypes))));
    }

    private function getTrackPostType(): string
    {
        $postType = apply_filters('campwp_track_post_type', 'campwp_track');

        if (! is_string($postType) || $postType === '') {
            return 'campwp_track';
        }

        return sanitize_key($postType);
    }

    /**
     * @return list<string>
     */
    private function getSupportedPostTypes(): array
    {
        return array_values(array_unique(array_merge($this->getAlbumPostTypes(), [$this->getTrackPostType()])));
    }
}
