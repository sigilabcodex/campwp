<?php

declare(strict_types=1);

namespace CampWP\Domain\Media;

final class ResolvedBonusAsset
{
    private BonusAssetReference $reference;

    private MediaAsset $asset;

    public function __construct(BonusAssetReference $reference, MediaAsset $asset)
    {
        $this->reference = $reference;
        $this->asset = $asset;
    }

    public function getReference(): BonusAssetReference
    {
        return $this->reference;
    }

    public function getAsset(): MediaAsset
    {
        return $this->asset;
    }
}
