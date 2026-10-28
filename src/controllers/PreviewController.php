<?php

namespace rewish\craft\menu\controllers;

use craft\web\Controller;
use rewish\craft\menu\Plugin;
use rewish\craft\menu\web\assets\builder\BuilderAsset;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * CP preview of how a menu resolves for a site (visibility rules applied).
 */
class PreviewController extends Controller
{
    public function actionIndex(int $menuId, ?int $siteId = null): Response
    {
        $this->requirePermission('menu:access');
        $plugin = Plugin::getInstance();
        $menu = $plugin->menus->getById($menuId);
        if (!$menu) {
            throw new NotFoundHttpException('Menu not found.');
        }
        if (!$menu->canView(\Craft::$app->getUser()->getIdentity())) {
            throw new ForbiddenHttpException('Not allowed to view this menu.');
        }

        $sites = array_values(array_filter(
            \Craft::$app->getSites()->getAllSites(),
            static fn ($site) => \in_array((int) $site->id, $menu->getEnabledSiteIds(), true)
        ));
        $requested = $siteId ?? $this->request->getQueryParam('siteId');
        $siteId = is_numeric($requested) ? (int) $requested : (int) (($sites[0] ?? \Craft::$app->getSites()->getPrimarySite())->id);

        $raw = (bool) $this->request->getQueryParam('raw');
        $tree = $plugin->nodes->getTree($menuId, $siteId, !$raw);
        $tree = $plugin->activeMatcher->annotateTree($tree, (string) $this->request->getQueryParam('uri', ''));

        $this->view->registerAssetBundle(BuilderAsset::class);

        return $this->renderTemplate('menu/preview', [
            'menu' => $menu,
            'tree' => $tree,
            'sites' => $sites,
            'siteId' => $siteId,
            'raw' => $raw,
            'uri' => (string) $this->request->getQueryParam('uri', ''),
            'menuEnabled' => (bool) $menu->enabled,
        ]);
    }
}
