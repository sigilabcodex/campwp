<?php

declare(strict_types=1);

namespace CampWP\Domain\Media;

final class BonusAssetReference
{
    private string $type;

    private int $referenceId;

    private string $label;

    public function __construct(string $type, int $referenceId, string $label)
    {
        $this->type = $type;
        $this->referenceId = $referenceId;
        $this->label = $label;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getReferenceId(): int
    {
        return $this->referenceId;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    /**
     * @return array{type: string, reference_id: int, label: string}
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'reference_id' => $this->referenceId,
            'label' => $this->label,
        ];
    }
}
