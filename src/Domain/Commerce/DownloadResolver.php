<?php

declare(strict_types=1);

namespace CampWP\Domain\Commerce;

use CampWP\Domain\Audio\TrackAudioResolver;
use CampWP\Domain\Media\AlbumBonusAssetResolver;
use CampWP\Domain\Media\MediaAsset;
use CampWP\Infrastructure\Media\WordPressMediaLibraryProvider;

final class DownloadResolver
{
    private EntitlementService $entitlements;

    private TrackAudioResolver $trackAudioResolver;

    private AlbumBonusAssetResolver $bonusResolver;

    private WordPressMediaLibraryProvider $mediaProvider;

    public function __construct(?EntitlementService $entitlements = null)
    {
        $this->entitlements = $entitlements ?? new EntitlementService();
        $this->mediaProvider = new WordPressMediaLibraryProvider();
        $this->trackAudioResolver = new TrackAudioResolver($this->mediaProvider);
        $this->bonusResolver = new AlbumBonusAssetResolver($this->mediaProvider);
    }

    public function resolveTrackDownload(int $trackId): ?MediaAsset
    {
        $post = get_post($trackId);

        if (! $post instanceof \WP_Post || $post->post_status !== 'publish') {
            return null;
        }

        $config = $this->entitlements->getTrackDownloadConfig($trackId);

        if (! $config['enabled'] || ! $this->entitlements->canCurrentUserDownload($config['mode'], $config['product_id'])) {
            return null;
        }

        $referenceId = $this->trackAudioResolver->getTrackAudioReferenceId($trackId);

        if ($referenceId <= 0) {
            return null;
        }

        return $this->mediaProvider->resolve($referenceId);
    }

    public function resolveAlbumBonusDownload(int $albumId, int $referenceId): ?MediaAsset
    {
        $post = get_post($albumId);

        if (! $post instanceof \WP_Post || $post->post_status !== 'publish') {
            return null;
        }

        $config = $this->entitlements->getAlbumDownloadConfig($albumId);

        if (! $config['enabled'] || ! $this->entitlements->canCurrentUserDownload($config['mode'], $config['product_id'])) {
            return null;
        }

        $isAllowedReference = false;

        foreach ($this->bonusResolver->getBonusReferences($albumId) as $reference) {
            if ($reference->getReferenceId() === $referenceId) {
                $isAllowedReference = true;
                break;
            }
        }

        if (! $isAllowedReference) {
            return null;
        }

        return $this->mediaProvider->resolve($referenceId);
    }
}
