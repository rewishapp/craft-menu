# Menu

Nested site menus for Craft CMS. Built by [rewi.sh](https://rewi.sh).

Package `rewi.sh/menu`. Handle **`menu`**. Craft 5.0+ / 6.0+. PHP 8.2+.

Control panel translations: **en**, **de**, **fr**, **it**, **nl**.

## Install

```bash
composer require "rewi.sh/menu:^1.0.0" -w && php craft plugin/install menu
```

DDEV:

```bash
ddev composer require "rewi.sh/menu:^1.0.0" -w && ddev craft plugin/install menu
```

## What you get

- CP section **Menu** — create menus and drag-drop nested nodes
- Node types: custom URL, entry, category, asset, product (Commerce), passive, site, dynamic (section / category group / asset volume / product type → projected children)
- Per-menu sites, node field layouts, and manage permissions
- CSS classes, URL suffix, custom attributes, new window
- Twig: `craft.menu.tree()`, `activeTree()`, `breadcrumbs()`, `isActive` / `isCurrent`
- GraphQL: `menu`, `menus`, `menuNodes` (with `children`, `active`, `current`, `projected`), `menuBreadcrumbs`
- Export / import menu JSON from the node builder
- Migrate from Verbb under **Settings** (and via console)
- Docs in [`docs/`](docs/README.md)

## Twig

```twig
{% set tree = craft.menu.activeTree('main') %}
<ul>
  {% for item in tree %}
    <li class="{{ item.active ? 'is-active' }}{{ item.current ? ' is-current' }}">
      {% if item.node.nodeUrl %}
        <a href="{{ item.node.nodeUrl }}"{% if item.node.newWindow %} target="_blank" rel="noopener"{% endif %}>
          {{ item.node.title }}
        </a>
      {% else %}
        <span>{{ item.node.title }}</span>
      {% endif %}
      {# item.children is the same shape #}
    </li>
  {% endfor %}
</ul>

{% set crumbs = craft.menu.breadcrumbs('main') %}
```

## GraphQL

```graphql
{
  menu(handle: "main") { id title handle }
  menuNodes(handle: "main", level: 1) {
    id title url active(uri: "about/team") current(uri: "about/team")
    children { id title url }
  }
  menuBreadcrumbs(handle: "main", uri: "about/team") { id title url }
}
```

## Migrate from Verbb

Reads `navigation_menus` / `navigation_nodes` and creates Menu elements. Existing handles are skipped. Entry / category / asset / custom (and passive/group/site → custom) are imported; Product and Dynamic nodes are skipped.

```bash
php craft menu/migrate/navigation --dry-run=1
php craft menu/migrate/navigation --dry-run=0
```

Or use **Menu → Settings → Migrate from Verbb Navigation** in the control panel.

## License

MIT.
