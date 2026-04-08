<?php

declare(strict_types=1);

namespace CampWP\Core;

final class ImplementationReport
{
    private const DISMISS_META_KEY = '_campwp_dev_report_dismissed';

    public function register(): void
    {
        add_action('admin_init', [$this, 'logReport']);
        add_action('admin_notices', [$this, 'renderNotice']);
        add_action('wp_ajax_campwp_dismiss_dev_report', [$this, 'dismissNotice']);
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

        $userId = get_current_user_id();
        if ($userId > 0 && get_user_meta($userId, self::DISMISS_META_KEY, true) === '1') {
            return;
        }

        $nonce = wp_create_nonce('campwp_dismiss_dev_report');
        $ajaxUrl = esc_url_raw(admin_url('admin-ajax.php'));

        echo '<div class="notice notice-info is-dismissible campwp-dev-report-notice" data-dismiss-nonce="' . esc_attr($nonce) . '"><p><strong>CAMPWP dev report:</strong> download + entitlement foundation loaded (public/restricted/purchase, Woo optional, secure routed downloads). Current limitations: redirect-only delivery, no counters, no tokenized links. Next: signed URLs, counters, pay-what-you-want.</p></div>';

        $script = <<<JS
jQuery(function($){
    $(document).on('click', '.campwp-dev-report-notice .notice-dismiss', function(){
        var notice = $(this).closest('.campwp-dev-report-notice');
        var nonce = notice.data('dismiss-nonce');

        $.post('{$ajaxUrl}', {
            action: 'campwp_dismiss_dev_report',
            nonce: nonce
        });
    });
});
JS;

        wp_add_inline_script('jquery', $script);
    }

    public function dismissNotice(): void
    {
        if (! current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'forbidden'], 403);
        }

        $nonce = sanitize_text_field(wp_unslash((string) ($_POST['nonce'] ?? '')));
        if (! wp_verify_nonce($nonce, 'campwp_dismiss_dev_report')) {
            wp_send_json_error(['message' => 'invalid_nonce'], 403);
        }

        $userId = get_current_user_id();
        if ($userId > 0) {
            update_user_meta($userId, self::DISMISS_META_KEY, '1');
        }

        wp_send_json_success();
    }
}
