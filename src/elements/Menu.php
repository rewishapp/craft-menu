<?php

namespace rewish\craft\menu\elements;

use craft\base\Element;
use craft\db\Query;
use craft\elements\User;
use craft\helpers\StringHelper;
use craft\helpers\UrlHelper;
use craft\models\FieldLayout;
use craft\models\Structure;
use rewish\craft\menu\elements\db\MenuQuery;
use rewish\craft\menu\Plugin;
use rewish\craft\menu\records\MenuRecord;

class Menu extends Element
{
    public ?string $handle = null;

    public ?int $structureId = null;

    public ?string $instructions = null;

    public ?int $maxLevels = null;

    public ?int $sortOrder = null;

    public ?int $nodeFieldLayoutId = null;

    /** @var null|list<int> site IDs enabled for this menu; null = all sites */
    public ?array $enabledSiteIds = null;

    public static function displayName(): string
    {
        return \Craft::t('menu', 'Menu');
    }

    public static function pluralDisplayName(): string
    {
        return \Craft::t('menu', 'Menus');
    }

    public static function refHandle(): ?string
    {
        return 'menu';
    }

    public static function hasTitles(): bool
    {
        return true;
    }

    public static function hasUris(): bool
    {
        return false;
    }

    public static function isLocalized(): bool
    {
        return false;
    }

    public static function find(): MenuQuery
    {
        return new MenuQuery(static::class);
    }

    public static function managePermissionName(string $uid): string
    {
        return 'menu:manageMenu:'.$uid;
    }

    public function getManagePermissionName(): ?string
    {
        return $this->uid ? self::managePermissionName($this->uid) : null;
    }

    public function getUriFormat(): ?string
    {
        return null;
    }

    public function getCpEditUrl(): ?string
    {
        return $this->id ? UrlHelper::cpUrl('menu/'.$this->id.'/nodes') : null;
    }

    public function canView(User $user): bool
    {
        if ($user->can('menu:editMenus')) {
            return true;
        }
        $perm = $this->getManagePermissionName();

        return $perm ? $user->can($perm) : $user->can('menu:access');
    }

    public function canSave(User $user): bool
    {
        if ($user->can('menu:editMenus')) {
            return true;
        }
        $perm = $this->getManagePermissionName();

        return $perm ? $user->can($perm) : false;
    }

    public function canDelete(User $user): bool
    {
        return $user->can('menu:editMenus');
    }

    /**
     * @return list<int>
     */
    public function getEnabledSiteIds(): array
    {
        if ($this->enabledSiteIds !== null) {
            return $this->enabledSiteIds;
        }
        if (!$this->id) {
            return array_map(static fn ($site) => (int) $site->id, \Craft::$app->getSites()->getAllSites());
        }

        $rows = (new Query())
            ->select(['siteId'])
            ->from('{{%menu_menus_sites}}')
            ->where(['menuId' => $this->id, 'enabled' => true])
            ->column()
        ;

        if ($rows === []) {
            // No rows yet → all sites.
            $this->enabledSiteIds = array_map(static fn ($site) => (int) $site->id, \Craft::$app->getSites()->getAllSites());
        } else {
            $this->enabledSiteIds = array_map('intval', $rows);
        }

        return $this->enabledSiteIds;
    }

    public function isEnabledForSite(?int $siteId = null): bool
    {
        $siteId ??= (int) \Craft::$app->getSites()->getCurrentSite()->id;

        return \in_array($siteId, $this->getEnabledSiteIds(), true);
    }

    public function getNodeFieldLayout(): ?FieldLayout
    {
        if ($this->nodeFieldLayoutId) {
            return \Craft::$app->getFields()->getLayoutById($this->nodeFieldLayoutId);
        }

        return null;
    }

