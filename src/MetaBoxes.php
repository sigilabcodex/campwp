<?php

namespace CampWP;

class MetaBoxes
{
    public function register(): void
    {
        add_action('add_meta_boxes', [$this, 'register_meta_boxes']);
        add_action('save_post', [$this, 'save_album_meta']);
        add_action('save_post', [$this, 'save_track_meta']);
    }

    public function register_meta_boxes(): void
    {
        add_meta_box('campwp_album_details', __('Album Details', 'campwp'), [$this, 'render_album_meta'], PostTypes::ALBUM, 'normal', 'default');
        add_meta_box('campwp_track_details', __('Track Details', 'campwp'), [$this, 'render_track_meta'], PostTypes::TRACK, 'normal', 'default');
    }

    public function render_album_meta(\WP_Post $post): void
    {
        wp_nonce_field('campwp_album_meta', 'campwp_album_meta_nonce');
        $release_date = get_post_meta($post->ID, '_campwp_release_date', true);
        $catalog_number = get_post_meta($post->ID, '_campwp_catalog_number', true);
        ?>
        <p>
            <label for="campwp_release_date"><?php esc_html_e('Release Date', 'campwp'); ?></label><br />
            <input type="date" id="campwp_release_date" name="campwp_release_date" value="<?php echo esc_attr($release_date); ?>" />
        </p>
        <p>
            <label for="campwp_catalog_number"><?php esc_html_e('Catalog Number', 'campwp'); ?></label><br />
            <input type="text" id="campwp_catalog_number" name="campwp_catalog_number" value="<?php echo esc_attr($catalog_number); ?>" class="regular-text" />
        </p>
        <?php
    }

    public function render_track_meta(\WP_Post $post): void
    {
        wp_nonce_field('campwp_track_meta', 'campwp_track_meta_nonce');
        $album_id = (int) get_post_meta($post->ID, '_campwp_album_id', true);
        $track_number = (int) get_post_meta($post->ID, '_campwp_track_number', true);

        $albums = get_posts([
            'post_type' => PostTypes::ALBUM,
            'numberposts' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
        ]);
        ?>
        <p>
            <label for="campwp_album_id"><?php esc_html_e('Album', 'campwp'); ?></label><br />
            <select id="campwp_album_id" name="campwp_album_id">
                <option value="0"><?php esc_html_e('— None —', 'campwp'); ?></option>
                <?php foreach ($albums as $album) : ?>
                    <option value="<?php echo esc_attr((string) $album->ID); ?>" <?php selected($album_id, $album->ID); ?>>
                        <?php echo esc_html($album->post_title); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>
        <p>
            <label for="campwp_track_number"><?php esc_html_e('Track Number', 'campwp'); ?></label><br />
            <input type="number" min="1" id="campwp_track_number" name="campwp_track_number" value="<?php echo esc_attr((string) $track_number); ?>" />
        </p>
        <?php
    }

    public function save_album_meta(int $post_id): void
    {
        if (get_post_type($post_id) !== PostTypes::ALBUM || ! $this->can_save($post_id, 'campwp_album_meta_nonce', 'campwp_album_meta')) {
            return;
        }

        update_post_meta($post_id, '_campwp_release_date', isset($_POST['campwp_release_date']) ? sanitize_text_field(wp_unslash($_POST['campwp_release_date'])) : '');
        update_post_meta($post_id, '_campwp_catalog_number', isset($_POST['campwp_catalog_number']) ? sanitize_text_field(wp_unslash($_POST['campwp_catalog_number'])) : '');
    }

    public function save_track_meta(int $post_id): void
    {
        if (get_post_type($post_id) !== PostTypes::TRACK || ! $this->can_save($post_id, 'campwp_track_meta_nonce', 'campwp_track_meta')) {
            return;
        }

        update_post_meta($post_id, '_campwp_album_id', isset($_POST['campwp_album_id']) ? absint($_POST['campwp_album_id']) : 0);
        update_post_meta($post_id, '_campwp_track_number', isset($_POST['campwp_track_number']) ? absint($_POST['campwp_track_number']) : 0);
    }

    private function can_save(int $post_id, string $nonce_name, string $nonce_action): bool
    {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return false;
        }

        if (! isset($_POST[$nonce_name]) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[$nonce_name])), $nonce_action)) {
            return false;
        }

        return current_user_can('edit_post', $post_id);
    }
}
