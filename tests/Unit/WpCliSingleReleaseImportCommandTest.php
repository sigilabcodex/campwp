<?php

declare(strict_types=1);

namespace CampWP\Tests\Unit;

use CampWP\Application\Import\ImportResult;
use CampWP\Domain\Metadata\MetadataKeys;
use CampWP\Infrastructure\Cli\CliService;
use CampWP\Infrastructure\Cli\ImportReleaseCommand;
use PHPUnit\Framework\TestCase;

final class WpCliSingleReleaseImportCommandTest extends TestCase
{
    protected function setUp(): void
    {
        \WP_CLI::reset();
        CliService::resetRegistrationForTests();
        $GLOBALS['campwp_test_posts'] = [];
        $GLOBALS['campwp_test_meta'] = [];
        $GLOBALS['campwp_test_post_types'] = [];
        $GLOBALS['campwp_inserted_posts'] = [];
        $GLOBALS['campwp_updated_posts'] = [];
        $GLOBALS['campwp_deleted_meta'] = [];
        $GLOBALS['campwp_test_next_post_id'] = 1000;
        $GLOBALS['campwp_test_thumbnail_ids'] = [];
        $GLOBALS['campwp_test_meta_write_failures'] = [];
        $GLOBALS['campwp_test_update_post_meta_return_false'] = false;
        $GLOBALS['campwp_test_wp_insert_post_fail_titles'] = [];
        $GLOBALS['campwp_test_wp_update_post_fail_titles'] = [];
    }

    public function testCommandRegistersWhenWpCliIsAvailable(): void
    {
        (new CliService(true))->register();

        self::assertArrayHasKey('campwp import-release', \WP_CLI::$commands);
        self::assertIsCallable(\WP_CLI::$commands['campwp import-release']);
        self::assertSame(['campwp import-release'], \WP_CLI::$addCommandCalls);
    }

    public function testRepeatedRegistrationIsIdempotentAcrossInstances(): void
    {
        (new CliService(true))->register();
        (new CliService(true))->register();
        (new CliService(true))->register();

        self::assertArrayHasKey('campwp import-release', \WP_CLI::$commands);
        self::assertSame(['campwp import-release'], \WP_CLI::$addCommandCalls);
    }

    public function testCommandDoesNotRegisterWhenWpCliIsUnavailable(): void
    {
        (new CliService(false))->register();

        self::assertSame([], \WP_CLI::$commands);
    }

    public function testDefaultCliServiceDoesNotRegisterWhenWpCliConstantIsUnavailable(): void
    {
        (new CliService())->register();

        self::assertArrayNotHasKey('campwp import-release', \WP_CLI::$commands);
    }

    public function testMissingPathIsRejected(): void
    {
        $exit = $this->command()->run([], ['dry-run' => true]);

        self::assertSame(1, $exit);
        self::assertContains('A local manifest JSON path is required.', \WP_CLI::$errors);
    }

    public function testMissingModeIsRejected(): void
    {
        $exit = $this->command()->run([$this->fixturePath()], []);

        self::assertSame(1, $exit);
        self::assertContains('Select exactly one execution mode: --dry-run or --apply.', \WP_CLI::$errors);
    }

    public function testBothModesAreRejected(): void
    {
        $exit = $this->command()->run([$this->fixturePath()], ['dry-run' => true, 'apply' => true]);

        self::assertSame(1, $exit);
        self::assertContains('Select exactly one execution mode: --dry-run or --apply.', \WP_CLI::$errors);
    }

    /**
     * @dataProvider falseModeFlagValues
     */
    public function testFalseValuedSingleModeFlagsDoNotEnableMode(mixed $value): void
    {
        $exit = $this->command()->run([$this->fixturePath()], ['dry-run' => $value]);

        self::assertSame(1, $exit);
        self::assertContains('Select exactly one execution mode: --dry-run or --apply.', \WP_CLI::$errors);
    }

    /** @return list<array{mixed}> */
    public static function falseModeFlagValues(): array
    {
        return [[false], [0], ['0'], [null], ['false'], ['no']];
    }

