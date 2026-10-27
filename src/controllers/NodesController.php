<?php

namespace rewish\craft\menu\controllers;

use craft\helpers\Json;
use craft\web\Controller;
use rewish\craft\menu\elements\Menu;
use rewish\craft\menu\elements\Node;
use rewish\craft\menu\Plugin;
use rewish\craft\menu\services\DynamicSources;
use rewish\craft\menu\web\assets\builder\BuilderAsset;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class NodesController extends Controller
{
    public function actionIndex(int $menuId): Response
    {
        $this->requirePermission('menu:access');
        $menu = Plugin::getInstance()->menus->getById($menuId);
        if (!$menu) {
            throw new NotFoundHttpException('Menu not found.');
        }
        if (!$menu->canView(\Craft::$app->getUser()->getIdentity())) {
            throw new ForbiddenHttpException('Not allowed to view this menu.');
        }

        $this->view->registerAssetBundle(BuilderAsset::class);
        $vars = $this->indexVars($menu);

        return $this->renderTemplate('menu/nodes/index', $vars);
    }

    public function actionList(int $menuId): Response
    {
        $this->requirePermission('menu:access');
        $menu = Plugin::getInstance()->menus->getById($menuId);
        if (!$menu) {
            throw new NotFoundHttpException('Menu not found.');
        }
        $vars = $this->indexVars($menu);

        return $this->asJson([
            'success' => true,
            'html' => $this->getView()->renderTemplate('menu/nodes/_list', $vars),
            'sessionMoves' => $vars['session']['moves'] ?? 0,
        ]);
    }

    public function actionMove(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $menuId = (int) $this->request->getRequiredBodyParam('menuId');
        $nodeId = (int) $this->request->getRequiredBodyParam('nodeId');
        $targetId = (int) $this->request->getRequiredBodyParam('targetId');
        $position = (string) $this->request->getRequiredBodyParam('position');
        $siteId = (int) ($this->request->getBodyParam('siteId') ?: \Craft::$app->getSites()->getPrimarySite()->id);

        $menu = Plugin::getInstance()->menus->getById($menuId);
        if (!$menu) {
            throw new NotFoundHttpException('Menu not found.');
        }
        $this->requireMenuManage($menu);

        $node = Node::find()->id($nodeId)->menuId($menuId)->status(null)->siteId($siteId)->one()
            ?? Node::find()->id($nodeId)->menuId($menuId)->status(null)->one();
        if (!$node) {
            throw new NotFoundHttpException('Node not found.');
        }

        if (!Plugin::getInstance()->nodes->move($node, $position, $targetId)) {
            return $this->asFailure(\Craft::t('menu', 'Couldn’t move node.'));
        }

        $userId = (int) \Craft::$app->getUser()->getId();
        Plugin::getInstance()->buildSessions->recordMove($menuId, $siteId, $userId, [
            'nodeId' => $nodeId,
            'targetId' => $targetId,
            'position' => $position,
        ]);
        $session = Plugin::getInstance()->buildSessions->getOrCreate($menuId, $siteId, $userId);

        $vars = $this->indexVars($menu, $siteId);

        return $this->asSuccess(\Craft::t('menu', 'Node moved.'), [
            'html' => $this->getView()->renderTemplate('menu/nodes/_list', $vars),
            'sessionMoves' => $session['moves'],
        ]);
    }

    public function actionDiscardSession(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();
        $menuId = (int) $this->request->getRequiredBodyParam('menuId');
        $siteId = (int) ($this->request->getBodyParam('siteId') ?: \Craft::$app->getSites()->getPrimarySite()->id);
        $menu = Plugin::getInstance()->menus->getById($menuId);
        if (!$menu) {
            throw new NotFoundHttpException('Menu not found.');
        }
        $this->requireMenuManage($menu);
        $userId = (int) \Craft::$app->getUser()->getId();
        if (!Plugin::getInstance()->buildSessions->discard($menuId, $siteId, $userId)) {
            return $this->asFailure(\Craft::t('menu', 'Couldn’t restore structure.'));
        }
        $vars = $this->indexVars($menu, $siteId);

        return $this->asSuccess(\Craft::t('menu', 'Structure restored.'), [
            'html' => $this->getView()->renderTemplate('menu/nodes/_list', $vars),
            'sessionMoves' => 0,
        ]);
    }

    public function actionSave(): ?Response
    {
        $this->requirePostRequest();

        $menuId = (int) $this->request->getRequiredBodyParam('menuId');
        $menu = Plugin::getInstance()->menus->getById($menuId);
        if (!$menu) {
            throw new NotFoundHttpException('Menu not found.');
        }
        $this->requireMenuManage($menu);

        $siteId = (int) ($this->request->getBodyParam('siteId') ?: \Craft::$app->getSites()->getPrimarySite()->id);
        $id = $this->request->getBodyParam('nodeId');
        $node = $id
            ? (Node::find()->id((int) $id)->menuId($menuId)->status(null)->siteId($siteId)->one()
                ?? Node::find()->id((int) $id)->menuId($menuId)->status(null)->one())
            : new Node(['menuId' => $menuId, 'siteId' => $siteId]);
        if (!$node) {
            throw new NotFoundHttpException('Node not found.');
        }

        $node->title = (string) $this->request->getBodyParam('title');
        $node->type = (string) $this->request->getBodyParam('type', Node::TYPE_CUSTOM);
        $node->url = $this->request->getBodyParam('url') ?: null;

        $elements = $this->request->getBodyParam('elements');
        if (\is_array($elements) && $elements !== []) {
            $node->elementId = (int) reset($elements);
        } else {
            $elementId = $this->request->getBodyParam('elementId');
            $node->elementId = is_numeric($elementId) ? (int) $elementId : null;
        }

        if ($node->type === Node::TYPE_SITE) {
            $linkedSiteId = $this->request->getBodyParam('linkedSiteId');
            $node->elementId = is_numeric($linkedSiteId) ? (int) $linkedSiteId : $node->elementId;
            $node->url = null;
            $node->data = null;
        } elseif ($node->type === Node::TYPE_DYNAMIC) {
            $node->elementId = null;
            $node->url = null;
            $source = (string) $this->request->getBodyParam('dynamicSource', DynamicSources::SOURCE_ENTRY_SECTION);
            $data = [
                'source' => $source,
                'limit' => (int) ($this->request->getBodyParam('dynamicLimit') ?: 50),
            ];
            if ($source === DynamicSources::SOURCE_ENTRY_SECTION) {
                $data['section'] = (string) $this->request->getBodyParam('dynamicSection', '');
            } elseif ($source === DynamicSources::SOURCE_CATEGORY_GROUP) {
                $data['group'] = (string) $this->request->getBodyParam('dynamicGroup', '');
            } elseif ($source === DynamicSources::SOURCE_ASSET_VOLUME) {
                $data['volume'] = (string) $this->request->getBodyParam('dynamicVolume', '');
            } elseif ($source === DynamicSources::SOURCE_PRODUCT_TYPE) {
                $data['productType'] = (string) $this->request->getBodyParam('dynamicProductType', '');
            }
            $node->setDataArray($data);
        } elseif ($node->type === Node::TYPE_PASSIVE) {
            $node->elementId = null;
            $node->url = null;
            $node->data = null;
        } elseif ($node->type === Node::TYPE_CUSTOM) {
            $node->elementId = null;
            $node->data = null;
        } else {
            $node->url = null;
            $node->data = null;
        }

        $node->classes = $this->request->getBodyParam('classes') ?: null;
        $node->urlSuffix = $this->request->getBodyParam('urlSuffix') ?: null;
        $node->newWindow = (bool) $this->request->getBodyParam('newWindow');
        $node->followElement = (bool) $this->request->getBodyParam('followElement', true);
        $node->enabled = (bool) $this->request->getBodyParam('enabled', true);
        $node->siteId = $siteId;

        $attrs = trim((string) $this->request->getBodyParam('customAttributes', ''));
        if ($attrs === '') {
            $node->customAttributes = null;
        } else {
            $decoded = Json::decodeIfJson($attrs);
            $node->customAttributes = \is_array($decoded) ? Json::encode($decoded) : $attrs;
        }

        $parentId = $this->request->getBodyParam('parentId');
        $node->newParentId = is_numeric($parentId) ? (int) $parentId : null;

        if ($node->getFieldLayout()) {
            $node->setFieldValuesFromRequest('fields');
        }

        if (!\Craft::$app->getElements()->saveElement($node)) {
            return $this->asFailure(\Craft::t('menu', 'Couldn’t save node.'), ['node' => $node]);
        }

        return $this->asSuccess(\Craft::t('menu', 'Node saved.'), redirect: 'menu/'.$menuId.'/nodes?siteId='.$siteId);
    }

    public function actionDelete(): ?Response
    {
        $this->requirePostRequest();
        $menuId = (int) $this->request->getRequiredBodyParam('menuId');
        $siteId = (int) ($this->request->getBodyParam('siteId') ?: \Craft::$app->getSites()->getPrimarySite()->id);
        $menu = Plugin::getInstance()->menus->getById($menuId);
        if (!$menu) {
            throw new NotFoundHttpException('Menu not found.');
        }
        $this->requireMenuManage($menu);
        $nodeId = (int) $this->request->getRequiredBodyParam('nodeId');
        $node = Node::find()->id($nodeId)->menuId($menuId)->status(null)->siteId($siteId)->one()
            ?? Node::find()->id($nodeId)->menuId($menuId)->status(null)->one();
        if (!$node) {
            throw new NotFoundHttpException('Node not found.');
        }
        \Craft::$app->getElements()->deleteElement($node);

        return $this->asSuccess(\Craft::t('menu', 'Node deleted.'), redirect: 'menu/'.$menuId.'/nodes?siteId='.$siteId);
    }

    /**
     * @return array<string, mixed>
     */
    private function indexVars(Menu $menu, ?int $siteId = null): array
    {
        $enabledSiteIds = $menu->getEnabledSiteIds();
        $editableSites = array_values(array_filter(
            \Craft::$app->getSites()->getAllSites(),
            static fn ($site) => \in_array((int) $site->id, $enabledSiteIds, true)
        ));
        if ($editableSites === []) {
            $editableSites = \Craft::$app->getSites()->getAllSites();
        }

        $requested = $siteId ?? $this->request->getQueryParam('siteId');
        $siteId = is_numeric($requested) ? (int) $requested : (int) $editableSites[0]->id;
        if (!\in_array($siteId, array_map(static fn ($s) => (int) $s->id, $editableSites), true)) {
            $siteId = (int) $editableSites[0]->id;
        }

        $menuId = (int) $menu->id;
        $nodeId = $this->request->getQueryParam('nodeId');
        $editNode = null;
        if ($nodeId) {
            $editNode = Node::find()->id((int) $nodeId)->menuId($menuId)->status(null)->siteId($siteId)->structureId($menu->structureId)->one()
                ?? Node::find()->id((int) $nodeId)->menuId($menuId)->status(null)->structureId($menu->structureId)->one();
        }

        $editNode ??= new Node([
            'menuId' => $menuId,
            'type' => Node::TYPE_CUSTOM,
            'siteId' => $siteId,
        ]);

        $nodes = Plugin::getInstance()->nodes->getForMenu($menuId, $siteId);
        $parentOptions = [['label' => \Craft::t('menu', 'Top level'), 'value' => '']];
        foreach ($nodes as $node) {
            if ($editNode->id && ((int) $node->id === (int) $editNode->id || $this->isDescendant($editNode, $node))) {
                continue;
            }
            $indent = str_repeat('— ', max(0, (int) $node->level - 1));
            $parentOptions[] = [
                'label' => $indent.$node->title,
                'value' => (string) $node->id,
            ];
        }

        $userId = (int) (\Craft::$app->getUser()->getId() ?: 0);
        $session = $userId
            ? Plugin::getInstance()->buildSessions->getOrCreate($menuId, $siteId, $userId)
            : ['id' => 0, 'moves' => 0, 'snapshot' => []];

        $productTypes = [];
        if (Node::commerceInstalled() && \Craft::$app->getPlugins()->getPlugin('commerce')) {
            try {
                $productTypes = \craft\commerce\Plugin::getInstance()->getProductTypes()->getAllProductTypes();
            } catch (\Throwable) {
                $productTypes = [];
            }
        }

        return [
            'menu' => $menu,
            'nodes' => $nodes,
            'editNode' => $editNode,
            'parentId' => $editNode->id ? $editNode->getParent()?->id : null,
            'parentOptions' => $parentOptions,
            'linkedElement' => $editNode->getLinkedElement(),
            'types' => self::typeOptions(),
            'sites' => $editableSites,
            'siteId' => $siteId,
            'sections' => \Craft::$app->getEntries()->getAllSections(),
            'categoryGroups' => \Craft::$app->getCategories()->getAllGroups(),
            'volumes' => \Craft::$app->getVolumes()->getAllVolumes(),
            'productTypes' => $productTypes,
            'commerceInstalled' => Node::commerceInstalled(),
            'dynamicSources' => Plugin::getInstance()->dynamicSources->sourceOptions(),
            'dynamicData' => $editNode->getDataArray(),
            'nodeFieldLayout' => $editNode->getFieldLayout(),
            'canEdit' => Plugin::getInstance()->menus->canManage($menu),
            'session' => $session,
            'productClass' => Node::commerceProductClass(),
        ];
    }

    private function requireMenuManage(Menu $menu): void
    {
        if (!Plugin::getInstance()->menus->canManage($menu)) {
            throw new ForbiddenHttpException('Not allowed to manage this menu.');
        }
    }

    private function isDescendant(Node $ancestor, Node $candidate): bool
    {
        $parent = $candidate->getParent();
        while ($parent instanceof Node) {
            if ((int) $parent->id === (int) $ancestor->id) {
                return true;
            }
            $parent = $parent->getParent();
        }

        return false;
    }

    /**
     * @return array<string, string>
     */
    private static function typeOptions(): array
    {
        $types = [
            Node::TYPE_CUSTOM => \Craft::t('menu', 'Custom URL'),
            Node::TYPE_ENTRY => \Craft::t('menu', 'Entry'),
            Node::TYPE_CATEGORY => \Craft::t('menu', 'Category'),
            Node::TYPE_ASSET => \Craft::t('menu', 'Asset'),
            Node::TYPE_PASSIVE => \Craft::t('menu', 'Passive (label only)'),
            Node::TYPE_SITE => \Craft::t('menu', 'Site'),
            Node::TYPE_DYNAMIC => \Craft::t('menu', 'Dynamic'),
        ];
        if (Node::commerceInstalled()) {
            $types[Node::TYPE_PRODUCT] = \Craft::t('menu', 'Product');
        }

        return $types;
    }
}
