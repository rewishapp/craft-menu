<?php

namespace rewish\craft\menu\controllers;

use craft\web\Controller;
use rewish\craft\menu\Plugin;
use rewish\craft\menu\web\assets\builder\BuilderAsset;
use yii\web\ForbiddenHttpException;
use yii\web\Response;

class SettingsController extends Controller
{
    public function actionIndex(): Response
    {
        $user = \Craft::$app->getUser()->getIdentity();
        $canSettings = $user?->can('menu:settings') ?? false;
        $canMigrate = $user?->can('menu:migrate') ?? false;
        if (!$canSettings && !$canMigrate) {
            throw new ForbiddenHttpException('Not allowed to access Menu settings.');
        }

        $this->view->registerAssetBundle(BuilderAsset::class);

        $verbb = null;
        if ($canMigrate) {
            $verbb = Plugin::getInstance()->migrators->detectVerbbNavigation();
        }

        return $this->renderTemplate('menu/settings/index', [
            'settings' => Plugin::settings(),
            'canSettings' => $canSettings,
            'canMigrate' => $canMigrate,
            'verbb' => $verbb,
        ]);
    }

    public function actionSave(): ?Response
    {
        $this->requirePostRequest();
        $this->requirePermission('menu:settings');
        $plugin = Plugin::getInstance();
        $settings = Plugin::settings();
        $settings->defaultMaxLevels = (int) $this->request->getBodyParam('defaultMaxLevels', 5);
        $apiToken = trim((string) $this->request->getBodyParam('apiToken', ''));
        $settings->apiToken = $apiToken !== '' ? $apiToken : null;
        if (!$settings->validate()) {
            return $this->asFailure(\Craft::t('menu', 'Couldn’t save settings.'), ['settings' => $settings]);
        }
        \Craft::$app->getPlugins()->savePluginSettings($plugin, $settings->toArray());

        return $this->asSuccess(\Craft::t('menu', 'Settings saved.'));
    }
}
