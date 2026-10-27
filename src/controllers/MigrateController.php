<?php

namespace rewish\craft\menu\controllers;

use craft\web\Controller;
use rewish\craft\menu\Plugin;
use yii\web\Response;

class MigrateController extends Controller
{
    public function actionIndex(): Response
    {
        $this->requirePermission('menu:migrate');

        return $this->redirect('menu/settings#migrate');
    }

    public function actionVerbb(): ?Response
    {
        $this->requirePostRequest();
        $this->requirePermission('menu:migrate');
        $dryRun = (bool) $this->request->getBodyParam('dryRun', true);
        $result = Plugin::getInstance()->migrators->migrateFromVerbbNavigation($dryRun);
        if (!$result['ok']) {
            return $this->asFailure(implode(' ', $result['lines']));
        }

        return $this->asSuccess(implode(' ', $result['lines']));
    }
}
