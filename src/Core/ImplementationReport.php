<?php

declare(strict_types=1);

namespace CampWP\Core;

final class ImplementationReport
{
    public function register(): void
    {
        add_action('admin_init', [$this, 'logReport']);
        add_action('admin_notices', [$this, 'renderNotice']);
    }

    public function logReport(): void
    {
        if (! defined('WP_DEBUG') || ! WP_DEBUG) {
            return;
        }

        error_log('[CAMPWP] Download and entitlement layer enabled: components=EntitlementService,DownloadResolver,DownloadController,WooIntegrationService; modes=public|restricted|purchase; woo=optional; limitations=redirect-only,no-counters,no-rest; next=tokenized-urls,download-logs,pwyw.');
    }

    public function renderNotice(): void
    {
        if (! defined('WP_DEBUG') || ! WP_DEBUG || ! current_user_can('manage_options')) {
            return;
        }

        echo '<div class="notice notice-info"><p><strong>CAMPWP dev report:</strong> download + entitlement foundation loaded (public/restricted/purchase, Woo optional, secure routed downloads). Current limitations: redirect-only delivery, no counters, no tokenized links. Next: signed URLs, counters, pay-what-you-want.</p></div>';
    }
}
