# Twig

All helpers hang off `craft.menu`.

## Tree

```twig
{% set tree = craft.menu.tree('main') %}
```

Each item is `{ node, children }`. Dynamic nodes expand projected children into `children` (not editable CP nodes).

Use `node.nodeUrl`, `node.title`, `node.newWindow`, `node.classes`, etc.

Trees are **resolved for display**: disabled menus return nothing, and disabled, unavailable, or rule-hidden nodes are removed together with their children.

Each node also exposes `node.meta` (htmlId, ariaLabel, titleAttr, rel, clickable, badgeText, badgeStyle, iconClass, description, featured, megaEnabled, megaColumns, megaColumn, mobileHidden, mobileOrder, mobileCollapsible) and `node.type` (`separator` / `anchor` included).

## Active tree

```twig
{% set tree = craft.menu.activeTree('main') %}
{# optional URI override: craft.menu.activeTree('main', 'about/team') #}
```

Same shape as `tree()`, plus:

- `active` — this node or a descendant matches the URI
- `current` — this node is the best match
- `hasActiveChild` — a child is active

```twig
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
      {# recurse item.children the same way #}
    </li>
  {% endfor %}
</ul>
```

## Render helper

```twig
{{ craft.menu.render('main', { ariaLabel: 'Main', class: 'site-nav' }) }}
```

Renders a `<nav>` landmark with nested lists, `aria-current="page"` on the current item, separators, badges, and `<details>` mega panels for nodes with mega enabled. Pass a `Menu` (for example a Menu field value) instead of a handle if you prefer.

Options: `ariaLabel`, `class`, `listClass`, `itemClass`, `linkClass`, `activeClass`, `currentClass`, `maxDepth`, `uri`, `siteId`.

Or use the macro directly:

```twig
{% import 'menu/_macros/nav' as nav %}
{{ nav.renderNav(craft.menu.activeTree('main'), { ariaLabel: 'Main' }) }}
```

## Breadcrumbs

```twig
{% set crumbs = craft.menu.breadcrumbs('main') %}
{# or craft.menu.breadcrumbs('main', craft.app.request.pathInfo) #}
```

Returns a flat list of `Node` elements from root → current match.

## Helpers

```twig
{% if craft.menu.isActive(node) %}…{% endif %}
{% if craft.menu.isCurrent(node) %}…{% endif %}
```

## Queries

```twig
{% set menu = craft.menu.menus('main') %}
{% set all = craft.menu.menus().all() %}
{% set nodes = craft.menu.nodes().menuId(menu.id).level(1).all() %}
```
