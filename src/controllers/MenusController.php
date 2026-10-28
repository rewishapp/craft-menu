<?php

namespace rewish\craft\menu\controllers;

use craft\helpers\StringHelper;
use craft\models\FieldLayout;
use craft\web\Controller;
use rewish\craft\menu\elements\Menu;
use rewish\craft\menu\elements\Node;
use rewish\craft\menu\Plugin;
use rewish\craft\menu\web\assets\builder\BuilderAsset;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class MenusController extends Controller
{
    public function actionIndex(): Response
    {
        $this->requirePermission('menu:access');
        $user = \Craft::$app->getUser()->getIdentity();
        $menus = Plugin::getInstance()->menus->getAll($user);
        $nodeCounts = [];
        $health = [];
        foreach ($menus as $menu) {
            if (!$menu->id) {
                continue;
            }
            $health[(int) $menu->id] = Plugin::getInstance()->linkHealth->summary($menu);
            $nodeCounts[(int) $menu->id] = (int) Node::find()
                ->menuId((int) $menu->id)
                ->status(null)
                ->count()
            ;
        }

        $this->view->registerAssetBundle(BuilderAsset::class);

        return $this->renderTemplate('menu/menus/index', [
            'menus' => $menus,
            'nodeCounts' => $nodeCounts,
            'health' => $health,
            'canCreate' => $user?->can('menu:editMenus') ?? false,
        ]);
    }

    public function actionEdit(?int $menuId = null): Response
    {
        if ($menuId) {
            $menu = Plugin::getInstance()->menus->getById($menuId);
            if (!$menu) {
                throw new NotFoundHttpException('Menu not found.');
            }
            $this->requireMenuManage($menu);
        } else {
            $this->requirePermission('menu:editMenus');
            $menu = new Menu();
            $menu->maxLevels = Plugin::settings()->defaultMaxLevels;
            $menu->enabledSiteIds = array_map(
                static fn ($site) => (int) $site->id,
                \Craft::$app->getSites()->getAllSites()
            );
        }

        $layout = $menu->getNodeFieldLayout();
        if (!$layout) {
            $layout = new FieldLayout();
            $layout->type = Node::class;
        }

        return $this->renderTemplate('menu/menus/edit', [
            'menu' => $menu,
            'isNew' => !$menu->id,
            'sites' => \Craft::$app->getSites()->getAllSites(),
            'enabledSiteIds' => $menu->getEnabledSiteIds(),
            'nodeFieldLayout' => $layout,
        ]);
    }

    public function actionSave(): ?Response
    {
        $this->requirePostRequest();

        $id = $this->request->getBodyParam('menuId');
        $menu = $id ? Plugin::getInstance()->menus->getById((int) $id) : new Menu();
        if (!$menu) {
            throw new NotFoundHttpException('Menu not found.');
        }
        if ($menu->id) {
            $this->requireMenuManage($menu);
        } else {
            $this->requirePermission('menu:editMenus');
        }

        $menu->title = (string) $this->request->getBodyParam('title');
        $handle = trim((string) $this->request->getBodyParam('handle'));
        $menu->handle = $handle !== '' ? $handle : StringHelper::toHandle($menu->title);
        $menu->instructions = $this->request->getBodyParam('instructions') ?: null;
        $cssClass = trim((string) $this->request->getBodyParam('cssClass', ''));
        $menu->cssClass = $cssClass !== '' ? $cssClass : null;
        $htmlAttributes = trim((string) $this->request->getBodyParam('htmlAttributes', ''));
        $menu->htmlAttributes = $htmlAttributes !== '' ? $htmlAttributes : null;
        $enabled = $this->request->getBodyParam('enabled');
        if ($enabled !== null) {
            $menu->enabled = (bool) $enabled;
        }
        $maxLevels = $this->request->getBodyParam('maxLevels');
        $menu->maxLevels = is_numeric($maxLevels) ? (int) $maxLevels : Plugin::settings()->defaultMaxLevels;

        $siteIds = $this->request->getBodyParam('siteIds');
        if (\is_array($siteIds)) {
            $menu->enabledSiteIds = array_values(array_map('intval', array_filter($siteIds, 'is_numeric')));
        } else {
            $menu->enabledSiteIds = array_map(
                static fn ($site) => (int) $site->id,
                \Craft::$app->getSites()->getAllSites()
            );
        }

        if ($this->request->getBodyParam('fieldLayout')) {
            $fieldLayout = \Craft::$app->getFields()->assembleLayoutFromPost();
            $fieldLayout->id = $menu->nodeFieldLayoutId;
            $fieldLayout->type = Node::class;
            if (\Craft::$app->getFields()->saveLayout($fieldLayout)) {
                $menu->nodeFieldLayoutId = $fieldLayout->id;
            }
        }

        if (!\Craft::$app->getElements()->saveElement($menu)) {
            return $this->asFailure(\Craft::t('menu', 'Couldn’t save menu.'), ['menu' => $menu]);
        }

        return $this->asSuccess(\Craft::t('menu', 'Menu saved.'), [
            'menu' => $menu,
        ], 'menu/'.$menu->id.'/nodes');
    }

    public function actionDuplicate(): ?Response
    {
        $this->requirePostRequest();
        $this->requirePermission('menu:editMenus');
        $id = (int) $this->request->getRequiredBodyParam('menuId');
        $menu = Plugin::getInstance()->menus->getById($id);
        if (!$menu) {
            throw new NotFoundHttpException('Menu not found.');
        }

        $copy = Plugin::getInstance()->duplicators->duplicateMenu($menu);
        if (!$copy) {
            return $this->asFailure(\Craft::t('menu', 'Couldn’t duplicate menu.'));
        }

        return $this->asSuccess(\Craft::t('menu', 'Menu duplicated.'), ['menu' => $copy], 'menu/'.$copy->id.'/nodes');
    }

    public function actionDelete(): ?Response
    {
        $this->requirePostRequest();
        $this->requirePermission('menu:editMenus');
        $id = (int) $this->request->getRequiredBodyParam('menuId');
        $menu = Plugin::getInstance()->menus->getById($id);
        if (!$menu) {
            throw new NotFoundHttpException('Menu not found.');
        }
        if (!\Craft::$app->getElements()->deleteElement($menu)) {
            throw new ForbiddenHttpException('Could not delete menu.');
        }

        return $this->asSuccess(\Craft::t('menu', 'Menu deleted.'), redirect: 'menu');
    }

    private function requireMenuManage(Menu $menu): void
    {
        if (!Plugin::getInstance()->menus->canManage($menu)) {
            throw new ForbiddenHttpException('Not allowed to manage this menu.');
        }
    }
}
