<?php

namespace rewish\craft\menu\console\controllers;

use craft\console\Controller;
use craft\helpers\Console;
use rewish\craft\menu\Plugin;
use yii\console\ExitCode;

/**
 * Migrate menus from other plugins into Menu.
 */
class MigrateController extends Controller
{
    public bool $dryRun = true;

    public function options($actionID): array
    {
        return array_merge(parent::options($actionID), ['dryRun']);
    }

    public function optionAliases(): array
    {
        return array_merge(parent::optionAliases(), [
            'dry-run' => 'dryRun',
        ]);
    }

    /**
     * Migrate from Verbb.
     */
    public function actionNavigation(): int
    {
        $result = Plugin::getInstance()->migrators->migrateFromVerbbNavigation($this->dryRun);
        foreach ($result['lines'] as $line) {
            $this->stdout($line.\PHP_EOL, $result['ok'] ? Console::FG_GREEN : Console::FG_RED);
        }

        return $result['ok'] ? ExitCode::OK : ExitCode::UNSPECIFIED_ERROR;
    }
}
