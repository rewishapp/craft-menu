<?php

namespace rewish\craft\menu\models;

use craft\base\Model;

class Settings extends Model
{
    public int $defaultMaxLevels = 5;

    public bool $pluginNameOverride = false;

    /** Shared secret for the read-only JSON API. Empty = API disabled. Supports env vars (`$MENU_API_TOKEN`). */
    public ?string $apiToken = null;

    public function defineRules(): array
    {
        return [
            [['defaultMaxLevels'], 'integer', 'min' => 1, 'max' => 20],
            [['apiToken'], 'string', 'max' => 255],
        ];
    }
}
