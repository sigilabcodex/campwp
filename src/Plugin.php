<?php

namespace CampWP;

use CampWP\Integrations\WooCommerceBridge;
use CampWP\Media\MetadataExtractor;
use CampWP\Storage\LocalStorage;

class Plugin
{
    public function register(): void
    {
        (new PostTypes())->register();
        (new MetaBoxes())->register();
        (new LocalStorage())->register();
        (new MetadataExtractor())->register();
        (new Frontend())->register();
        (new WooCommerceBridge())->register();
    }
}
