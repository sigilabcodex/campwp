<?php

declare(strict_types=1);

namespace CampWP\Application\Import\Media;

use CampWP\Domain\Import\CoverManifest;

interface CoverSideloaderInterface
{
    public function sideload(CoverManifest $cover, int $albumId, int $existingAttachmentId = 0): CoverSideloadResult;
}
