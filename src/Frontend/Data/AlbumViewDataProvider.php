<?php

declare(strict_types=1);

namespace CampWP\Frontend\Data;

use CampWP\Domain\Audio\TrackAudioResolver;
use CampWP\Domain\Commerce\EntitlementService;
use CampWP\Domain\ContentModel\AlbumTrackRelationshipService;
use CampWP\Domain\Media\AlbumBonusAssetResolver;
use CampWP\Domain\Metadata\MetadataKeys;
use CampWP\Frontend\Download\DownloadController;
use CampWP\Infrastructure\Media\WordPressMediaLibraryProvider;

final class AlbumViewDataProvider
{
    private AlbumTrackRelationshipService $relationshipService;

    private TrackAudioResolver $trackAudioResolver;

    private AlbumBonusAssetResolver $bonusAssetResolver;

    private EntitlementService $entitlementService;

    private DownloadController $downloadController;

    public function __construct()
    {
        $mediaProvider = new WordPressMediaLibraryProvider();

        $this->relationshipService = new AlbumTrackRelationshipService();
        $this->trackAudioResolver = new TrackAudioResolver($mediaProvider);
        $this->bonusAssetResolver = new AlbumBonusAssetResolver($mediaProvider);
        $this->entitlementService = new EntitlementService();
        $this->downloadController = new DownloadController();
    }

    /**
     * @return array<string, mixed>
     */
    public function getAlbumViewData(\WP_Post $album): array
    {
        $subtitle = $this->getMetaString($album->ID, MetadataKeys::ALBUM_SUBTITLE);
        $artist = $this->getMetaString($album->ID, MetadataKeys::ALBUM_ARTIST_DISPLAY);
        $releaseDate = $this->getMetaString($album->ID, MetadataKeys::ALBUM_RELEASE_DATE);
        $releaseType = $this->normalizeReleaseType(
            $this->getMetaString($album->ID, MetadataKeys::ALBUM_RELEASE_TYPE)
        );
        $labelName = $this->getMetaString($album->ID, MetadataKeys::ALBUM_LABEL_NAME);
        $releaseNotes = $this->getMetaString($album->ID, MetadataKeys::ALBUM_RELEASE_NOTES);

        $albumDownloadConfig = $this->entitlementService->getAlbumDownloadConfig($album->ID);

        return [
            'id' => $album->ID,
            'title' => get_the_title($album),
            'subtitle' => $subtitle,
            'artist_display' => $artist,
            'release_date' => $releaseDate,
            'release_type' => $releaseType,
            'release_type_label' => $this->releaseTypeLabel($releaseType),
            'label_name' => $labelName,
            'release_notes' => $releaseNotes,
            'cover_html' => get_the_post_thumbnail($album, 'large'),
            'tracks' => $this->getTrackRows($album->ID, $artist),
            'bonus_assets' => $this->getBonusAssetRows($album->ID, $albumDownloadConfig),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function getTrackRows(int $albumId, string $albumArtist): array
    {
        $rows = [];

        foreach ($this->relationshipService->getTracksForAlbum($albumId) as $index => $trackPost) {
            $trackSubtitle = $this->getMetaString($trackPost->ID, MetadataKeys::TRACK_SUBTITLE);
            $trackArtistOverride = $this->getMetaString($trackPost->ID, MetadataKeys::TRACK_ARTIST_DISPLAY);
            $trackDuration = $this->getMetaString($trackPost->ID, MetadataKeys::TRACK_DURATION);
            $trackNumberMeta = max(0, (int) get_post_meta($trackPost->ID, MetadataKeys::TRACK_NUMBER, true));
            $trackArtworkId = max(0, (int) get_post_meta($trackPost->ID, MetadataKeys::TRACK_ARTWORK_ID, true));
            $audioFile = $this->trackAudioResolver->getTrackAudioFile($trackPost->ID);
            $downloadConfig = $this->entitlementService->getTrackDownloadConfig($trackPost->ID);

            $rows[] = [
                'id' => $trackPost->ID,
                'number' => $trackNumberMeta > 0 ? $trackNumberMeta : $index + 1,
                'title' => get_the_title($trackPost),
                'permalink' => get_permalink($trackPost),
                'subtitle' => $trackSubtitle,
                'artist_display' => $trackArtistOverride !== '' ? $trackArtistOverride : $albumArtist,
                'duration' => $trackDuration,
                'artwork_html' => $this->getTrackArtworkHtml($trackPost->ID, $trackArtworkId),
                'audio' => $audioFile,
                'download' => [
                    'enabled' => $downloadConfig['enabled'] && $audioFile !== null,
                    'mode' => $downloadConfig['mode'],
                    'mode_label' => $this->entitlementService->modeLabel($downloadConfig['mode']),
                    'url' => $this->downloadController->getTrackDownloadUrl($trackPost->ID),
                ],
            ];
        }

        return $rows;
    }


    /**
     * @param array{enabled: bool, mode: string, product_id: int} $downloadConfig
     * @return list<array<string, mixed>>
     */
    private function getBonusAssetRows(int $albumId, array $downloadConfig): array
    {
        $rows = [];

        foreach ($this->bonusAssetResolver->resolveBonusAssets($albumId) as $asset) {
            $rows[] = [
                'label' => $asset->getReference()->getLabel(),
                'fallback_label' => $asset->getAsset()->getTitle(),
                'download' => [
                    'enabled' => $downloadConfig['enabled'],
                    'mode' => $downloadConfig['mode'],
                    'mode_label' => $this->entitlementService->modeLabel($downloadConfig['mode']),
                    'url' => $this->downloadController->getAlbumBonusDownloadUrl($albumId, $asset->getAsset()->getReferenceId()),
                ],
            ];
        }

        return $rows;
    }

    private function getTrackArtworkHtml(int $trackId, int $artworkReferenceId): string
    {
        if ($artworkReferenceId > 0) {
            $imageHtml = wp_get_attachment_image($artworkReferenceId, 'thumbnail');
            if (is_string($imageHtml) && $imageHtml !== '') {
                return $imageHtml;
            }
        }

        $fallbackHtml = get_the_post_thumbnail($trackId, 'thumbnail');

        return is_string($fallbackHtml) ? $fallbackHtml : '';
    }

    private function getMetaString(int $postId, string $metaKey): string
    {
        $value = get_post_meta($postId, $metaKey, true);

        return is_string($value) ? $value : '';
    }

    private function normalizeReleaseType(string $releaseType): string
    {
        $allowedTypes = ['single', 'ep', 'album', 'compilation'];
        $normalized = sanitize_key($releaseType);

        if (in_array($normalized, $allowedTypes, true)) {
            return $normalized;
        }

        return 'album';
    }

    private function releaseTypeLabel(string $releaseType): string
    {
        $labels = [
            'single' => __('Single', 'campwp'),
            'ep' => __('EP', 'campwp'),
            'album' => __('Album', 'campwp'),
            'compilation' => __('Compilation', 'campwp'),
        ];

        return $labels[$releaseType] ?? __('Album', 'campwp');
    }
}
