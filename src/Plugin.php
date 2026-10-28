<?php

namespace rewish\craft\menu;

use craft\base\Model;
use craft\base\Plugin as BasePlugin;
use craft\console\Application as ConsoleApplication;
use craft\events\ElementEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterGqlQueriesEvent;
use craft\events\RegisterTemplateRootsEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\events\RegisterUserPermissionsEvent;
use craft\services\Elements;
use craft\services\Fields;
use craft\services\Gql;
use craft\services\UserPermissions;
use craft\web\twig\variables\CraftVariable;
use craft\web\UrlManager;
use craft\web\View;
use rewish\craft\menu\elements\Menu;
use rewish\craft\menu\elements\Node;
use rewish\craft\menu\fields\MenuField;
use rewish\craft\menu\gql\queries\MenuQuery as MenuGqlQuery;
use rewish\craft\menu\models\Settings;
use rewish\craft\menu\services\ActiveMatcher;
use rewish\craft\menu\services\BuildSessions;
use rewish\craft\menu\services\Duplicators;
use rewish\craft\menu\services\DynamicSources;
use rewish\craft\menu\services\ImportExport;
use rewish\craft\menu\services\LinkHealth;
use rewish\craft\menu\services\MenuCache;
use rewish\craft\menu\services\Menus;
use rewish\craft\menu\services\Migrators;
use rewish\craft\menu\services\Nodes;
use rewish\craft\menu\services\Visibility;
use rewish\craft\menu\variables\MenuVariable;
use yii\base\Event;

/**
 * @property Menus          $menus
 * @property Nodes          $nodes
 * @property Migrators      $migrators
 * @property ImportExport   $importExport
 * @property ActiveMatcher  $activeMatcher
 * @property DynamicSources $dynamicSources
 * @property BuildSessions  $buildSessions
 * @property Visibility     $visibility
 * @property LinkHealth     $linkHealth
 * @property Duplicators    $duplicators
 * @property MenuCache      $menuCache
 */
class Plugin extends BasePlugin
{
    public string $schemaVersion = '0.3.0';

    public bool $hasCpSection = true;

    public bool $hasCpSettings = true;

    public static function config(): array
    {
        return [
            'components' => [
                'menus' => Menus::class,
                'nodes' => Nodes::class,
                'migrators' => Migrators::class,
                'importExport' => ImportExport::class,
                'activeMatcher' => ActiveMatcher::class,
                'dynamicSources' => DynamicSources::class,
                'buildSessions' => BuildSessions::class,
                'visibility' => Visibility::class,
                'linkHealth' => LinkHealth::class,
                'duplicators' => Duplicators::class,
                'menuCache' => MenuCache::class,
            ],
        ];
    }

    public function init(): void
    {
        parent::init();

        \Craft::setAlias('@menu', $this->getBasePath());

        if (\Craft::$app instanceof ConsoleApplication) {
            $this->controllerNamespace = 'rewish\craft\menu\console\controllers';
        }

        $this->registerElementTypes();
        $this->registerFieldTypes();
        $this->registerPermissions();
        $this->registerCpRoutes();
        $this->registerSiteRoutes();
        $this->registerTemplateRoots();
        $this->registerCacheInvalidation();
        $this->registerTwigVariable();
        $this->registerGql();
    }

    public static function getInstance(): ?self
    {
        // @var null|self $plugin
        return parent::getInstance();
    }

    public static function settings(): Settings
    {
        $plugin = self::getInstance();
        if (!$plugin instanceof self) {
            return new Settings();
        }

        $settings = $plugin->getSettings();

        return $settings instanceof Settings ? $settings : new Settings();
    }

    public function getCpNavItem(): ?array
    {
        $user = \Craft::$app->getUser()->getIdentity();
        if ($user === null || !$user->can('menu:access')) {
            return null;
        }

        $item = parent::getCpNavItem();
        $item['label'] = 'Menu';
        $item['url'] = 'menu';
        $subnav = [
            'menus' => ['label' => 'Menus', 'url' => 'menu'],
        ];
        if ($user->can('menu:settings') || $user->can('menu:migrate')) {
            $subnav['settings'] = ['label' => 'Settings', 'url' => 'menu/settings'];
        }
        $item['subnav'] = $subnav;

        return $item;
    }

    public function getSettingsResponse(): mixed
    {
        return \Craft::$app->getResponse()->redirect('menu/settings');
    }

    protected function createSettingsModel(): ?Model
    {
        return new Settings();
    }

    private function registerElementTypes(): void
    {
        Event::on(Elements::class, Elements::EVENT_REGISTER_ELEMENT_TYPES, static function (RegisterComponentTypesEvent $event) {
            $event->types[] = Menu::class;
            $event->types[] = Node::class;
        });
    }

