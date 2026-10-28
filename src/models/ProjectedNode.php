<?php

namespace rewish\craft\menu\models;

use craft\base\Model;
use rewish\craft\menu\helpers\NodeMeta;

/**
 * Virtual child produced by a dynamic source (not a stored Node element).
 */
class ProjectedNode extends Model
{
    public string $title = '';

    public ?string $url = null;

    public ?int $elementId = null;

    public string $type = 'projected';

    public bool $newWindow = false;

    public ?string $classes = null;

    /** @var array<string, mixed> raw meta (normalized by {@see getMeta()}) */
    public array $rawMeta = [];

    public function getNodeUrl(): ?string
    {
        return $this->url;
    }

    public function getLevelIndent(): int
    {
        return 0;
    }

    /**
     * @return array<string, mixed>
     */
    public function getMeta(): array
    {
        return NodeMeta::normalize($this->rawMeta);
    }

    /**
     * @return array<string, mixed>
     */
    public function getCustomAttributesArray(): array
    {
        return [];
    }

    public function isSeparator(): bool
    {
        return $this->type === 'separator';
    }

    public function isClickable(): bool
    {
        return $this->getMeta()['clickable'] && $this->url !== null && $this->url !== '';
    }
}
