<?php

namespace rewish\craft\menu\models;

use craft\base\Model;

class Settings extends Model
{
    public int $defaultMaxLevels = 5;

    public bool $pluginNameOverride = false;

    public function defineRules(): array
    {
        return [
            [['defaultMaxLevels'], 'integer', 'min' => 1, 'max' => 20],
        ];
    }
}