    public function testOneTrueAndOneFalseModeIsAccepted(): void
    {
        $exit = $this->command()->run([$this->fixturePath()], ['dry-run' => true, 'apply' => false]);

        self::assertSame(0, $exit);
        self::assertSame([], $GLOBALS['campwp_test_posts']);
        self::assertStringContainsString('Status: dry_run', $this->cliOutput());
    }

    public function testBothFalseModesAreRejected(): void
    {
        $exit = $this->command()->run([$this->fixturePath()], ['dry-run' => false, 'apply' => false]);

        self::assertSame(1, $exit);
        self::assertContains('Select exactly one execution mode: --dry-run or --apply.', \WP_CLI::$errors);
    }

    /**
     * @dataProvider unusualModeFlagValues
     */
    public function testUnusualScalarModeValuesAreRejected(mixed $value): void
    {
        $exit = $this->command()->run([$this->fixturePath()], ['dry-run' => $value]);

        self::assertSame(1, $exit);
        self::assertContains('Unsupported value for --dry-run. Use it as a boolean flag.', \WP_CLI::$errors);
    }

    /** @return list<array{mixed}> */
    public static function unusualModeFlagValues(): array
    {
        return [['yes'], ['true'], ['apply'], [2], [[]]];
    }

    public function testUnsupportedOptionIsRejected(): void
    {
        $exit = $this->command()->run([$this->fixturePath()], ['dry-run' => true, 'force' => true]);

        self::assertSame(1, $exit);
        self::assertContains('Unsupported option --force.', \WP_CLI::$errors);
    }

    /**
     * @dataProvider invalidPaths
     */
    public function testInvalidLocalPathsAreRejected(string $path): void
    {
        $exit = $this->command()->run([$path], ['dry-run' => true]);

        self::assertSame(1, $exit);
        self::assertNotSame([], \WP_CLI::$errors);
    }

    /** @return list<array{string}> */
    public static function invalidPaths(): array
    {
        return [
            ['https://example.com/manifest.json'],
            ['http://example.com/manifest.json'],
            ['file:///tmp/manifest.json'],
            ['php://filter/resource=/tmp/manifest.json'],
            [__DIR__],
            [__FILE__],
            ['/tmp/campwp-missing-manifest.json'],
        ];
    }

    public function testUnreadableFileIsRejected(): void
    {
        $path = $this->writeTempFile('json', '{}');
        chmod($path, 0000);

        try {
            $exit = $this->command()->run([$path], ['dry-run' => true]);
        } finally {
            chmod($path, 0644);
            unlink($path);
        }

        self::assertSame(1, $exit);
        self::assertNotSame([], \WP_CLI::$errors);
    }

    public function testDryRunValidManifestReturnsZeroAndWritesNothing(): void
    {
        $exit = $this->command()->run([$this->fixturePath()], ['dry-run' => true]);

        self::assertSame(0, $exit);
        self::assertSame([], $GLOBALS['campwp_test_posts']);
        self::assertSame([], $GLOBALS['campwp_test_meta']);
        $output = $this->cliOutput();
        self::assertStringContainsString('Source: test_catalog:TEST001', $output);
        self::assertStringContainsString('Status: dry_run', $output);
        self::assertStringContainsString('Album: created', $output);
        self::assertStringContainsString('TEST001-01  created', $output);
        self::assertStringNotContainsString('playback_url', $output);
    }

    public function testDryRunInvalidManifestReturnsNonZeroAndPrintsErrors(): void
    {
        $manifest = $this->manifest();
        $manifest['schema_version'] = 'bad';
        $path = $this->writeManifest($manifest);

        $exit = $this->command()->run([$path], ['dry-run' => true]);

        self::assertSame(1, $exit);
        self::assertSame([], $GLOBALS['campwp_test_posts']);
        self::assertStringContainsString('schema_version is unsupported.', implode("\n", \WP_CLI::$errors));
        unlink($path);
    }

