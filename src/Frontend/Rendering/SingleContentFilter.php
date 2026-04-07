<?php

declare(strict_types=1);

namespace CampWP\Frontend\Rendering;

final class SingleContentFilter
{
    private AlbumPageRenderer $albumRenderer;

    private TrackPageRenderer $trackRenderer;

    public function __construct(AlbumPageRenderer $albumRenderer, TrackPageRenderer $trackRenderer)
    {
        $this->albumRenderer = $albumRenderer;
        $this->trackRenderer = $trackRenderer;
    }

    public function register(): void
    {
        add_filter('the_content', [$this, 'filterContent']);
    }

    public function filterContent(string $content): string
    {
        if (! is_singular() || ! in_the_loop() || ! is_main_query()) {
            return $content;
        }

        $post = get_post();

        if (! $post instanceof \WP_Post) {
            return $content;
        }

        if ($post->post_type === 'campwp_album') {
            return $this->albumRenderer->render($post, $content);
        }

        if ($post->post_type === 'campwp_track') {
            return $this->trackRenderer->render($post, $content);
        }

        return $content;
    }
}
