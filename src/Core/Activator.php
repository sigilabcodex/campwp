<?php

declare(strict_types=1);

namespace CampWP\Core;

use CampWP\Infrastructure\ContentModel\PostTypeRegistrar;

final class Activator
{
    public static function activate(): void
    {
        (new PostTypeRegistrar())->registerPostTypes();

        flush_rewrite_rules();
    }
}
