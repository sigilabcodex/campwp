<?php

declare(strict_types=1);

namespace CampWP\Integrations;

use CampWP\Integrations\WooCommerce\WooCommerceService;

final class IntegrationService
{
    public function register(): void
    {
        if (! $this->isWooCommerceAvailable()) {
            return;
        }

        (new WooCommerceService())->register();
    }

    private function isWooCommerceAvailable(): bool
    {
        return class_exists('WooCommerce') || function_exists('WC');
    }
}
