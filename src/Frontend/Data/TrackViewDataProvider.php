<?php

declare(strict_types=1);

namespace CampWP\Frontend\Data;

use CampWP\Domain\Audio\TrackAudioResolver;
use CampWP\Domain\Commerce\EntitlementService;
use CampWP\Domain\ContentModel\TrackMetadataInheritanceService;
use CampWP\Frontend\Download\DownloadController;
use CampWP\Domain\Metadata\MetadataKeys;
use CampWP\Frontend\Presentation\DownloadCtaPresenter;
use CampWP\Infrastructure\Media\WordPressMediaLibraryProvider;

final class TrackViewDataProvider
{
    private TrackAudioResolver $trackAudioResolver;
    private EntitlementService $entitlementService;
    private DownloadController $downloadController;
    private DownloadCtaPresenter $downloadCtaPresenter;
    private TrackMetadataInheritanceService $inheritance;

    public function __construct()
    {
        $this->trackAudioResolver = new TrackAudioResolver(new WordPressMediaLibraryProvider());
        $this->entitlementService = new EntitlementService();
        $this->downloadController = new DownloadController();
        $this->downloadCtaPresenter = new DownloadCtaPresenter($this->entitlementService);
        $this->inheritance = new TrackMetadataInheritanceService();
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
        $trackPermalink = get_permalink($track);

        $trackArtist = $this->getMetaString($track->ID, MetadataKeys::TRACK_ARTIST_DISPLAY);
        $trackCredits = $this->getMetaString($track->ID, MetadataKeys::TRACK_CREDITS);
        $defaults = $this->inheritance->getReleaseDefaults($album instanceof \WP_Post ? $album->ID : 0);

        $audio = $this->trackAudioResolver->getTrackPlaybackFile($track->ID);
        $downloadFile = $this->trackAudioResolver->getTrackDownloadFile($track->ID);
        $downloadUrl = $this->downloadController->getTrackDownloadUrl($track->ID);
        if ($this->trackAudioResolver->getTrackSourceType($track->ID) === 'external_url' && $downloadFile instanceof \CampWP\Domain\Audio\TrackAudioFile) {
            $downloadUrl = $downloadFile->getUrl();
        }
        $downloadConfig = $this->entitlementService->getTrackDownloadConfig($track->ID);

        $data = [
            'id' => $track->ID,
            'title' => get_the_title($track),
            'subtitle' => $this->getMetaString($track->ID, MetadataKeys::TRACK_SUBTITLE),
            'artist_display' => $trackArtist !== '' ? $trackArtist : $defaults['artist_display_name'],
            'duration' => $this->getMetaString($track->ID, MetadataKeys::TRACK_DURATION),
            'isrc' => $this->getMetaString($track->ID, MetadataKeys::TRACK_ISRC),
            'credits' => $trackCredits !== '' ? $trackCredits : $defaults['credits'],
            'lyrics' => $this->getMetaString($track->ID, MetadataKeys::TRACK_LYRICS),
            'audio' => $audio,
            'cta' => $this->downloadCtaPresenter->present(
                $downloadConfig,
                $downloadUrl,
                $downloadFile !== null,
                is_string($trackPermalink) ? $trackPermalink : ''
            ),
            'artwork_html' => $this->getArtworkHtml($track->ID, $artworkId, $album instanceof \WP_Post ? $album->ID : 0),
            'album' => $album instanceof \WP_Post ? [
                'id' => $album->ID,
                'title' => get_the_title($album),
                'permalink' => is_string($albumPermalink) ? $albumPermalink : '',
            ] : null,
        ];

        return apply_filters('campwp_track_view_data', $data, $track);
    }

    private function getArtworkHtml(int $trackId, int $artworkReferenceId, int $albumId): string
    {
        if ($artworkReferenceId > 0) {
            $html = wp_get_attachment_image($artworkReferenceId, 'large');
            if (is_string($html) && $html !== '') {
                return $html;
            }
        }

        $fallbackTrack = get_the_post_thumbnail($trackId, 'large');
        if (is_string($fallbackTrack) && $fallbackTrack !== '') {
            return $fallbackTrack;
        }

        if ($albumId > 0) {
            $fallbackAlbum = get_the_post_thumbnail($albumId, 'large');
            if (is_string($fallbackAlbum)) {
                return $fallbackAlbum;
            }
        }

        return '';
    }

    private function getMetaString(int $postId, string $metaKey): string
    {
        $value = get_post_meta($postId, $metaKey, true);

        return is_string($value) ? $value : '';
    }
}
