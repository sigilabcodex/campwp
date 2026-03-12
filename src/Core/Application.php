<?php

declare(strict_types=1);

namespace CampWP\Core;

use CampWP\Admin\AdminService;
use CampWP\Domain\DomainService;
use CampWP\Infrastructure\InfrastructureService;
use CampWP\Integrations\IntegrationService;

final class Application
{
    /**
     * @var list<object>
     */
    private array $services;

    public function __construct()
    {
        $this->services = [
            new AdminService(),
            new DomainService(),
            new InfrastructureService(),
            new IntegrationService(),
        ];
    }

    public function run(): void
    {
        foreach ($this->services as $service) {
            if (method_exists($service, 'register')) {
                $service->register();
            }
        }
    }
}
