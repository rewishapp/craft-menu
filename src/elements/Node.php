<?php

namespace rewish\craft\menu\elements;

use craft\base\Element;
use craft\base\ElementInterface;
use craft\elements\Asset;
use craft\elements\Category;
use craft\elements\Entry;
use craft\elements\User;
use craft\helpers\Json;
use craft\helpers\UrlHelper;
use craft\models\FieldLayout;
use rewish\craft\menu\elements\db\NodeQuery;
use rewish\craft\menu\records\NodeRecord;

class Node extends Element
{
    public const TYPE_CUSTOM = 'custom';

    public const TYPE_ENTRY = 'entry';

    public const TYPE_CATEGORY = 'category';

    public const TYPE_ASSET = 'asset';

    /** Label-only node (no URL). */
    public const TYPE_PASSIVE = 'passive';

    /** Links to a Craft site homepage; `elementId` stores the site ID. */
    public const TYPE_SITE = 'site';

    /** Projects children from a section or category group (`data` JSON). */
    public const TYPE_DYNAMIC = 'dynamic';

    /** Craft Commerce product link (requires craftcms/commerce). */
    public const TYPE_PRODUCT = 'product';

    public const TYPES = [
        self::TYPE_CUSTOM,
        self::TYPE_ENTRY,
        self::TYPE_CATEGORY,
        self::TYPE_ASSET,
        self::TYPE_PASSIVE,
        self::TYPE_SITE,
        self::TYPE_DYNAMIC,
        self::TYPE_PRODUCT,
    ];

    public ?int $menuId = null;

    public string $type = self::TYPE_CUSTOM;

    public ?int $elementId = null;

    public ?string $url = null;

    public ?string $classes = null;

    public ?string $urlSuffix = null;

    public bool $newWindow = false;

    public bool $followElement = true;

    public ?string $customAttributes = null;

    /** JSON bag for dynamic source settings, etc. */
    public ?string $data = null;

    /** Structure parent to apply after save (null = root). */
    public ?int $newParentId = null;

    private ?ElementInterface $_linkedElement = null;

    public static function displayName(): string
    {
        return \Craft::t('menu', 'Node');
    }

    public static function pluralDisplayName(): string
    {
        return \Craft::t('menu', 'Nodes');
    }

    public static function refHandle(): ?string
    {
        return 'node';
    }

    public static function hasTitles(): bool
    {
        return true;
    }

    public static function hasStatuses(): bool
    {
        return true;
    }

    public static function hasUris(): bool
    {
        return false;
    }

    public static function isLocalized(): bool
    {
        return true;
    }

    public static function find(): NodeQuery
    {
        return new NodeQuery(static::class);
    }

    public function getMenu(): ?Menu
    {
        return $this->menuId ? Menu::find()->id($this->menuId)->status(null)->one() : null;
    }

    public function getLinkedElement(): ?ElementInterface
    {
        if ($this->_linkedElement !== null) {
            return $this->_linkedElement;
        }
        if (!$this->elementId) {
            return null;
        }

        $this->_linkedElement = \Craft::$app->getElements()->getElementById($this->elementId, null, $this->siteId);

        return $this->_linkedElement;
    }

    public function getFieldLayout(): ?FieldLayout
    {
        $menu = $this->getMenu();
        if ($menu?->nodeFieldLayoutId) {
            return \Craft::$app->getFields()->getLayoutById($menu->nodeFieldLayoutId);
        }

        return parent::getFieldLayout();
    }

    /**
     * @return array<string, mixed>
     */
    public function getDataArray(): array
    {
        if (\is_array($this->data)) {
            return $this->data;
        }
        if (!$this->data) {
            return [];
        }

        $decoded = Json::decodeIfJson($this->data);

        return \is_array($decoded) ? $decoded : [];
    }

    public function setDataArray(array $data): void
    {
        $this->data = $data === [] ? null : Json::encode($data);
    }

    public function getNodeUrl(): ?string
    {
        $suffix = $this->urlSuffix ?? '';
        if ($this->type === self::TYPE_PASSIVE || $this->type === self::TYPE_DYNAMIC) {
            return null;
        }
        if ($this->type === self::TYPE_CUSTOM) {
            return ($this->url ?? '').$suffix;
        }
        if ($this->type === self::TYPE_SITE) {
            $site = $this->elementId ? \Craft::$app->getSites()->getSiteById((int) $this->elementId) : null;

            return $site ? rtrim($site->getBaseUrl() ?? '', '/').'/'.$suffix : null;
        }

        $element = $this->getLinkedElement();
        if ($element instanceof ElementInterface) {
            try {
                $url = $element->getUrl();
            } catch (\Throwable) {
                $url = null;
            }

            return $url ? $url.$suffix : null;
        }

        return $this->url ? $this->url.$suffix : null;
    }

    public function hasUrl(): bool
    {
        return !\in_array($this->type, [self::TYPE_PASSIVE, self::TYPE_DYNAMIC], true);
    }

