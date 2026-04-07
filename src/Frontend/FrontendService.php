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
    }
}
