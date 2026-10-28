<?php

namespace rewish\craft\menu\services;

use craft\base\Component;
use craft\helpers\App;
use rewish\craft\menu\elements\Node;
use rewish\craft\menu\helpers\NodeMeta;

/**
 * Hides nodes (and their whole subtree) when disabled, unavailable, or failing visibility rules.
 */
class Visibility extends Component
{
    /**
     * Drop every item that isn't visible; a hidden node takes its children with it.
     *
     * @param list<array{node: object, children: list}> $tree
     * @param null|array<string, mixed>                 $context see {@see buildContext()}
     *
     * @return list<array{node: object, children: list}>
     */
    public function filterTree(array $tree, ?int $siteId = null, ?array $context = null): array
    {
        $context ??= $this->buildContext($siteId);

        $out = [];
        foreach ($tree as $item) {
            $node = $item['node'] ?? null;
            if (!\is_object($node) || !$this->isVisible($node, $context)) {
                continue;
            }
            $item['children'] = $this->filterTree($item['children'] ?? [], $siteId, $context);
            $out[] = $item;
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $context
     */
    public function isVisible(object $node, array $context): bool
    {
        if ($node instanceof Node) {
            if (!$node->enabled || !$node->getEnabledForSite() || !$node->isAvailable()) {
                return false;
            }
        }

        $rules = NodeMeta::forObject($node)['visibility'] ?? [];
        foreach ($rules as $rule) {
            if (!$this->evaluateRule($rule, $context)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array{type?: string, value?: mixed} $rule
     * @param array<string, mixed>                $context
     */
    public function evaluateRule(array $rule, array $context): bool
    {
        $type = (string) ($rule['type'] ?? 'always');
        $value = $rule['value'] ?? null;

        switch ($type) {
            case 'always':
                return true;

            case 'loggedIn':
                return !empty($context['loggedIn']);

            case 'loggedOut':
                return empty($context['loggedIn']);

            case 'userGroup':
                if (empty($context['loggedIn'])) {
                    return false;
                }
                $needle = trim((string) $value);
                if ($needle === '') {
                    return true;
                }
                if (is_numeric($needle)) {
                    return \in_array((int) $needle, array_map('intval', $context['groupIds'] ?? []), true);
                }

                return \in_array($needle, $context['groupHandles'] ?? [], true);

            case 'site':
                if (!is_numeric($value)) {
                    return true;
                }

                return (int) $value === (int) ($context['siteId'] ?? 0);

            case 'dateRange':
                return $this->inDateRange($value, (string) ($context['today'] ?? date('Y-m-d')));

            case 'environment':
                $env = strtolower(trim((string) ($context['environment'] ?? '')));
                $allowed = array_filter(array_map(
                    static fn (string $v) => strtolower(trim($v)),
                    preg_split('/[\s,|]+/', (string) $value) ?: []
                ));

                return $allowed === [] || \in_array($env, $allowed, true);
        }

        // Unknown rule types never hide a node.
        return true;
    }

    /**
     * @return array{loggedIn: bool, groupIds: list<int>, groupHandles: list<string>, siteId: int, today: string, environment: string}
     */
    public function buildContext(?int $siteId = null): array
    {
        $user = null;

        try {
            $user = \Craft::$app->getUser()->getIdentity();
        } catch (\Throwable) {
            // Console / no user component.
        }

        $groupIds = [];
        $groupHandles = [];
        if ($user) {
            foreach ($user->getGroups() as $group) {
                $groupIds[] = (int) $group->id;
                $groupHandles[] = (string) $group->handle;
            }
        }

        try {
            $siteId ??= (int) \Craft::$app->getSites()->getCurrentSite()->id;
        } catch (\Throwable) {
            $siteId ??= 0;
        }

        $env = App::env('CRAFT_ENVIRONMENT') ?: (\Craft::$app->env ?? '');

        return [
            'loggedIn' => $user !== null,
            'groupIds' => $groupIds,
            'groupHandles' => $groupHandles,
            'siteId' => $siteId,
            'today' => (new \DateTimeImmutable('now'))->format('Y-m-d'),
            'environment' => (string) $env,
        ];
    }

    private function inDateRange(mixed $value, string $today): bool
    {
        $range = NodeMeta::normalizeDateRange($value);
        if ($range === null) {
            return true;
        }
        if ($range['from'] !== null && $today < $range['from']) {
            return false;
        }
        if ($range['to'] !== null && $today > $range['to']) {
            return false;
        }

        return true;
    }
}
