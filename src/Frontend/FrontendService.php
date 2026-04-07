<?php

declare(strict_types=1);

namespace CampWP\Frontend;

use CampWP\Frontend\Data\AlbumViewDataProvider;
use CampWP\Frontend\Data\TrackViewDataProvider;
use CampWP\Frontend\Download\DownloadController;
use CampWP\Frontend\Rendering\AlbumPageRenderer;
use CampWP\Frontend\Rendering\SingleContentFilter;
use CampWP\Frontend\Rendering\TrackPageRenderer;

final class FrontendService
{
    public function register(): void
    {
        $albumDataProvider = new AlbumViewDataProvider();
        $trackDataProvider = new TrackViewDataProvider();

        (new DownloadController())->register();

        $contentFilter = new SingleContentFilter(
            new AlbumPageRenderer($albumDataProvider),
            new TrackPageRenderer($trackDataProvider)
        );

        $contentFilter->register();

        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    public function enqueueAssets(): void
    {
        if (! is_singular(['campwp_album', 'campwp_track'])) {
            return;
        }

        wp_enqueue_style(
            'campwp-frontend',
            CAMPWP_URL . 'assets/css/campwp-frontend.css',
            [],
            defined('CAMPWP_VERSION') ? CAMPWP_VERSION : '0.1.0'
        );
    }
}
