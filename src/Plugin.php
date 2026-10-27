<?php

namespace rewish\craft\menu;

use craft\base\Model;
use craft\base\Plugin as BasePlugin;
use craft\console\Application as ConsoleApplication;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterGqlQueriesEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\events\RegisterUserPermissionsEvent;
use craft\services\Elements;
use craft\services\Gql;
use craft\services\UserPermissions;
use craft\web\twig\variables\CraftVariable;
use craft\web\UrlManager;
use rewish\craft\menu\elements\Menu;
use rewish\craft\menu\elements\Node;
use rewish\craft\menu\gql\queries\MenuQuery as MenuGqlQuery;
use rewish\craft\menu\models\Settings;
use rewish\craft\menu\services\ActiveMatcher;
use rewish\craft\menu\services\BuildSessions;
use rewish\craft\menu\services\DynamicSources;
use rewish\craft\menu\services\ImportExport;
use rewish\craft\menu\services\Menus;
use rewish\craft\menu\services\Migrators;
use rewish\craft\menu\services\Nodes;
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
 */
class Plugin extends BasePlugin
{
    public string $schemaVersion = '0.2.1';

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
        $this->registerPermissions();
        $this->registerCpRoutes();
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
        if ($user->can('menu:migrate')) {
            $subnav['migrate'] = ['label' => 'Migrate', 'url' => 'menu/migrate'];
        }
        if ($user->can('menu:settings')) {
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

    private function registerPermissions(): void
    {
        Event::on(UserPermissions::class, UserPermissions::EVENT_REGISTER_PERMISSIONS, static function (RegisterUserPermissionsEvent $event) {
            $nested = [
                'menu:editMenus' => ['label' => \Craft::t('menu', 'Create and edit all menus')],
                'menu:migrate' => ['label' => \Craft::t('menu', 'Run migrations from other plugins')],
                'menu:settings' => ['label' => \Craft::t('menu', 'Change Menu settings')],
            ];

            try {
                foreach (Menu::find()->all() as $menu) {
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
