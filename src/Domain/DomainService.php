<?php

declare(strict_types=1);

namespace CampWP\Domain;

use CampWP\Domain\Metadata\MetadataSchemaRegistrar;

final class DomainService
{
    public function register(): void
    {
        (new MetadataSchemaRegistrar())->register();
    }
}