    public function afterSave(bool $isNew): void
    {
        if ($isNew) {
            $structure = new Structure();
            $structure->maxLevels = $this->maxLevels ?: Plugin::settings()->defaultMaxLevels;
            \Craft::$app->getStructures()->saveStructure($structure);
            $this->structureId = $structure->id;

            $record = new MenuRecord();
            $record->id = $this->id;
        } else {
            $record = MenuRecord::findOne($this->id) ?? new MenuRecord();
            $record->id = $this->id;
            if ($this->structureId && $this->maxLevels) {
                $structure = \Craft::$app->getStructures()->getStructureById($this->structureId);
                if ($structure) {
                    $structure->maxLevels = $this->maxLevels;
                    \Craft::$app->getStructures()->saveStructure($structure);
                }
            }
        }

        if (!$this->nodeFieldLayoutId) {
            $layout = new FieldLayout();
            $layout->type = Node::class;
            if (\Craft::$app->getFields()->saveLayout($layout)) {
                $this->nodeFieldLayoutId = $layout->id;
            }
        }

        $record->structureId = (int) $this->structureId;
        $record->handle = (string) $this->handle;
        $record->instructions = $this->instructions;
        $record->maxLevels = $this->maxLevels;
        $record->sortOrder = $this->sortOrder;
        $record->nodeFieldLayoutId = $this->nodeFieldLayoutId;
        $record->save(false);

        $this->saveSiteRows();

        parent::afterSave($isNew);
    }

    public function beforeDelete(): bool
    {
        if (!parent::beforeDelete()) {
            return false;
        }

        $nodes = Node::find()->menuId($this->id)->status(null)->all();
        foreach ($nodes as $node) {
            \Craft::$app->getElements()->deleteElement($node, true);
        }

        return true;
    }

    public function afterDelete(): void
    {
        if ($this->structureId) {
            \Craft::$app->getStructures()->deleteStructureById($this->structureId);
        }
        if ($this->nodeFieldLayoutId) {
            $layout = \Craft::$app->getFields()->getLayoutById($this->nodeFieldLayoutId);
            if ($layout) {
                \Craft::$app->getFields()->deleteLayout($layout);
            }
        }
        parent::afterDelete();
    }

    protected static function defineSources(string $context): array
    {
        return [
            [
                'key' => '*',
                'label' => \Craft::t('menu', 'All menus'),
                'defaultSort' => ['title', 'asc'],
            ],
        ];
    }

    protected static function defineTableAttributes(): array
    {
        return [
            'handle' => ['label' => \Craft::t('app', 'Handle')],
            'dateUpdated' => ['label' => \Craft::t('app', 'Date Updated')],
        ];
    }

    protected static function defineDefaultTableAttributes(string $source): array
    {
        return ['handle', 'dateUpdated'];
    }

    protected static function defineSortOptions(): array
    {
        return [
            'title' => \Craft::t('app', 'Title'),
            'handle' => \Craft::t('app', 'Handle'),
            'dateUpdated' => \Craft::t('app', 'Date Updated'),
        ];
    }

    protected function defineRules(): array
    {
        $rules = parent::defineRules();
        $rules[] = [['handle'], 'required'];
        $rules[] = [['handle'], 'string', 'max' => 64];
        $rules[] = [['handle'], 'match', 'pattern' => '/^[a-zA-Z][\w]*$/'];
        $rules[] = [['maxLevels'], 'integer', 'min' => 1, 'max' => 20];

        return $rules;
    }

    private function saveSiteRows(): void
    {
        if (!$this->id || $this->enabledSiteIds === null) {
            return;
        }

        $db = \Craft::$app->getDb();
        $db->createCommand()
            ->delete('{{%menu_menus_sites}}', ['menuId' => $this->id])
            ->execute()
        ;

        $now = (new \DateTime())->format('Y-m-d H:i:s');
        foreach ($this->enabledSiteIds as $siteId) {
            $db->createCommand()
                ->insert('{{%menu_menus_sites}}', [
                    'menuId' => $this->id,
                    'siteId' => (int) $siteId,
                    'enabled' => true,
                    'dateCreated' => $now,
                    'dateUpdated' => $now,
                    'uid' => StringHelper::UUID(),
                ])
                ->execute()
            ;
        }
    }
}
