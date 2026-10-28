<?php

namespace rewish\craft\menu\helpers;

use craft\helpers\Json;

/**
 * Reads and writes presentation / behavior extras stored under `data['meta']`
 * on a node, without touching other keys (e.g. dynamic source settings).
 */
final class NodeMeta
{
    public const KEY = 'meta';

    public const FALLBACK_HIDE = 'hide';

    public const FALLBACK_UNLINK = 'unlink';

    public const FALLBACK_URL = 'url';

    public const FALLBACKS = [self::FALLBACK_HIDE, self::FALLBACK_UNLINK, self::FALLBACK_URL];

    public const BADGE_STYLES = ['default', 'info', 'success', 'warning', 'critical'];

    public const VISIBILITY_TYPES = ['always', 'loggedIn', 'loggedOut', 'userGroup', 'site', 'dateRange', 'environment'];

    private const STRING_KEYS = [
        'htmlId', 'ariaLabel', 'titleAttr', 'rel', 'fallbackUrl', 'badgeText', 'iconClass', 'description',
    ];

    private const BOOL_KEYS = [
        'clickable', 'featured', 'megaEnabled', 'mobileHidden', 'mobileCollapsible',
    ];

    private const ID_KEYS = ['iconAssetId', 'imageId'];

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'htmlId' => null,
            'ariaLabel' => null,
            'titleAttr' => null,
            'rel' => null,
            'clickable' => true,
            'fallback' => self::FALLBACK_UNLINK,
            'fallbackUrl' => null,
            'badgeText' => null,
            'badgeStyle' => 'default',
            'iconClass' => null,
            'iconAssetId' => null,
            'description' => null,
            'imageId' => null,
            'featured' => false,
            'megaEnabled' => false,
            'megaColumns' => 1,
            'megaColumn' => 1,
            'mobileHidden' => false,
            'mobileOrder' => null,
            'mobileCollapsible' => false,
            'visibility' => [],
        ];
    }

    /**
     * Raw `data` (array, JSON string, or null) → normalized meta with defaults applied.
     *
     * @return array<string, mixed>
     */
    public static function fromData(mixed $data): array
    {
        $array = self::decode($data);
        $stored = $array[self::KEY] ?? [];

        return self::normalize(\is_array($stored) ? $stored : []);
    }

    /**
     * Meta for a node-like object (Node, ProjectedNode, anything with getDataArray()/meta).
     *
     * @return array<string, mixed>
     */
    public static function forObject(object $node): array
    {
        if (method_exists($node, 'getMeta')) {
            $meta = $node->getMeta();

            return \is_array($meta) ? $meta : self::defaults();
        }

        return self::defaults();
    }

    /**
     * Fill in defaults and coerce types for known keys. Unknown keys are dropped.
     *
     * @param array<string, mixed> $meta
     *
     * @return array<string, mixed>
     */
    public static function normalize(array $meta): array
    {
        $out = self::defaults();

        foreach (self::STRING_KEYS as $key) {
            if (\array_key_exists($key, $meta)) {
                $value = \is_scalar($meta[$key]) ? trim((string) $meta[$key]) : '';
                $out[$key] = $value === '' ? null : $value;
            }
        }
        foreach (self::BOOL_KEYS as $key) {
            if (\array_key_exists($key, $meta)) {
                $out[$key] = self::toBool($meta[$key]);
            }
        }
        foreach (self::ID_KEYS as $key) {
            if (\array_key_exists($key, $meta)) {
                $out[$key] = self::toId($meta[$key]);
            }
        }

        if (\array_key_exists('fallback', $meta)) {
            $fallback = \is_string($meta['fallback']) ? trim($meta['fallback']) : '';
            $out['fallback'] = \in_array($fallback, self::FALLBACKS, true) ? $fallback : self::FALLBACK_UNLINK;
        }
        if (\array_key_exists('badgeStyle', $meta)) {
            $style = \is_string($meta['badgeStyle']) ? trim($meta['badgeStyle']) : '';
            $out['badgeStyle'] = \in_array($style, self::BADGE_STYLES, true) ? $style : 'default';
        }
        if (\array_key_exists('megaColumns', $meta)) {
            $out['megaColumns'] = self::clampColumns($meta['megaColumns']);
        }
        if (\array_key_exists('megaColumn', $meta)) {
            $out['megaColumn'] = self::clampColumns($meta['megaColumn']);
        }
        if (\array_key_exists('mobileOrder', $meta)) {
            $out['mobileOrder'] = is_numeric($meta['mobileOrder']) ? (int) $meta['mobileOrder'] : null;
        }
        if (\array_key_exists('visibility', $meta)) {
            $out['visibility'] = self::normalizeVisibility($meta['visibility']);
        }

        return $out;
    }

    /**
     * Normalize visibility rules to a list of `{type, value?}`. Invalid rows are dropped.
     *
     * @return list<array{type: string, value?: mixed}>
     */
    public static function normalizeVisibility(mixed $rules): array
    {
        if (!\is_array($rules)) {
            return [];
        }

        $out = [];
        foreach ($rules as $rule) {
            if (!\is_array($rule)) {
                continue;
            }
            $type = isset($rule['type']) && \is_string($rule['type']) ? trim($rule['type']) : '';
            if (!\in_array($type, self::VISIBILITY_TYPES, true)) {
                continue;
            }

            $value = $rule['value'] ?? null;
            if ($type === 'dateRange') {
                $value = self::normalizeDateRange($value);
                if ($value === null) {
                    continue;
                }
            } elseif (\in_array($type, ['always', 'loggedIn', 'loggedOut'], true)) {
                $value = null;
            } else {
                $value = \is_scalar($value) ? trim((string) $value) : '';
                if ($value === '') {
                    continue;
                }
            }

            $row = ['type' => $type];
            if ($value !== null) {
                $row['value'] = $value;
            }
            $out[] = $row;
        }

        return $out;
    }

    /**
     * Accepts `{from, to}`, or a string like `2026-01-01,2026-02-01` / `2026-01-01..2026-02-01`.
     *
     * @return null|array{from: ?string, to: ?string}
     */
    public static function normalizeDateRange(mixed $value): ?array
    {
        $from = $to = null;
        if (\is_array($value)) {
            $from = $value['from'] ?? null;
            $to = $value['to'] ?? null;
        } elseif (\is_string($value) && $value !== '') {
            $parts = preg_split('/\s*(?:,|\.\.|\/|\|)\s*/', trim($value), 2) ?: [];
            $from = $parts[0] ?? null;
            $to = $parts[1] ?? null;
        }

        $from = self::cleanDate($from);
        $to = self::cleanDate($to);
        if ($from === null && $to === null) {
            return null;
        }

        return ['from' => $from, 'to' => $to];
    }

    /**
     * Flat text version of a rule value for CP inputs.
     */
    public static function visibilityValueToText(array $rule): string
    {
        $value = $rule['value'] ?? '';
        if (\is_array($value)) {
            return trim((string) ($value['from'] ?? '').','.(string) ($value['to'] ?? ''), ',');
        }

        return \is_scalar($value) ? (string) $value : '';
    }

    /**
     * Merge `$input` into `$existing` (key-by-key; keys absent from input are kept).
     *
     * @param array<string, mixed> $existing
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>
     */
    public static function merge(array $existing, array $input): array
    {
        $current = self::normalize($existing);
        $known = array_intersect_key($input, self::defaults());

        // Normalize the incoming partial against the current values so absent keys survive.
        $combined = array_merge($current, $known);

        return self::normalize($combined);
    }

    /**
     * Drop values equal to their default so stored JSON stays small.
     *
     * @param array<string, mixed> $meta normalized meta
     *
     * @return array<string, mixed>
     */
    public static function compact(array $meta): array
    {
        $defaults = self::defaults();
        $out = [];
        foreach ($meta as $key => $value) {
            if (!\array_key_exists($key, $defaults) || $value === $defaults[$key] || $value === [] || $value === null) {
                continue;
            }
            $out[$key] = $value;
        }

        return $out;
    }

    /**
     * Read the merged meta from anything exposing `getDataArray()`.
     *
     * @return array<string, mixed>
     */
    public static function get(object $node): array
    {
        if (method_exists($node, 'getDataArray')) {
            return self::fromData($node->getDataArray());
        }

        return self::defaults();
    }

    /**
     * Merge `$input` into the node's `data['meta']` and write back, preserving every other data key.
     *
     * @param array<string, mixed> $input
     */
    public static function set(object $node, array $input): void
    {
        $data = method_exists($node, 'getDataArray') ? $node->getDataArray() : [];
        $existing = isset($data[self::KEY]) && \is_array($data[self::KEY]) ? $data[self::KEY] : [];
        $meta = self::compact(self::merge($existing, $input));

        if ($meta === []) {
            unset($data[self::KEY]);
        } else {
            $data[self::KEY] = $meta;
        }

        if (method_exists($node, 'setDataArray')) {
            $node->setDataArray($data);
        }
    }

    /**
     * Pull `meta[...]` out of POST-style input. Visibility rows may arrive indexed by arbitrary keys.
     *
     * @param array<string, mixed> $post
     *
     * @return array<string, mixed>
     */
    public static function fromRequest(array $post): array
    {
        $raw = $post[self::KEY] ?? [];
        if (!\is_array($raw)) {
            return [];
        }
        if (isset($raw['visibility']) && \is_array($raw['visibility'])) {
            $raw['visibility'] = array_values($raw['visibility']);
        } elseif (!empty($raw['visibilitySubmitted'])) {
            // The rules editor was on the page but every row was removed.
            $raw['visibility'] = [];
        }
        unset($raw['visibilitySubmitted']);

        return $raw;
    }

    /**
     * @return array<string, mixed>
     */
    private static function decode(mixed $data): array
    {
        if (\is_array($data)) {
            return $data;
        }
        if (!\is_string($data) || $data === '') {
            return [];
        }
        $decoded = Json::decodeIfJson($data);

        return \is_array($decoded) ? $decoded : [];
    }

    private static function toBool(mixed $value): bool
    {
        if (\is_string($value)) {
            return !\in_array(strtolower(trim($value)), ['', '0', 'false', 'off', 'no'], true);
        }

        return (bool) $value;
    }

    private static function toId(mixed $value): ?int
    {
        if (\is_array($value)) {
            $value = reset($value);
        }

        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }

    private static function clampColumns(mixed $value): int
    {
        return is_numeric($value) ? max(1, min(6, (int) $value)) : 1;
    }

    private static function cleanDate(mixed $value): ?string
    {
        if (!\is_string($value)) {
            return null;
        }
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date && $date->format('Y-m-d') === $value ? $value : null;
    }
}
