<?php

declare(strict_types=1);

namespace CampWP\Integrations;

final class IntegrationService
{
    public function register(): void
    {
        // Reserved integration bootstrap entrypoint.
        // WooCommerce is integrated through the entitlement layer and admin metadata fields.
    }
}
