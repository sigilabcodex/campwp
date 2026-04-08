<?php

declare(strict_types=1);

namespace CampWP\Infrastructure;

use CampWP\Infrastructure\Media\AudioUploadPolicyService;

final class InfrastructureService
{
    public function register(): void
    {
        (new AudioUploadPolicyService())->register();
    }
}
