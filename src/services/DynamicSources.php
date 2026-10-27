<?php

namespace rewish\craft\menu\services;

use craft\base\Component;
use craft\elements\Asset;
use craft\elements\Category;
use craft\elements\db\ElementQueryInterface;
use craft\elements\Entry;
use rewish\craft\menu\elements\Node;
use rewish\craft\menu\models\ProjectedNode;

class DynamicSources extends Component
{
    public const SOURCE_ENTRY_SECTION = 'entrySection';

    public const SOURCE_CATEGORY_GROUP = 'categoryGroup';

    public const SOURCE_ASSET_VOLUME = 'assetVolume';

    public const SOURCE_PRODUCT_TYPE = 'productType';

    /**
     * @return array<string, string>
     */
    public function sourceOptions(): array
    {
        $options = [
            self::SOURCE_ENTRY_SECTION => \Craft::t('menu', 'Entry section'),
            self::SOURCE_CATEGORY_GROUP => \Craft::t('menu', 'Category group'),
            self::SOURCE_ASSET_VOLUME => \Craft::t('menu', 'Asset volume'),
        ];
        if (Node::commerceInstalled()) {
            $options[self::SOURCE_PRODUCT_TYPE] = \Craft::t('menu', 'Product type');
        }

        return $options;
    }

    /**
     * @return list<ProjectedNode>
     */
    public function project(Node $node, ?int $siteId = null): array
    {
        if ($node->type !== Node::TYPE_DYNAMIC) {
            return [];
        }

        $data = $node->getDataArray();
        $source = (string) ($data['source'] ?? '');
        $siteId ??= $node->siteId ?: \Craft::$app->getSites()->getCurrentSite()->id;
        $limit = isset($data['limit']) && is_numeric($data['limit']) ? max(1, (int) $data['limit']) : 50;

        return match ($source) {
            self::SOURCE_ENTRY_SECTION => $this->projectEntries($data, $siteId, $limit),
            self::SOURCE_CATEGORY_GROUP => $this->projectCategories($data, $siteId, $limit),
            self::SOURCE_ASSET_VOLUME => $this->projectAssets($data, $siteId, $limit),
            self::SOURCE_PRODUCT_TYPE => $this->projectProducts($data, $siteId, $limit),
            default => [],
        };
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return list<ProjectedNode>
     */
    private function projectEntries(array $data, int $siteId, int $limit): array
    {
        $handle = trim((string) ($data['section'] ?? ''));
        if ($handle === '') {
            return [];
        }

        $query = Entry::find()
            ->section($handle)
            ->siteId($siteId)
            ->status(null)
            ->limit($limit)
            ->orderBy(['title' => \SORT_ASC])
        ;

        $out = [];
        foreach ($query->all() as $entry) {
            $out[] = new ProjectedNode([
                'title' => (string) $entry->title,
                'url' => $entry->getUrl(),
                'elementId' => (int) $entry->id,
                'type' => 'entry',
            ]);
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return list<ProjectedNode>
     */
    private function projectCategories(array $data, int $siteId, int $limit): array
    {
        $handle = trim((string) ($data['group'] ?? ''));
        if ($handle === '') {
            return [];
        }

        $query = Category::find()
            ->group($handle)
            ->siteId($siteId)
            ->status(null)
            ->limit($limit)
            ->orderBy(['title' => \SORT_ASC])
        ;

        $out = [];
        foreach ($query->all() as $category) {
            $out[] = new ProjectedNode([
                'title' => (string) $category->title,
                'url' => $category->getUrl(),
                'elementId' => (int) $category->id,
                'type' => 'category',
            ]);
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return list<ProjectedNode>
     */
    private function projectAssets(array $data, int $siteId, int $limit): array
    {
        $handle = trim((string) ($data['volume'] ?? ''));
        if ($handle === '') {
            return [];
        }

        $query = Asset::find()
            ->volume($handle)
            ->siteId($siteId)
            ->status(null)
            ->limit($limit)
            ->orderBy(['title' => \SORT_ASC])
        ;

        $out = [];
        foreach ($query->all() as $asset) {
            $out[] = new ProjectedNode([
                'title' => (string) $asset->title,
                'url' => $asset->getUrl(),
                'elementId' => (int) $asset->id,
                'type' => 'asset',
            ]);
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return list<ProjectedNode>
     */
    private function projectProducts(array $data, int $siteId, int $limit): array
    {
        $class = Node::commerceProductClass();
        if ($class === null) {
            return [];
        }
        $handle = trim((string) ($data['productType'] ?? ''));
        if ($handle === '') {
            return [];
        }

        /** @var ElementQueryInterface $query */
        $query = $class::find()
            ->type($handle)
            ->siteId($siteId)
            ->status(null)
            ->limit($limit)
            ->orderBy(['title' => \SORT_ASC])
        ;

        $out = [];
        foreach ($query->all() as $product) {
            $out[] = new ProjectedNode([
                'title' => (string) $product->title,
                'url' => $product->getUrl(),
                'elementId' => (int) $product->id,
                'type' => 'product',
            ]);
        }

        return $out;
    }
}
