<?php

namespace rewish\craft\menu\fields;

use craft\base\ElementInterface;
use craft\base\Field;
use craft\helpers\Cp;
use GraphQL\Type\Definition\Type;
use rewish\craft\menu\elements\Menu;
use rewish\craft\menu\gql\types\MenuType;
use rewish\craft\menu\Plugin;
use yii\db\Schema;

/**
 * Dropdown that stores a menu handle; the field value is the {@see Menu} (or null).
 */
class MenuField extends Field
{
    public static function displayName(): string
    {
        return \Craft::t('menu', 'Menu');
    }

    public static function icon(): string
    {
        return 'bars';
    }

    public static function phpType(): string
    {
        return '\\'.Menu::class.'|null';
    }

    public static function dbType(): array|string|null
    {
        return Schema::TYPE_STRING;
    }

    public function normalizeValue(mixed $value, ?ElementInterface $element): mixed
    {
        if ($value instanceof Menu) {
            return $value;
        }
        if (!\is_string($value) || trim($value) === '') {
            return null;
        }

        return Plugin::getInstance()?->menus->getByHandle(trim($value));
    }

    public function serializeValue(mixed $value, ?ElementInterface $element): mixed
    {
        if ($value instanceof Menu) {
            return $value->handle;
        }

        return \is_string($value) && $value !== '' ? $value : null;
    }

    public function getContentGqlType(): array|Type
    {
        return MenuType::getType();
    }

    /**
     * @return list<array{label: string, value: string}>
     */
    public function getOptions(): array
    {
        $options = [['label' => \Craft::t('menu', 'Choose a menu'), 'value' => '']];
        foreach (Plugin::getInstance()?->menus->getAll() ?? [] as $menu) {
            $options[] = ['label' => (string) $menu->title, 'value' => (string) $menu->handle];
        }

        return $options;
    }

    protected function inputHtml(mixed $value, ?ElementInterface $element, bool $inline): string
    {
        $handle = $value instanceof Menu ? (string) $value->handle : '';

        return Cp::selectHtml([
            'id' => $this->getInputId(),
            'name' => $this->handle,
            'options' => $this->getOptions(),
            'value' => $handle,
        ]);
    }
}
