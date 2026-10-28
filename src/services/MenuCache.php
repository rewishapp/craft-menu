<?php

namespace rewish\craft\menu\services;

use craft\base\Component;
use yii\caching\TagDependency;

/**
 * Thin wrapper over Craft's cache for rendered menu data, keyed by handle + site.
 */
class MenuCache extends Component
{
    public const TAG = 'menu';

    public int $duration = 3600;

    public function get(string $handle, ?int $siteId = null): mixed
    {
        $value = \Craft::$app->getCache()->get($this->key($handle, $siteId));

        return $value === false ? null : $value;
    }

    public function set(string $handle, mixed $value, ?int $siteId = null, ?int $duration = null): bool
    {
        return \Craft::$app->getCache()->set(
            $this->key($handle, $siteId),
            $value,
            $duration ?? $this->duration,
            new TagDependency(['tags' => [self::TAG, $this->tag($handle)]])
        );
    }

    /**
     * Invalidate one menu (all sites), or everything when `$handle` is null.
     */
    public function invalidate(?string $handle = null): void
    {
        TagDependency::invalidate(\Craft::$app->getCache(), $handle === null ? self::TAG : $this->tag($handle));
    }

    public function key(string $handle, ?int $siteId = null): string
    {
        return 'menu:tree:'.$handle.':'.($siteId ?? 0);
    }

    private function tag(string $handle): string
    {
        return self::TAG.':'.$handle;
    }
}
