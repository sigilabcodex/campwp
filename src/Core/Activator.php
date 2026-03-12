<?php

declare(strict_types=1);

namespace CampWP\Core;

final class Activator
{
    public static function activate(): void
    {
        flush_rewrite_rules();
    }
}
