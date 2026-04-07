<?php

declare(strict_types=1);

namespace CampWP\Domain\Commerce;

final class WooIntegrationService
{
    public function isAvailable(): bool
    {
        return class_exists('WooCommerce') || function_exists('WC');
    }

    public function hasPurchasedProduct(int $userId, int $productId): bool
    {
        if (! $this->isAvailable() || $userId <= 0 || $productId <= 0) {
            return false;
        }

        $user = get_user_by('id', $userId);

        if (! $user instanceof \WP_User) {
            return false;
        }

        $email = $user->user_email;

        if (is_string($email) && $email !== '' && function_exists('wc_customer_bought_product') && wc_customer_bought_product($email, $userId, $productId)) {
            return true;
        }

        if (function_exists('wc_get_customer_available_downloads')) {
            $downloads = wc_get_customer_available_downloads($userId);

            if (is_array($downloads)) {
                foreach ($downloads as $download) {
                    if (! is_array($download)) {
                        continue;
                    }

                    if (isset($download['product_id']) && absint($download['product_id']) === $productId) {
                        return true;
                    }
                }
            }
        }

        return false;
    }
}
