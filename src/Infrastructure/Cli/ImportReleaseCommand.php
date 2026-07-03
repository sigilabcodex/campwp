<?php

declare(strict_types=1);

namespace CampWP\Infrastructure\Cli;

use CampWP\Application\Import\ImportResult;
use CampWP\Application\Import\ReleaseImporter;
use CampWP\Application\Import\TrackImportResult;

final class ImportReleaseCommand
{
    private ReleaseImporter $importer;

    /** @var callable(string,bool):ImportResult|null */
    private $importCallback;

    /** @param callable(string,bool):ImportResult|null $importCallback */
    public function __construct(?ReleaseImporter $importer = null, ?callable $importCallback = null)
    {
        $this->importer = $importer ?? new ReleaseImporter();
        $this->importCallback = $importCallback;
    }

    /**
     * @param list<string> $args
     * @param array<string, mixed> $assocArgs
     */
    public function __invoke(array $args, array $assocArgs): void
    {
        $exitCode = $this->run($args, $assocArgs);
        if ($exitCode !== 0 && method_exists('WP_CLI', 'halt')) {
            \WP_CLI::halt($exitCode);
        }
    }

    /**
     * @param list<string> $args
     * @param array<string, mixed> $assocArgs
     */
    public function run(array $args, array $assocArgs): int
    {
        $format = (string) ($assocArgs['format'] ?? 'table');
        $validationError = $this->validateArguments($args, $assocArgs);
        if ($validationError !== '') {
            return $this->renderCommandError($validationError, $format);
        }

        $dryRun = $this->isEnabledModeFlag($assocArgs['dry-run'] ?? null);

        try {
            $result = $this->import((string) $args[0], $dryRun);
        } catch (\Throwable $throwable) {
            return $this->renderUnexpectedException($throwable, $format);
        }

        $this->renderResult($result, $format);

        if ($result->errors !== []) {
            return 1;
        }

        if (! $dryRun && in_array($result->status, ['partial', 'failed'], true)) {
            return 1;
        }

        return 0;
    }

    /**
     * @param list<string> $args
     * @param array<string, mixed> $assocArgs
     */
    private function validateArguments(array $args, array $assocArgs): string
    {
        if (count($args) !== 1 || trim((string) ($args[0] ?? '')) === '') {
            return 'A local manifest JSON path is required.';
        }

        $allowedFlags = ['dry-run', 'apply', 'format'];
        foreach (array_keys($assocArgs) as $flag) {
            if (! in_array((string) $flag, $allowedFlags, true)) {
                return sprintf('Unsupported option --%s.', (string) $flag);
            }
        }

        foreach (['dry-run', 'apply'] as $modeFlag) {
            if (array_key_exists($modeFlag, $assocArgs) && ! $this->isRecognizedModeFlagValue($assocArgs[$modeFlag])) {
                return sprintf('Unsupported value for --%s. Use it as a boolean flag.', $modeFlag);
            }
        }

        $hasDryRun = array_key_exists('dry-run', $assocArgs) && $this->isEnabledModeFlag($assocArgs['dry-run']);
        $hasApply = array_key_exists('apply', $assocArgs) && $this->isEnabledModeFlag($assocArgs['apply']);
        if ($hasDryRun === $hasApply) {
            return 'Select exactly one execution mode: --dry-run or --apply.';
        }

        $format = (string) ($assocArgs['format'] ?? 'table');
        if (! in_array($format, ['table', 'json'], true)) {
            return 'Unsupported format. Use --format=table or --format=json.';
        }

        return '';
    }

    private function import(string $path, bool $dryRun): ImportResult
    {
        if ($this->importCallback !== null) {
            return ($this->importCallback)($path, $dryRun);
        }

        return $this->importer->importLocalFile($path, $dryRun);
    }

    private function renderResult(ImportResult $result, string $format): void
    {
        if ($format === 'json') {
            $payload = $result->toArray();
            if (($payload['errors'] ?? []) !== [] && ($payload['status'] ?? '') === 'dry_run') {
                $payload['status'] = 'failed';
            }
            \WP_CLI::line(wp_json_encode($payload));
            return;
        }

        \WP_CLI::line('Source: ' . ($result->sourceReleaseIdentity !== '' ? $result->sourceReleaseIdentity : '(unknown)'));
        \WP_CLI::line('Status: ' . $result->status);
        \WP_CLI::line(sprintf('Album: %s%s', $result->albumAction, $result->albumPostId > 0 ? sprintf(' (#%d)', $result->albumPostId) : ''));
        \WP_CLI::line(sprintf(
            'Counts: %d created, %d updated, %d unchanged',
            $result->createdCount,
            $result->updatedCount,
            $result->unchangedCount
        ));

        if ($result->trackResults !== []) {
            \WP_CLI::line('Tracks:');
            foreach ($result->trackResults as $trackResult) {
                if (! $trackResult instanceof TrackImportResult) {
                    continue;
                }

                \WP_CLI::line(sprintf(
                    '  %s  %s%s',
                    $trackResult->externalTrackId,
                    $trackResult->action,
                    $trackResult->postId > 0 ? sprintf(' (#%d)', $trackResult->postId) : ''
                ));
            }
        }

        \WP_CLI::line(sprintf('Warnings: %d', count($result->warnings)));
        foreach ($result->warnings as $warning) {
            \WP_CLI::warning($warning);
        }

        \WP_CLI::line(sprintf('Errors: %d', count($result->errors)));
        foreach ($result->errors as $error) {
            \WP_CLI::error($error, false);
        }
    }

    private function renderCommandError(string $message, string $format): int
    {
        if ($format === 'json') {
            $this->renderJsonError('failed', [$message]);
            return 1;
        }

        return $this->error($message);
    }

    private function renderUnexpectedException(\Throwable $throwable, string $format): int
    {
        $message = 'Unexpected import failure.';
        if ($format === 'json') {
            $this->renderJsonError('failed', [$message]);
            return 1;
        }

        \WP_CLI::error($message, false);
        return 1;
    }

    /** @param list<string> $errors */
    private function renderJsonError(string $status, array $errors): void
    {
        \WP_CLI::line(wp_json_encode([
            'album_post_id' => 0,
            'album_action' => 'failed',
            'tracks' => [],
            'created_count' => 0,
            'updated_count' => 0,
            'unchanged_count' => 0,
            'warnings' => [],
            'errors' => $errors,
            'source_release_identity' => '',
            'dry_run' => false,
            'status' => $status,
        ]));
    }

    private function isRecognizedModeFlagValue(mixed $value): bool
    {
        if (is_bool($value) || $value === null || is_int($value)) {
            return in_array($value, [true, false, 1, 0, null], true);
        }

        if (! is_string($value)) {
            return false;
        }

        return in_array(strtolower(trim($value)), ['', '1', '0', 'false', 'no'], true);
    }

    private function isEnabledModeFlag(mixed $value): bool
    {
        if ($value === true || $value === 1 || $value === '1' || $value === '') {
            return true;
        }

        return false;
    }

    private function error(string $message): int
    {
        \WP_CLI::error($message, false);
        return 1;
    }
}
