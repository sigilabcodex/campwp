<?php

declare(strict_types=1);

namespace CampWP\Domain\Media;

use CampWP\Domain\Metadata\MetadataKeys;

final class AlbumBonusAssetResolver
{
    private const REFERENCE_TYPE_ATTACHMENT = 'wp_attachment';

    private MediaStorageProviderInterface $storageProvider;

    public function __construct(MediaStorageProviderInterface $storageProvider)
    {
        $this->storageProvider = $storageProvider;
    }

    /**
     * @return list<BonusAssetReference>
     */
    public function getBonusReferences(int $albumId): array
    {
        $rawValue = get_post_meta($albumId, MetadataKeys::ALBUM_BONUS_ITEMS, true);

        if (! is_string($rawValue) || $rawValue === '') {
            return [];
        }

        $decoded = json_decode($rawValue, true);

        if (! is_array($decoded)) {
            return [];
        }

        $references = [];

        foreach ($decoded as $item) {
            if (! is_array($item)) {
                continue;
            }

            $type = isset($item['type']) ? sanitize_key((string) $item['type']) : '';
            $referenceId = isset($item['reference_id']) ? absint($item['reference_id']) : 0;
            $label = isset($item['label']) ? sanitize_text_field((string) $item['label']) : '';

            if ($type !== self::REFERENCE_TYPE_ATTACHMENT || $referenceId <= 0) {
                continue;
            }

            if (! $this->storageProvider->isValidReference($referenceId)) {
                continue;
            }

            $references[] = new BonusAssetReference($type, $referenceId, $label);
        }

        return $references;
    }

    /**
     * @return list<ResolvedBonusAsset>
     */
    public function resolveBonusAssets(int $albumId): array
    {
        $resolved = [];

        foreach ($this->getBonusReferences($albumId) as $reference) {
            $asset = $this->storageProvider->resolve($reference->getReferenceId());

            if (! $asset instanceof MediaAsset) {
                continue;
            }

            $resolved[] = new ResolvedBonusAsset($reference, $asset);
        }

        return $resolved;
    }
}
