<?php

declare(strict_types=1);

namespace CampWP\Domain\Commerce;

use CampWP\Domain\Metadata\MetadataKeys;

final class EntitlementService
{
    public const MODE_PUBLIC = 'public';
    public const MODE_RESTRICTED = 'restricted';
    public const MODE_PURCHASE = 'purchase';

    private WooIntegrationService $wooIntegration;

    public function __construct(?WooIntegrationService $wooIntegration = null)
    {
        $this->wooIntegration = $wooIntegration ?? new WooIntegrationService();
    }

    /**
     * @return array{enabled: bool, mode: string, product_id: int}
     */
    public function getTrackDownloadConfig(int $trackId): array
    {
        $albumId = absint(get_post_meta($trackId, MetadataKeys::TRACK_ALBUM_ID, true));

        $trackEnabled = get_post_meta($trackId, MetadataKeys::TRACK_DOWNLOAD_ENABLED, true);
        $trackMode = sanitize_key((string) get_post_meta($trackId, MetadataKeys::TRACK_DOWNLOAD_MODE, true));
        $trackProductId = absint(get_post_meta($trackId, MetadataKeys::TRACK_PRODUCT_ID, true));

        if ($trackEnabled === '' && $trackMode === '' && $trackProductId === 0 && $albumId > 0) {
            return $this->getAlbumDownloadConfig($albumId);
        }

        return [
            'enabled' => $this->normalizeEnabled($trackEnabled, true),
            'mode' => $this->normalizeMode($trackMode),
            'product_id' => $trackProductId,
        ];
    }

    /**
     * @return array{enabled: bool, mode: string, product_id: int}
     */
    public function getAlbumDownloadConfig(int $albumId): array
    {
        return [
            'enabled' => $this->normalizeEnabled(get_post_meta($albumId, MetadataKeys::ALBUM_DOWNLOAD_ENABLED, true), false),
            'mode' => $this->normalizeMode((string) get_post_meta($albumId, MetadataKeys::ALBUM_DOWNLOAD_MODE, true)),
            'product_id' => absint(get_post_meta($albumId, MetadataKeys::ALBUM_PRODUCT_ID, true)),
        ];
    }

    public function canCurrentUserDownload(string $mode, int $productId): bool
    {
        $normalizedMode = $this->normalizeMode($mode);

        if ($normalizedMode === self::MODE_PUBLIC) {
            return true;
        }

        if (! is_user_logged_in()) {
            return false;
        }

        if ($normalizedMode === self::MODE_RESTRICTED) {
            return true;
        }

        if ($normalizedMode === self::MODE_PURCHASE) {
            if (! $this->wooIntegration->isAvailable() || $productId <= 0) {
                return false;
            }

            return $this->wooIntegration->hasPurchasedProduct(get_current_user_id(), $productId);
        }

        return false;
    }

    public function modeLabel(string $mode): string
    {
        return match ($this->normalizeMode($mode)) {
            self::MODE_RESTRICTED => __('Login required', 'campwp'),
            self::MODE_PURCHASE => __('Purchase required', 'campwp'),
            default => __('Free download', 'campwp'),
        };
    }

    public function isModeSelectable(string $mode): bool
    {
        $normalizedMode = $this->normalizeMode($mode);

        if ($normalizedMode !== self::MODE_PURCHASE) {
            return true;
        }

        return $this->wooIntegration->isAvailable();
    }

    private function normalizeMode(string $mode): string
    {
        $normalized = sanitize_key($mode);

        if (in_array($normalized, [self::MODE_PUBLIC, self::MODE_RESTRICTED, self::MODE_PURCHASE], true)) {
            return $normalized;
        }

        return self::MODE_PUBLIC;
    }

    /**
     * @param mixed $value
     */
    private function normalizeEnabled($value, bool $fallback): bool
    {
        if ($value === '' || $value === null) {
            return $fallback;
        }

        return in_array((string) $value, ['1', 'yes', 'on', 'true'], true);
    }
}
