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
        add_filter('post_thumbnail_html', [$this, 'filterAlbumFeaturedImage'], 10, 5);
        add_filter('the_title', [$this, 'filterAlbumThemeTitle'], 20, 2);
        add_filter('render_block_core/post-title', [$this, 'filterAlbumThemeBlockTitle'], 20, 2);
        add_filter('render_block_core/post-author-name', [$this, 'filterAlbumThemeBlockMeta'], 20, 2);
        add_filter('render_block_core/post-date', [$this, 'filterAlbumThemeBlockMeta'], 20, 2);
        add_filter('body_class', [$this, 'addAlbumBodyClass']);
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

    /**
     * @param string|array<int, string> $size
     * @param string|array<string, string> $attr
     */
    public function filterAlbumFeaturedImage(string $html, int $postId, int $postThumbnailId, $size, $attr): string
    {
        if (is_admin() || ! is_singular('campwp_album')) {
            return $html;
        }

        if (get_queried_object_id() !== $postId) {
            return $html;
        }

        return '';
    }

    /**
     * @param string|int $postId
     */
    public function filterAlbumThemeTitle(string $title, $postId): string
    {
        if (! $this->shouldSuppressThemePresentationForAlbum()) {
            return $title;
        }

        if ((int) $postId !== get_queried_object_id()) {
            return $title;
        }

        return '';
    }

    /**
     * @param array<string, mixed> $block
     */
    public function filterAlbumThemeBlockTitle(string $blockContent, array $block): string
    {
        if (! $this->shouldSuppressThemePresentationForAlbum()) {
            return $blockContent;
        }

        return '';
    }

    /**
     * @param array<string, mixed> $block
     */
    public function filterAlbumThemeBlockMeta(string $blockContent, array $block): string
    {
        if (! $this->shouldSuppressThemePresentationForAlbum()) {
            return $blockContent;
        }

        return '';
    }

    /**
     * @param list<string> $classes
     * @return list<string>
     */
    public function addAlbumBodyClass(array $classes): array
    {
        if (is_singular('campwp_album')) {
            $classes[] = 'campwp-album-singular';
        }

        return $classes;
    }

    private function shouldSuppressThemePresentationForAlbum(): bool
    {
        if (is_admin() || ! is_singular('campwp_album')) {
            return false;
        }

        $queriedId = get_queried_object_id();
        if ($queriedId <= 0) {
            return false;
        }

        $queried = get_post($queriedId);

        return $queried instanceof \WP_Post && $queried->post_type === 'campwp_album';
    }
}