    public function testDryRunJsonOutputUsesImportResultShape(): void
    {
        $exit = $this->command()->run([$this->fixturePath()], ['dry-run' => true, 'format' => 'json']);

        self::assertSame(0, $exit);
        $decoded = json_decode(\WP_CLI::$lines[0] ?? '', true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('dry_run', $decoded['status']);
        self::assertSame('test_catalog:TEST001', $decoded['source_release_identity']);
        self::assertArrayHasKey('tracks', $decoded);
    }

    public function testJsonOutputContainsExactlyOneDocumentForInvalidManifest(): void
    {
        $manifest = $this->manifest();
        $manifest['schema_version'] = 'bad';
        $path = $this->writeManifest($manifest);

        $exit = $this->command()->run([$path], ['dry-run' => true, 'format' => 'json']);
        $decoded = $this->decodeSingleJsonOutput();

        self::assertSame(1, $exit);
        self::assertSame('failed', $decoded['status']);
        self::assertContains('schema_version is unsupported.', $decoded['errors']);
        self::assertSame([], \WP_CLI::$errors);
        self::assertStringNotContainsString('Test Remote Album', $this->cliOutput());
        unlink($path);
    }

    public function testJsonOutputContainsExactlyOneDocumentForPartialFailedSuccessAndUnchanged(): void
    {
        $GLOBALS['campwp_test_meta_write_failures'] = [MetadataKeys::TRACK_AUDIO_DOWNLOAD_URL];
        $partialExit = $this->command()->run([$this->fixturePath()], ['apply' => true, 'format' => 'json']);
        $partial = $this->decodeSingleJsonOutput();
        self::assertSame(1, $partialExit);
        self::assertSame('partial', $partial['status']);
        self::assertSame([], \WP_CLI::$errors);
        self::assertStringNotContainsString('playback_url', $this->cliOutput());

        $this->setUp();
        $failedManifest = $this->manifest();
        unset($failedManifest['album']['external_release_id']);
        $failedPath = $this->writeManifest($failedManifest);
        $failedExit = $this->command()->run([$failedPath], ['apply' => true, 'format' => 'json']);
        $failed = $this->decodeSingleJsonOutput();
        self::assertSame(1, $failedExit);
        self::assertSame('failed', $failed['status']);
        unlink($failedPath);

        $this->setUp();
        $successExit = $this->command()->run([$this->fixturePath()], ['apply' => true, 'format' => 'json']);
        $success = $this->decodeSingleJsonOutput();
        self::assertSame(0, $successExit);
        self::assertSame('success', $success['status']);

        \WP_CLI::reset();
        $unchangedExit = $this->command()->run([$this->fixturePath()], ['apply' => true, 'format' => 'json']);
        $unchanged = $this->decodeSingleJsonOutput();
        self::assertSame(0, $unchangedExit);
        self::assertSame('unchanged', $unchanged['status']);
    }

    public function testApplyFirstImportCreatesDraftAlbumAndTracks(): void
    {
        $exit = $this->command()->run([$this->fixturePath()], ['apply' => true]);

        self::assertSame(0, $exit);
        self::assertSame(3, count($GLOBALS['campwp_test_posts']));
        self::assertSame(0, $this->countPostsOfType('attachment'));
        $album = $this->firstPostOfType('campwp_album');
        self::assertInstanceOf(\WP_Post::class, $album);
        self::assertSame('draft', $album->post_status);
        self::assertStringContainsString('Status: success', $this->cliOutput());
        self::assertStringContainsString('Album: created (#' . $album->ID . ')', $this->cliOutput());
    }

    public function testSecondApplyReportsUnchanged(): void
    {
        $command = $this->command();
        self::assertSame(0, $command->run([$this->fixturePath()], ['apply' => true]));
        \WP_CLI::reset();

        $exit = $command->run([$this->fixturePath()], ['apply' => true]);

        self::assertSame(0, $exit);
        self::assertStringContainsString('Status: unchanged', $this->cliOutput());
        self::assertStringContainsString('Counts: 0 created, 0 updated, 3 unchanged', $this->cliOutput());
    }

    public function testPartialResultReturnsNonZeroAndPrintsErrors(): void
    {
        $GLOBALS['campwp_test_meta_write_failures'] = [MetadataKeys::TRACK_AUDIO_DOWNLOAD_URL];

        $exit = $this->command()->run([$this->fixturePath()], ['apply' => true]);

        self::assertSame(1, $exit);
        self::assertStringContainsString('Status: partial', $this->cliOutput());
        self::assertNotSame([], \WP_CLI::$errors);
    }

    public function testFailedResultReturnsNonZero(): void
    {
        $manifest = $this->manifest();
        unset($manifest['album']['external_release_id']);
        $path = $this->writeManifest($manifest);

        $exit = $this->command()->run([$path], ['apply' => true]);

        self::assertSame(1, $exit);
        self::assertStringContainsString('Status: failed', $this->cliOutput());
        unlink($path);
    }

    public function testApplyWarningsArePrinted(): void
    {
        $GLOBALS['campwp_test_posts'][50] = new \WP_Post(['ID' => 50, 'post_type' => 'campwp_album', 'post_title' => 'Legacy', 'post_status' => 'draft']);
        $GLOBALS['campwp_test_post_types'][50] = 'campwp_album';
        $GLOBALS['campwp_test_meta'][50] = [
            MetadataKeys::ALBUM_SOURCE_PROVIDER => 'direct',
            MetadataKeys::ALBUM_EXTERNAL_RELEASE_ID => 'TEST001',
        ];

        $exit = $this->command()->run([$this->fixturePath()], ['apply' => true]);

        self::assertSame(0, $exit);
        self::assertNotSame([], \WP_CLI::$warnings);
        self::assertStringContainsString('Warnings: 1', $this->cliOutput());
    }

    public function testUnexpectedExceptionIsControlledInTableMode(): void
    {
        $exit = $this->failingCommand('Sensitive /tmp/secret-path stack detail')->run([$this->fixturePath()], ['apply' => true]);

        self::assertSame(1, $exit);
        self::assertSame(['Unexpected import failure.'], \WP_CLI::$errors);
        self::assertStringNotContainsString('secret-path', implode("\n", \WP_CLI::$errors));
        self::assertSame('', $this->cliOutput());
    }

    public function testUnexpectedExceptionIsControlledInJsonMode(): void
    {
        $exit = $this->failingCommand('Sensitive /tmp/secret-path stack detail')->run([$this->fixturePath()], ['apply' => true, 'format' => 'json']);
        $decoded = $this->decodeSingleJsonOutput();

        self::assertSame(1, $exit);
        self::assertSame('failed', $decoded['status']);
        self::assertSame(['Unexpected import failure.'], $decoded['errors']);
        self::assertStringNotContainsString('secret-path', $this->cliOutput());
        self::assertSame([], \WP_CLI::$errors);
    }

    public function testInvokeHaltsNonZeroForErrors(): void
    {
        $this->expectException(\CampWpCliExitException::class);
        $this->command()([], ['dry-run' => true]);
    }

    private function command(): ImportReleaseCommand
    {
        return new ImportReleaseCommand();
    }

    private function failingCommand(string $message): ImportReleaseCommand
    {
        return new ImportReleaseCommand(null, static function () use ($message): ImportResult {
            throw new \RuntimeException($message);
        });
    }

    /** @return array<string,mixed> */
    private function decodeSingleJsonOutput(): array
    {
        self::assertCount(1, \WP_CLI::$lines);
        $decoded = json_decode(\WP_CLI::$lines[0], true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);

        return $decoded;
    }

    private function fixturePath(): string
    {
        return dirname(__DIR__, 2) . '/tests/Fixtures/single-release-manifest.json';
    }

    /** @return array<string, mixed> */
    private function manifest(): array
    {
        $decoded = json_decode((string) file_get_contents($this->fixturePath()), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);
        return $decoded;
    }

    /** @param array<string, mixed> $manifest */
    private function writeManifest(array $manifest): string
    {
        return $this->writeTempFile('json', json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?: '{}');
    }

    private function writeTempFile(string $extension, string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'campwp-cli-');
        self::assertIsString($path);
        $finalPath = $path . '.' . $extension;
        rename($path, $finalPath);
        file_put_contents($finalPath, $contents);
        return $finalPath;
    }

    private function cliOutput(): string
    {
        return implode("\n", \WP_CLI::$lines);
    }

    private function countPostsOfType(string $postType): int
    {
        return count(array_filter(
            $GLOBALS['campwp_test_posts'],
            static fn ($post): bool => $post instanceof \WP_Post && $post->post_type === $postType
        ));
    }

    private function firstPostOfType(string $postType): ?\WP_Post
    {
        foreach ($GLOBALS['campwp_test_posts'] as $post) {
            if ($post instanceof \WP_Post && $post->post_type === $postType) {
                return $post;
            }
        }

        return null;
    }
}
