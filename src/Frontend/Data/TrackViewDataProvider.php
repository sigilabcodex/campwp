<?php

declare(strict_types=1);

namespace CampWP\Frontend\Data;

use CampWP\Domain\Audio\TrackAudioResolver;
use CampWP\Domain\Commerce\EntitlementService;
use CampWP\Frontend\Download\DownloadController;
use CampWP\Domain\Metadata\MetadataKeys;
use CampWP\Infrastructure\Media\WordPressMediaLibraryProvider;

final class TrackViewDataProvider
{
    private TrackAudioResolver $trackAudioResolver;

    private EntitlementService $entitlementService;

    private DownloadController $downloadController;

    public function __construct()
    {
        $this->trackAudioResolver = new TrackAudioResolver(new WordPressMediaLibraryProvider());
        $this->entitlementService = new EntitlementService();
        $this->downloadController = new DownloadController();
    }

    /**
     * @return array<string, mixed>
     */
    public function getTrackViewData(\WP_Post $track): array
    {
        $albumId = max(0, (int) get_post_meta($track->ID, MetadataKeys::TRACK_ALBUM_ID, true));
        $artworkId = max(0, (int) get_post_meta($track->ID, MetadataKeys::TRACK_ARTWORK_ID, true));

        $album = $albumId > 0 ? get_post($albumId) : null;
        $albumPermalink = $album instanceof \WP_Post ? get_permalink($album) : '';
        $albumArtist = $album instanceof \WP_Post
            ? $this->getMetaString($album->ID, MetadataKeys::ALBUM_ARTIST_DISPLAY)
            : '';
        $trackArtist = $this->getMetaString($track->ID, MetadataKeys::TRACK_ARTIST_DISPLAY);

        $audio = $this->trackAudioResolver->getTrackAudioFile($track->ID);
        $downloadConfig = $this->entitlementService->getTrackDownloadConfig($track->ID);

        return [
            'id' => $track->ID,
            'title' => get_the_title($track),
            'subtitle' => $this->getMetaString($track->ID, MetadataKeys::TRACK_SUBTITLE),
            'artist_display' => $trackArtist !== '' ? $trackArtist : $albumArtist,
            'credits' => $this->getMetaString($track->ID, MetadataKeys::TRACK_CREDITS),
            'lyrics' => $this->getMetaString($track->ID, MetadataKeys::TRACK_LYRICS),
            'audio' => $audio,
            'download' => [
                'enabled' => $downloadConfig['enabled'] && $audio !== null,
                'mode' => $downloadConfig['mode'],
                'mode_label' => $this->entitlementService->modeLabel($downloadConfig['mode']),
                'url' => $this->downloadController->getTrackDownloadUrl($track->ID),
            ],
            'artwork_html' => $this->getArtworkHtml($track->ID, $artworkId),
            'album' => $album instanceof \WP_Post ? [
                'id' => $album->ID,
                'title' => get_the_title($album),
                'permalink' => is_string($albumPermalink) ? $albumPermalink : '',
            ] : null,
        ];
    }

    private function getArtworkHtml(int $trackId, int $artworkReferenceId): string
    {
        if ($artworkReferenceId > 0) {
            $html = wp_get_attachment_image($artworkReferenceId, 'medium');
            if (is_string($html) && $html !== '') {
                return $html;
            }
        }

        $fallback = get_the_post_thumbnail($trackId, 'medium');

        return is_string($fallback) ? $fallback : '';
    }

    private function getMetaString(int $postId, string $metaKey): string
    {
        $value = get_post_meta($postId, $metaKey, true);

        return is_string($value) ? $value : '';
    }
}
