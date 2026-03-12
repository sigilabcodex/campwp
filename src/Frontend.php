<?php

namespace CampWP;

class Frontend
{
    public function register(): void
    {
        add_shortcode('campwp_album', [$this, 'render_album_shortcode']);
        add_shortcode('campwp_track', [$this, 'render_track_shortcode']);
    }

    public function render_album_shortcode(array $atts): string
    {
        $atts = shortcode_atts(['id' => 0], $atts);
        $album_id = absint($atts['id']);
        if (! $album_id) {
            return '';
        }

        $tracks = get_posts([
            'post_type' => PostTypes::TRACK,
            'posts_per_page' => -1,
            'meta_key' => '_campwp_album_id',
            'meta_value' => $album_id,
            'orderby' => 'meta_value_num',
            'meta_type' => 'NUMERIC',
            'order' => 'ASC',
        ]);

        ob_start();
        echo '<div class="campwp-album">';
        echo '<h3>' . esc_html(get_the_title($album_id)) . '</h3>';
        echo '<ol>';
        foreach ($tracks as $track) {
            echo '<li>' . esc_html($track->post_title) . '</li>';
        }
        echo '</ol>';
        echo '</div>';

        return (string) ob_get_clean();
    }

    public function render_track_shortcode(array $atts): string
    {
        $atts = shortcode_atts(['id' => 0], $atts);
        $track_id = absint($atts['id']);

        if (! $track_id) {
            return '';
        }

        return sprintf(
            '<div class="campwp-track"><strong>%s</strong></div>',
            esc_html(get_the_title($track_id))
        );
    }
}
