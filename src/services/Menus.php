<?php

namespace rewish\craft\menu\services;

use craft\base\Component;
use craft\elements\User;
use rewish\craft\menu\elements\Menu;

class Menus extends Component
{
    /**
     * @return list<Menu>
     */
    public function getAll(?User $user = null, bool $enabledOnly = false): array
    {
        $query = Menu::find()->orderBy(['sortOrder' => \SORT_ASC, 'title' => \SORT_ASC]);
        if (!$enabledOnly) {
            $query->status(null);
        }
        $menus = $query->all();
        if ($user === null) {
            return $menus;
        }

        return array_values(array_filter(
            $menus,
            static fn (Menu $menu) => $menu->canView($user)
        ));
    }

    /**
     * @param bool $anyStatus include disabled menus (CP / import); front-end callers leave this off
     */
    public function getByHandle(string $handle, bool $anyStatus = false): ?Menu
    {
        $query = Menu::find()->handle($handle);
        if ($anyStatus) {
            $query->status(null);
        }

        return $query->one();
    }

    public function getById(int $id): ?Menu
    {
        return Menu::find()->id($id)->status(null)->one();
    }

    public function canManage(?Menu $menu, ?User $user = null): bool
    {
        $user ??= \Craft::$app->getUser()->getIdentity();
        if (!$user) {
            return false;
        }
        if ($user->can('menu:editMenus')) {
            return true;
        }
        if (!$menu) {
            return false;
        }

        return $menu->canSave($user);
    }
}