    public function getCpEditUrl(): ?string
    {
        if (!$this->menuId || !$this->id) {
            return null;
        }

        return UrlHelper::cpUrl('menu/'.$this->menuId.'/nodes', ['nodeId' => $this->id]);
    }

    public function canView(User $user): bool
    {
        $menu = $this->getMenu();

        return $menu ? $menu->canView($user) : $user->can('menu:access');
    }

    public function canSave(User $user): bool
    {
        $menu = $this->getMenu();

        return $menu ? $menu->canSave($user) : $user->can('menu:editMenus');
    }

    public function canDelete(User $user): bool
    {
        return $this->canSave($user);
    }

    public function beforeSave(bool $isNew): bool
    {
        if ($this->type === self::TYPE_PASSIVE || $this->type === self::TYPE_DYNAMIC) {
            $this->url = null;
            $this->elementId = null;
            $this->followElement = false;
        } elseif ($this->type === self::TYPE_SITE) {
            $this->url = null;
            $this->followElement = false;
        } elseif ($this->followElement && $this->elementId && \in_array($this->type, [self::TYPE_ENTRY, self::TYPE_CATEGORY, self::TYPE_ASSET, self::TYPE_PRODUCT], true)) {
            $element = $this->getLinkedElement();
            if ($element instanceof ElementInterface && $element->title) {
                $this->title = $element->title;
            }
        }

        return parent::beforeSave($isNew);
    }

    public function afterSave(bool $isNew): void
    {
        $record = $isNew ? new NodeRecord() : (NodeRecord::findOne($this->id) ?? new NodeRecord());
        $record->id = $this->id;
        $record->menuId = (int) $this->menuId;
        $record->type = $this->type;
        $record->elementId = $this->elementId;
        $record->url = $this->url;
        $record->classes = $this->classes;
        $record->urlSuffix = $this->urlSuffix;
        $record->newWindow = $this->newWindow;
        $record->followElement = $this->followElement;
        $record->customAttributes = \is_array($this->customAttributes)
            ? Json::encode($this->customAttributes)
            : $this->customAttributes;
        $record->data = \is_array($this->data) ? Json::encode($this->data) : $this->data;
        $record->save(false);

        $menu = $this->getMenu();
        if ($menu?->structureId) {
            $structures = \Craft::$app->getStructures();
            // newParentId null = root; set by CP/import before save.
            $parentId = $this->newParentId;
            if ($parentId) {
                $parent = self::find()->id($parentId)->menuId($this->menuId)->status(null)->one();
                if ($parent) {
                    if ($isNew || (int) $this->getParent()?->id !== (int) $parentId) {
                        $structures->append($menu->structureId, $this, $parent);
                    } else {
                        $structures->updateElement($this);
                    }
                } elseif ($isNew) {
                    $structures->appendToRoot($menu->structureId, $this);
                } else {
                    $structures->updateElement($this);
                }
            } elseif ($isNew || $this->getParent()) {
                $structures->appendToRoot($menu->structureId, $this);
            } else {
                $structures->updateElement($this);
            }
        }

        parent::afterSave($isNew);
    }

    public function getLevelIndent(): int
    {
        return max(0, (int) $this->level - 1);
    }

    public function getCustomAttributesArray(): array
    {
        if (\is_array($this->customAttributes)) {
            return $this->customAttributes;
        }
        if (!$this->customAttributes) {
            return [];
        }

        return Json::decodeIfJson($this->customAttributes) ?: [];
    }

    public static function commerceProductClass(): ?string
    {
        $class = 'craft\commerce\elements\Product';

        return class_exists($class) ? $class : null;
    }

    public static function elementClassForType(string $type): ?string
    {
        return match ($type) {
            self::TYPE_ENTRY => Entry::class,
            self::TYPE_CATEGORY => Category::class,
            self::TYPE_ASSET => Asset::class,
            self::TYPE_PRODUCT => self::commerceProductClass(),
            default => null,
        };
    }

    public static function commerceInstalled(): bool
    {
        return self::commerceProductClass() !== null;
    }

    protected static function defineSources(string $context): array
    {
        return [
            [
                'key' => '*',
                'label' => \Craft::t('menu', 'All nodes'),
                'defaultSort' => ['structure', 'asc'],
            ],
        ];
    }

    protected function defineRules(): array
    {
        $rules = parent::defineRules();
        $rules[] = [['menuId', 'type'], 'required'];
        $rules[] = [['type'], 'in', 'range' => self::TYPES];
        $rules[] = [['url'], 'required', 'when' => fn () => $this->type === self::TYPE_CUSTOM];
        $rules[] = [['elementId'], 'required', 'when' => fn () => \in_array($this->type, [self::TYPE_ENTRY, self::TYPE_CATEGORY, self::TYPE_ASSET, self::TYPE_SITE, self::TYPE_PRODUCT], true)];

        return $rules;
    }
}
