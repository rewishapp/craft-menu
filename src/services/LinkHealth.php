<?php

namespace rewish\craft\menu\services;

use craft\base\Component;
use craft\base\ElementInterface;
use rewish\craft\menu\elements\Menu;
use rewish\craft\menu\elements\Node;
use rewish\craft\menu\helpers\NodeMeta;
use rewish\craft\menu\Plugin;

/**
 * Cheap, offline health checks for node links (no HTTP requests).
 */
class LinkHealth extends Component
{
    public const STATUS_OK = 'ok';

    public const STATUS_WARNING = 'warning';

    public const STATUS_BROKEN = 'broken';

    /**
     * @return array{status: string, message: string}
     */
    public function assess(Node $node): array
    {
        switch ($node->type) {
            case Node::TYPE_PASSIVE:
            case Node::TYPE_DYNAMIC:
            case Node::TYPE_SEPARATOR:
                return $this->result(self::STATUS_OK, '');

            case Node::TYPE_ANCHOR:
                return $this->assessAnchor((string) $node->url);

            case Node::TYPE_CUSTOM:
                return $this->assessUrl((string) $node->url);

            case Node::TYPE_SITE:
                $site = $node->elementId ? \Craft::$app->getSites()->getSiteById((int) $node->elementId) : null;

                return $site
                    ? $this->result(self::STATUS_OK, '')
                    : $this->result(self::STATUS_BROKEN, \Craft::t('menu', 'The linked site no longer exists.'));
        }

        return $this->assessElement($node);
    }

    /**
     * Pure URL heuristics for custom links.
     *
     * @return array{status: string, message: string}
     */
    public function assessUrl(string $url): array
    {
        $url = trim($url);
        if ($url === '') {
            return $this->result(self::STATUS_BROKEN, \Craft::t('menu', 'No URL set.'));
        }
        if ($url === '#') {
            return $this->result(self::STATUS_WARNING, \Craft::t('menu', 'Placeholder URL (#).'));
        }
        if (preg_match('/^\s*(javascript|data|vbscript):/i', $url)) {
            return $this->result(self::STATUS_BROKEN, \Craft::t('menu', 'Unsafe URL scheme.'));
        }
        if (preg_match('/\s/', $url)) {
            return $this->result(self::STATUS_WARNING, \Craft::t('menu', 'URL contains spaces.'));
        }
        if (preg_match('#^http://#i', $url)) {
            return $this->result(self::STATUS_WARNING, \Craft::t('menu', 'Link is not using HTTPS.'));
        }
        if (!preg_match('#^(https?://|mailto:|tel:|/|\#|\?|\{)#i', $url) && !str_contains($url, '.')) {
            return $this->result(self::STATUS_WARNING, \Craft::t('menu', 'URL has no leading slash or scheme.'));
        }

        return $this->result(self::STATUS_OK, '');
    }

    /**
     * @return array{status: string, message: string}
     */
    public function assessAnchor(string $fragment): array
    {
        $fragment = ltrim(trim($fragment), '#');
        if ($fragment === '') {
            return $this->result(self::STATUS_WARNING, \Craft::t('menu', 'Anchor has no fragment.'));
        }
        if (preg_match('/\s/', $fragment)) {
            return $this->result(self::STATUS_WARNING, \Craft::t('menu', 'Anchor contains spaces.'));
        }

        return $this->result(self::STATUS_OK, '');
    }

    /**
     * Assess every node of a menu and count results.
     *
     * @return array{total: int, ok: int, warning: int, broken: int, issues: list<array{nodeId: int, title: string, status: string, message: string}>}
     */
    public function summary(Menu $menu, ?int $siteId = null): array
    {
        $nodes = Plugin::getInstance()?->nodes->getForMenu((int) $menu->id, $siteId, true) ?? [];

        return $this->summarize($nodes);
    }

    /**
     * @param iterable<Node> $nodes
     *
     * @return array{total: int, ok: int, warning: int, broken: int, issues: list<array{nodeId: int, title: string, status: string, message: string}>}
     */
    public function summarize(iterable $nodes): array
    {
        $summary = ['total' => 0, 'ok' => 0, 'warning' => 0, 'broken' => 0, 'issues' => []];
        foreach ($nodes as $node) {
            $result = $this->assess($node);
            ++$summary['total'];
            ++$summary[$result['status']];
            if ($result['status'] !== self::STATUS_OK) {
                $summary['issues'][] = [
                    'nodeId' => (int) $node->id,
                    'title' => (string) $node->title,
                    'status' => $result['status'],
                    'message' => $result['message'],
                ];
            }
        }

        return $summary;
    }

    /**
     * @return array{status: string, message: string}
     */
    private function assessElement(Node $node): array
    {
        if (!$node->elementId) {
            return $this->result(self::STATUS_BROKEN, \Craft::t('menu', 'No element selected.'));
        }

        $element = \Craft::$app->getElements()->getElementById((int) $node->elementId, null, $node->siteId, ['status' => null]);
        if (!$element instanceof ElementInterface) {
            return $this->result(self::STATUS_BROKEN, \Craft::t('menu', 'The linked element no longer exists.'));
        }

        $meta = $node->getMeta();
        $hasFallback = $meta['fallback'] === NodeMeta::FALLBACK_URL && $meta['fallbackUrl'];

        if (!$element->enabled || !$element->getEnabledForSite()) {
            return $hasFallback
                ? $this->result(self::STATUS_WARNING, \Craft::t('menu', 'The linked element is disabled; using the fallback URL.'))
                : $this->result(self::STATUS_BROKEN, \Craft::t('menu', 'The linked element is disabled.'));
        }

        try {
            $url = $element->getUrl();
        } catch (\Throwable) {
            $url = null;
        }
        if (!$url) {
            return $this->result(self::STATUS_WARNING, \Craft::t('menu', 'The linked element has no URL.'));
        }

        return $this->result(self::STATUS_OK, '');
    }

    /**
     * @return array{status: string, message: string}
     */
    private function result(string $status, string $message): array
    {
        return ['status' => $status, 'message' => $message];
    }
}
