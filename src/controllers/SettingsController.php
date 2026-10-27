<?php

namespace rewish\craft\menu\controllers;

use craft\web\Controller;
use rewish\craft\menu\Plugin;
use yii\web\Response;

class SettingsController extends Controller
{
    public function actionIndex(): Response
    {
        $this->requirePermission('menu:settings');

        return $this->renderTemplate('menu/settings/index', [
            'settings' => Plugin::settings(),
        ]);
    }

    public function actionSave(): ?Response
    {
        $this->requirePostRequest();
        $this->requirePermission('menu:settings');
        $plugin = Plugin::getInstance();
        $settings = Plugin::settings();
        $settings->defaultMaxLevels = (int) $this->request->getBodyParam('defaultMaxLevels', 5);
        if (!$settings->validate()) {
            return $this->asFailure(\Craft::t('menu', 'Couldn’t save settings.'), ['settings' => $settings]);
        }
        \Craft::$app->getPlugins()->savePluginSettings($plugin, $settings->toArray());

        return $this->asSuccess(\Craft::t('menu', 'Settings saved.'));
    }
}
