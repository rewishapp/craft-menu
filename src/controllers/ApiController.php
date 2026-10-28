<?php

namespace rewish\craft\menu\controllers;

use craft\helpers\App;
use craft\web\Controller;
use rewish\craft\menu\Plugin;
use yii\web\ForbiddenHttpException;
use yii\web\MethodNotAllowedHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Read-only JSON API. Disabled unless a token is set under Menu → Settings.
 *
 * Send the token as `?token=…` or an `Authorization: Bearer …` header.
 */
class ApiController extends Controller
{
    protected array|bool|int $allowAnonymous = self::ALLOW_ANONYMOUS_LIVE;

    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        if (!$this->request->getIsGet() && !$this->request->getIsHead()) {
            throw new MethodNotAllowedHttpException('Only GET is supported.');
        }

        $expected = self::expectedToken();
        if ($expected === '') {
            // No token configured → the API doesn't exist.
            throw new NotFoundHttpException();
        }
        if (!hash_equals($expected, $this->providedToken())) {
            throw new ForbiddenHttpException('Invalid token.');
        }

        return true;
    }

    public function actionIndex(): Response
    {
        $out = [];
        foreach (Plugin::getInstance()->menus->getAll(null, true) as $menu) {
            $out[] = [
                'id' => (int) $menu->id,
                'title' => (string) $menu->title,
                'handle' => (string) $menu->handle,
                'cssClass' => $menu->cssClass,
            ];
        }

        return $this->asJson(['menus' => $out]);
    }

    public function actionTree(string $handle): Response
    {
        $plugin = Plugin::getInstance();
        $menu = $plugin->menus->getByHandle($handle);
        if (!$menu?->id) {
            throw new NotFoundHttpException('Menu not found.');
        }

        $siteParam = $this->request->getQueryParam('siteId');
        $siteId = is_numeric($siteParam) ? (int) $siteParam : (int) \Craft::$app->getSites()->getCurrentSite()->id;

        $tree = $plugin->nodes->getTree((int) $menu->id, $siteId);
        $uri = $this->request->getQueryParam('uri');
        if (\is_string($uri)) {
            $tree = $plugin->activeMatcher->annotateTree($tree, $uri);
        }

        return $this->asJson([
            'menu' => [
                'id' => (int) $menu->id,
                'title' => (string) $menu->title,
                'handle' => (string) $menu->handle,
                'cssClass' => $menu->cssClass,
                'htmlAttributes' => $menu->getHtmlAttributesArray(),
            ],
            'siteId' => $siteId,
            'nodes' => $plugin->nodes->treeToArray($tree),
        ]);
    }

    public static function expectedToken(): string
    {
        return trim((string) App::parseEnv((string) Plugin::settings()->apiToken));
    }

    private function providedToken(): string
    {
        $token = $this->request->getQueryParam('token');
        if (\is_string($token) && $token !== '') {
            return $token;
        }

        $header = (string) $this->request->getHeaders()->get('Authorization', '');
        if (preg_match('/^Bearer\s+(.+)$/i', $header, $m)) {
            return trim($m[1]);
        }

        return '';
    }
}
