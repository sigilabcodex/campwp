<?php

declare(strict_types=1);

namespace CampWP\Application\Import;

final class ManifestReader
{
    /**
     * @return array<string, mixed>
     */
    public function readJsonString(string $json): array
    {
        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new \InvalidArgumentException('Manifest JSON is malformed: ' . $exception->getMessage(), 0, $exception);
        }

        if (! is_array($decoded) || array_is_list($decoded)) {
            throw new \InvalidArgumentException('Manifest JSON must decode to an object.');
        }

        return $decoded;
    }

    /**
     * @param array<string, mixed> $manifest
     * @return array<string, mixed>
     */
    public function readArray(array $manifest): array
    {
        if (array_is_list($manifest)) {
            throw new \InvalidArgumentException('Manifest array must be an associative object.');
        }

        return $manifest;
    }

    /**
     * @return array<string, mixed>
     */
    public function readLocalFile(string $path): array
    {
        $path = trim($path);
        if ($path === '' || preg_match('/^[a-z][a-z0-9+.-]*:/i', $path) === 1) {
            throw new \InvalidArgumentException('Manifest path must be a local filesystem path, not a URL or stream wrapper.');
        }

        if (! is_file($path) || ! is_readable($path)) {
            throw new \InvalidArgumentException('Manifest path must be a readable JSON file.');
        }

        if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'json') {
            throw new \InvalidArgumentException('Manifest path must point to a JSON file.');
        }

        $contents = file_get_contents($path);
        if (! is_string($contents)) {
            throw new \InvalidArgumentException('Manifest file could not be read.');
        }

        return $this->readJsonString($contents);
    }
}
