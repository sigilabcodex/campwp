<?php

declare(strict_types=1);

namespace CampWP\Infrastructure;

use CampWP\Infrastructure\ContentModel\PostTypeRegistrar;

final class InfrastructureService
{
    public function register(): void
    {
        (new PostTypeRegistrar())->register();
    }
}
