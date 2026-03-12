<?php

namespace CampWP\Integrations;

use CampWP\PostTypes;

class WooCommerceBridge
{
    public function register(): void
    {
        add_action('add_meta_boxes', [$this, 'add_link_metabox']);
        add_action('save_post', [$this, 'save_link_meta']);
    }

    public function add_link_metabox(): void
    {
        if (! class_exists('WooCommerce')) {
            return;
        }

        add_meta_box(
            'campwp_wc_link',
            __('WooCommerce Link', 'campwp'),
            [$this, 'render_link_metabox'],
            PostTypes::ALBUM,
            'side',
            'default'
        );
    }

    public function render_link_metabox(\WP_Post $post): void
    {
        wp_nonce_field('campwp_wc_link_meta', 'campwp_wc_link_meta_nonce');
        $product_id = (int) get_post_meta($post->ID, '_campwp_wc_product_id', true);
        ?>
        <p>
            <label for="campwp_wc_product_id"><?php esc_html_e('WooCommerce Product ID', 'campwp'); ?></label>
            <input type="number" min="1" id="campwp_wc_product_id" name="campwp_wc_product_id" value="<?php echo esc_attr((string) $product_id); ?>" class="widefat" />
        </p>
        <?php
    }

    public function save_link_meta(int $post_id): void
    {
        if (get_post_type($post_id) !== PostTypes::ALBUM) {
            return;
        }

        if (! isset($_POST['campwp_wc_link_meta_nonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['campwp_wc_link_meta_nonce'])), 'campwp_wc_link_meta')) {
            return;
        }

        if (! current_user_can('edit_post', $post_id)) {
            return;
        }

        update_post_meta($post_id, '_campwp_wc_product_id', isset($_POST['campwp_wc_product_id']) ? absint($_POST['campwp_wc_product_id']) : 0);
    }
}
