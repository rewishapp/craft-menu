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
    public function getAll(?User $user = null): array
    {
        $menus = Menu::find()->orderBy(['sortOrder' => \SORT_ASC, 'title' => \SORT_ASC])->all();
        if ($user === null) {
            return $menus;
        }

        return array_values(array_filter(
            $menus,
            static fn (Menu $menu) => $menu->canView($user)
        ));
    }

    public function getByHandle(string $handle): ?Menu
    {
        return Menu::find()->handle($handle)->one();
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
