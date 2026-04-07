<?php

declare(strict_types=1);

namespace CampWP\Frontend\Download;

use CampWP\Domain\Commerce\DownloadResolver;

final class DownloadController
{
    public const QUERY_TYPE = 'campwp_download_type';
    public const QUERY_ID = 'campwp_download_id';

    private DownloadResolver $resolver;

    public function __construct(?DownloadResolver $resolver = null)
    {
        $this->resolver = $resolver ?? new DownloadResolver();
    }

    public function register(): void
    {
        add_action('init', [$this, 'registerRewriteRules']);
        add_filter('query_vars', [$this, 'registerQueryVars']);
        add_action('template_redirect', [$this, 'handleRequest']);
    }

    public function registerRewriteRules(): void
    {
        add_rewrite_rule('^campwp-download/(track|album-bonus)/([0-9]+)/?$', 'index.php?' . self::QUERY_TYPE . '=$matches[1]&' . self::QUERY_ID . '=$matches[2]', 'top');
    }

    /**
     * @param array<int, string> $queryVars
     * @return array<int, string>
     */
    public function registerQueryVars(array $queryVars): array
    {
        $queryVars[] = self::QUERY_TYPE;
        $queryVars[] = self::QUERY_ID;

        return $queryVars;
    }

    public function handleRequest(): void
    {
        $type = sanitize_key((string) get_query_var(self::QUERY_TYPE));
        $entityId = absint(get_query_var(self::QUERY_ID));

        if ($type === '' || $entityId <= 0) {
            return;
        }

        $asset = null;

        if ($type === 'track') {
            $asset = $this->resolver->resolveTrackDownload($entityId);
        } elseif ($type === 'album-bonus') {
            $referenceId = isset($_GET['ref']) ? absint(wp_unslash((string) $_GET['ref'])) : 0;
            if ($referenceId > 0) {
                $asset = $this->resolver->resolveAlbumBonusDownload($entityId, $referenceId);
            }
        }

        if ($asset === null) {
            status_header(403);
            wp_die(esc_html__('Download unavailable.', 'campwp'), esc_html__('Access denied', 'campwp'), ['response' => 403]);
        }

        wp_safe_redirect($asset->getUrl(), 302, 'CAMPWP');
        exit;
    }

    public function getTrackDownloadUrl(int $trackId): string
    {
        return home_url('/campwp-download/track/' . $trackId);
    }

    public function getAlbumBonusDownloadUrl(int $albumId, int $referenceId): string
    {
        return add_query_arg('ref', (string) $referenceId, home_url('/campwp-download/album-bonus/' . $albumId));
    }
}
