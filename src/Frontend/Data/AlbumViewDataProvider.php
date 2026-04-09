<?php

declare(strict_types=1);

namespace CampWP\Frontend\Data;

use CampWP\Domain\Audio\TrackAudioResolver;
use CampWP\Domain\Commerce\EntitlementService;
use CampWP\Domain\ContentModel\AlbumTrackRelationshipService;
use CampWP\Domain\ContentModel\TrackMetadataInheritanceService;
use CampWP\Domain\Media\AlbumBonusAssetResolver;
use CampWP\Domain\Metadata\MetadataKeys;
use CampWP\Frontend\Download\DownloadController;
use CampWP\Frontend\Presentation\DownloadCtaPresenter;
use CampWP\Infrastructure\Media\WordPressMediaLibraryProvider;

final class AlbumViewDataProvider
{
    private AlbumTrackRelationshipService $relationshipService;
    private TrackAudioResolver $trackAudioResolver;
    private AlbumBonusAssetResolver $bonusAssetResolver;
    private EntitlementService $entitlementService;
    private DownloadController $downloadController;
    private DownloadCtaPresenter $downloadCtaPresenter;
    private TrackMetadataInheritanceService $inheritance;

    public function __construct()
    {
        $mediaProvider = new WordPressMediaLibraryProvider();

        $this->relationshipService = new AlbumTrackRelationshipService();
        $this->trackAudioResolver = new TrackAudioResolver($mediaProvider);
        $this->bonusAssetResolver = new AlbumBonusAssetResolver($mediaProvider);
        $this->entitlementService = new EntitlementService();
        $this->downloadController = new DownloadController();
        $this->downloadCtaPresenter = new DownloadCtaPresenter($this->entitlementService);
        $this->inheritance = new TrackMetadataInheritanceService();
    }

    /**
     * @return array<string, mixed>
     */
    public function getAlbumViewData(\WP_Post $album): array
    {
        $releaseDefaults = $this->inheritance->getReleaseDefaults($album->ID);
        $subtitle = $this->getMetaString($album->ID, MetadataKeys::ALBUM_SUBTITLE);
        $artist = $this->getMetaString($album->ID, MetadataKeys::ALBUM_ARTIST_DISPLAY);
        $releaseDate = $this->getMetaString($album->ID, MetadataKeys::ALBUM_RELEASE_DATE);
        $releaseType = $this->normalizeReleaseType(
            $this->getMetaString($album->ID, MetadataKeys::ALBUM_RELEASE_TYPE)
        );
        $labelName = $this->getMetaString($album->ID, MetadataKeys::ALBUM_LABEL_NAME);
        $releaseNotes = $this->getMetaString($album->ID, MetadataKeys::ALBUM_RELEASE_NOTES);
        $credits = $this->getMetaString($album->ID, MetadataKeys::ALBUM_CREDITS_OVERRIDE);

        $albumDownloadConfig = $this->entitlementService->getAlbumDownloadConfig($album->ID);
        $bonusAssets = $this->getBonusAssetRows($album->ID, $albumDownloadConfig);

        $albumPermalink = get_permalink($album);

        $data = [
            'id' => $album->ID,
            'title' => get_the_title($album),
            'subtitle' => $subtitle,
            'artist_display' => $artist !== '' ? $artist : $releaseDefaults['artist_display_name'],
            'release_date' => $releaseDate,
            'release_type' => $releaseType,
            'release_type_label' => $this->releaseTypeLabel($releaseType),
            'label_name' => $labelName !== '' ? $labelName : $releaseDefaults['label_name'],
            'release_notes' => $releaseNotes,
            'credits' => $credits !== '' ? $credits : $releaseDefaults['credits'],
            'cover_html' => get_the_post_thumbnail($album, 'large'),
            'tracks' => $this->getTrackRows($album->ID),
            'bonus_assets' => $bonusAssets,
            'cta' => $this->downloadCtaPresenter->present(
                $albumDownloadConfig,
                '',
                $bonusAssets !== [],
                is_string($albumPermalink) ? $albumPermalink : ''
            ),
        ];

        return apply_filters('campwp_album_view_data', $data, $album);
    }


/**
 * @return list<array<string, mixed>>
 */
private function getTrackRows(int $albumId): array
{
    $rows = [];
    $defaults = $this->inheritance->getReleaseDefaults($albumId);

    foreach ($this->relationshipService->getTracksForAlbum($albumId) as $index => $trackPost) {
        if ($trackPost->post_status !== 'publish') {
            continue;
        }

        $trackSubtitle = $this->getMetaString($trackPost->ID, MetadataKeys::TRACK_SUBTITLE);
        $trackArtistOverride = $this->getMetaString($trackPost->ID, MetadataKeys::TRACK_ARTIST_DISPLAY);
        $trackDuration = $this->getMetaString($trackPost->ID, MetadataKeys::TRACK_DURATION);
        $trackNumberMeta = max(0, (int) get_post_meta($trackPost->ID, MetadataKeys::TRACK_NUMBER, true));
        $trackArtworkId = max(0, (int) get_post_meta($trackPost->ID, MetadataKeys::TRACK_ARTWORK_ID, true));
        $audioFile = $this->trackAudioResolver->getTrackAudioFile($trackPost->ID);
        $downloadConfig = $this->entitlementService->getTrackDownloadConfig($trackPost->ID);
        $trackPermalink = get_permalink($trackPost);

        $rows[] = [
            'id' => $trackPost->ID,
            'number' => $trackNumberMeta > 0 ? $trackNumberMeta : $index + 1,
            'title' => get_the_title($trackPost),
            'permalink' => is_string($trackPermalink) ? $trackPermalink : '',
            'subtitle' => $trackSubtitle,
            'artist_display' => $trackArtistOverride !== '' ? $trackArtistOverride : $defaults['artist_display_name'],
            'duration' => $trackDuration,
            'artwork_html' => $this->getTrackArtworkHtml($trackPost->ID, $trackArtworkId),
            'audio' => $audioFile,
            'cta' => $this->downloadCtaPresenter->present(
                $downloadConfig,
                $this->downloadController->getTrackDownloadUrl($trackPost->ID),
                $audioFile !== null,
                is_string($trackPermalink) ? $trackPermalink : ''
            ),
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
        $albumPermalink = get_permalink($albumId);

        foreach ($this->bonusAssetResolver->resolveBonusAssets($albumId) as $asset) {
            $rows[] = [
                'label' => $asset->getReference()->getLabel(),
                'fallback_label' => $asset->getAsset()->getTitle(),
                'cta' => $this->downloadCtaPresenter->present(
                    $downloadConfig,
                    $this->downloadController->getAlbumBonusDownloadUrl($albumId, $asset->getAsset()->getReferenceId()),
                    true,
                    is_string($albumPermalink) ? $albumPermalink : ''
                ),
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
