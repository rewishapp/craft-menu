<?php

namespace rewish\craft\menu\models;

use craft\base\Model;

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

    public function getNodeUrl(): ?string
    {
        return $this->url;
    }

    public function getLevelIndent(): int
    {
        return 0;
    }
}
