# Twig

All helpers hang off `craft.menu`.

## Tree

```twig
{% set tree = craft.menu.tree('main') %}
```

Each item is `{ node, children }`. Dynamic nodes expand projected children into `children` (not editable CP nodes).

Use `node.nodeUrl`, `node.title`, `node.newWindow`, `node.classes`, etc.

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