    private function registerFieldTypes(): void
    {
        Event::on(Fields::class, Fields::EVENT_REGISTER_FIELD_TYPES, static function (RegisterComponentTypesEvent $event) {
            $event->types[] = MenuField::class;
        });
    }

    private function registerTemplateRoots(): void
    {
        $register = function (RegisterTemplateRootsEvent $event) {
            $event->roots['menu'] = $this->getBasePath().'/templates';
        };
        // Lets site + CP templates `{% import 'menu/_macros/nav' as nav %}`.
        Event::on(View::class, View::EVENT_REGISTER_SITE_TEMPLATE_ROOTS, $register);
        Event::on(View::class, View::EVENT_REGISTER_CP_TEMPLATE_ROOTS, $register);
    }

    private function registerCacheInvalidation(): void
    {
        $invalidate = static function (ElementEvent $event) {
            $element = $event->element;
            if (!$element instanceof Menu && !$element instanceof Node) {
                return;
            }
            $menu = $element instanceof Menu ? $element : $element->getMenu();
            $self = self::getInstance();
            if ($self && $menu?->handle) {
                $self->menuCache->invalidate((string) $menu->handle);
            }
        };
        Event::on(Elements::class, Elements::EVENT_AFTER_SAVE_ELEMENT, $invalidate);
        Event::on(Elements::class, Elements::EVENT_AFTER_DELETE_ELEMENT, $invalidate);
    }

    private function registerSiteRoutes(): void
    {
        Event::on(UrlManager::class, UrlManager::EVENT_REGISTER_SITE_URL_RULES, static function (RegisterUrlRulesEvent $event) {
            $event->rules['menu-api/menus'] = 'menu/api/index';
            $event->rules['menu-api/menus/<handle:[\w-]+>'] = 'menu/api/tree';
        });
    }

    private function registerPermissions(): void
    {
        Event::on(UserPermissions::class, UserPermissions::EVENT_REGISTER_PERMISSIONS, static function (RegisterUserPermissionsEvent $event) {
            $nested = [
                'menu:editMenus' => ['label' => \Craft::t('menu', 'Create and edit all menus')],
                'menu:migrate' => ['label' => \Craft::t('menu', 'Run migrations from other plugins')],
                'menu:settings' => ['label' => \Craft::t('menu', 'Change Menu settings')],
            ];

            try {
                foreach (Menu::find()->status(null)->all() as $menu) {
                    if (!$menu->uid) {
                        continue;
                    }
                    $nested[Menu::managePermissionName($menu->uid)] = [
                        'label' => \Craft::t('menu', 'Manage “{name}” menu', ['name' => $menu->title]),
                    ];
                }
            } catch (\Throwable) {
                // Install / migrate before tables exist.
            }

            $event->permissions[] = [
                'heading' => 'Menu',
                'permissions' => [
                    'menu:access' => [
                        'label' => \Craft::t('menu', 'Access Menu'),
                        'nested' => $nested,
                    ],
                ],
            ];
        });
    }

    private function registerCpRoutes(): void
    {
        Event::on(UrlManager::class, UrlManager::EVENT_REGISTER_CP_URL_RULES, static function (RegisterUrlRulesEvent $event) {
            $event->rules['menu'] = 'menu/menus/index';
            $event->rules['menu/new'] = 'menu/menus/edit';
            $event->rules['menu/<menuId:\d+>'] = 'menu/menus/edit';
            $event->rules['menu/<menuId:\d+>/nodes'] = 'menu/nodes/index';
            $event->rules['menu/<menuId:\d+>/nodes/list'] = 'menu/nodes/list';
            $event->rules['menu/<menuId:\d+>/preview'] = 'menu/preview/index';
            $event->rules['menu/<menuId:\d+>/export'] = 'menu/import-export/export';
            $event->rules['menu/import'] = 'menu/import-export/import';
            $event->rules['menu/settings'] = 'menu/settings/index';
            $event->rules['menu/migrate'] = 'menu/migrate/index';
        });
    }

    private function registerTwigVariable(): void
    {
        Event::on(CraftVariable::class, CraftVariable::EVENT_INIT, static function (Event $event) {
            /** @var CraftVariable $variable */
            $variable = $event->sender;
            $variable->set('menu', MenuVariable::class);
        });
    }

    private function registerGql(): void
    {
        Event::on(Gql::class, Gql::EVENT_REGISTER_GQL_QUERIES, static function (RegisterGqlQueriesEvent $event) {
            $event->queries = array_merge($event->queries, MenuGqlQuery::getQueries());
        });
    }
}
