<?php

namespace rewish\craft\menu\controllers;

use craft\helpers\Json;
use craft\web\Controller;
use rewish\craft\menu\Plugin;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class ImportExportController extends Controller
{
    public function actionExport(int $menuId): Response
    {
        $menu = Plugin::getInstance()->menus->getById($menuId);
        if (!$menu) {
            throw new NotFoundHttpException('Menu not found.');
        }
        if (!Plugin::getInstance()->menus->canManage($menu)) {
            throw new ForbiddenHttpException('Not allowed.');
        }

        $payload = Plugin::getInstance()->importExport->exportMenu($menu);
        $json = Json::encode($payload, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES);

        return $this->response
            ->sendContentAsFile($json, $menu->handle.'-menu.json', [
                'mimeType' => 'application/json',
            ])
        ;
    }

    public function actionImport(): ?Response
    {
        $this->requirePostRequest();
        $this->requirePermission('menu:editMenus');

        $raw = (string) $this->request->getBodyParam('json', '');
        $replace = (bool) $this->request->getBodyParam('replace');
        $data = Json::decodeIfJson($raw);
        if (!\is_array($data)) {
            return $this->asFailure(\Craft::t('menu', 'Invalid JSON.'));
        }

        $result = Plugin::getInstance()->importExport->importMenu($data, $replace);
        if (!$result['ok']) {
            return $this->asFailure($result['message']);
        }

        $menu = $result['menu'];

        return $this->asSuccess($result['message'], redirect: $menu?->id ? 'menu/'.$menu->id.'/nodes' : 'menu');
    }
}
