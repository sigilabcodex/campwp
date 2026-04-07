<?php

declare(strict_types=1);

namespace CampWP\Frontend\Presentation;

use CampWP\Domain\Commerce\EntitlementService;
use CampWP\Domain\Commerce\WooIntegrationService;

final class DownloadCtaPresenter
{
    private EntitlementService $entitlementService;

    private WooIntegrationService $wooIntegration;

    public function __construct(?EntitlementService $entitlementService = null, ?WooIntegrationService $wooIntegration = null)
    {
        $this->entitlementService = $entitlementService ?? new EntitlementService();
        $this->wooIntegration = $wooIntegration ?? new WooIntegrationService();
    }

    /**
     * @param array{enabled: bool, mode: string, product_id: int} $config
     * @return array{state: string, label: string, message: string, action_label: string, action_url: string}
     */
    public function present(array $config, string $downloadUrl, bool $hasDownloadableFile, string $contextUrl = ''): array
    {
        if (! $config['enabled']) {
            return [
                'state' => 'disabled',
                'label' => __('Downloads disabled', 'campwp'),
                'message' => __('Downloads are not enabled for this item.', 'campwp'),
                'action_label' => '',
                'action_url' => '',
            ];
        }

        if (! $hasDownloadableFile) {
            return [
                'state' => 'missing_file',
                'label' => __('File unavailable', 'campwp'),
                'message' => __('A downloadable file is not currently attached.', 'campwp'),
                'action_label' => '',
                'action_url' => '',
            ];
        }

        $mode = (string) ($config['mode'] ?? EntitlementService::MODE_PUBLIC);
        $productId = (int) ($config['product_id'] ?? 0);

        if ($this->entitlementService->canCurrentUserDownload($mode, $productId)) {
            return [
                'state' => 'available',
                'label' => $this->entitlementService->modeLabel($mode),
                'message' => __('Download available now.', 'campwp'),
                'action_label' => __('Download', 'campwp'),
                'action_url' => $downloadUrl,
            ];
        }

        if ($mode === EntitlementService::MODE_RESTRICTED) {
            return [
                'state' => 'login_required',
                'label' => __('Login required', 'campwp'),
                'message' => __('Sign in to access this download.', 'campwp'),
                'action_label' => __('Log in', 'campwp'),
                'action_url' => wp_login_url($contextUrl),
            ];
        }

        if ($mode === EntitlementService::MODE_PURCHASE) {
            if (! $this->wooIntegration->isAvailable()) {
                return [
                    'state' => 'purchase_unavailable',
                    'label' => __('Purchase required', 'campwp'),
                    'message' => __('Purchasing is currently unavailable.', 'campwp'),
                    'action_label' => '',
                    'action_url' => '',
                ];
            }

            if ($productId <= 0) {
                return [
                    'state' => 'purchase_unavailable',
                    'label' => __('Purchase required', 'campwp'),
                    'message' => __('A purchase product is not configured yet.', 'campwp'),
                    'action_label' => '',
                    'action_url' => '',
                ];
            }

            $productUrl = get_permalink($productId);

            return [
                'state' => 'purchase_required',
                'label' => __('Purchase required', 'campwp'),
                'message' => __('Purchase this release to unlock the download.', 'campwp'),
                'action_label' => __('View purchase options', 'campwp'),
                'action_url' => is_string($productUrl) ? $productUrl : '',
            ];
        }

        return [
            'state' => 'unavailable',
            'label' => __('Download unavailable', 'campwp'),
            'message' => __('This download is not currently available.', 'campwp'),
            'action_label' => '',
            'action_url' => '',
        ];
    }
}
