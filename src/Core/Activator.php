<?php

declare(strict_types=1);

namespace CampWP\Core;

use CampWP\Domain\ContentModel\PostTypeRegistrar;
use CampWP\Frontend\Download\DownloadController;

final class Activator
{
    public static function activate(): void
    {
        (new PostTypeRegistrar())->registerPostTypes();
        DownloadController::registerRouteRewriteRules();
        update_option('campwp_download_rewrite_version', '1', false);

        flush_rewrite_rules();
    }
}
