<?php

declare(strict_types=1);

namespace CampWP\Infrastructure\Cli;

final class CliService
{
    private static bool $registered = false;

    private ?bool $isWpCli;

    public function __construct(?bool $isWpCli = null)
    {
        $this->isWpCli = $isWpCli;
    }

    public function register(): void
    {
        if (! $this->isWpCliAvailable() || self::$registered) {
            return;
        }

        \WP_CLI::add_command('campwp import-release', [new ImportReleaseCommand(), '__invoke']);
        self::$registered = true;
    }

    public static function resetRegistrationForTests(): void
    {
        self::$registered = false;
    }

    private function isWpCliAvailable(): bool
    {
        if ($this->isWpCli !== null) {
            return $this->isWpCli && class_exists('WP_CLI');
        }

        return defined('WP_CLI') && WP_CLI && class_exists('WP_CLI');
    }
}
