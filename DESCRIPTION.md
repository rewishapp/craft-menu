# Menu

Nested **menus**, **navigation**, and **navbar** builder for Craft CMS — site nav that is not forced to mirror your section tree.

**Menu** (package `rewi.sh/menu`) lets authors build **main navigation**, **header nav**, **footer menus**, **utility nav**, **sidebar menus**, and multi-level **dropdown** / **mega-menu** style trees. Link to custom URLs or Craft elements (entries, categories, assets, sites, and Commerce products when installed). Use **passive** labels, **dynamic** sources (projected children), and per-menu sites. Twig and GraphQL expose the tree for the front end — including **active** / **current** states and **breadcrumbs**.

Built for site navigation and navbar work — main, header, footer, utility, and sidebar menus — with nested dropdowns, breadcrumbs, and a CP builder. Includes a migrator for Verbb Navigation.

## Control panel

- **Menus** — create and configure each menu / navbar handle, max levels, sites, field layout
- **Nodes** — drag-and-drop structure; custom URL, entry, category, asset, product, passive, site, dynamic
- **Settings** — defaults such as max levels; migrate from Verbb Navigation
- **Developer helpers** — copy Twig (`tree`, `activeTree`, `breadcrumbs`) and GraphQL queries from the builder

## Front end

- Twig: `craft.menu.tree()`, `activeTree()`, `breadcrumbs()`, `isActive` / `isCurrent`
- GraphQL: `menu`, `menus`, `menuNodes`, `menuBreadcrumbs` (with `children`, `active`, `current`, `projected`)
- Import / export menu JSON; console + CP migrate from Verbb

## Requirements

- Craft CMS **5.0+** or **6.0+**
- PHP **8.2+**

Package: `rewi.sh/menu`  
Developer: [rewi.sh](https://rewi.sh)
