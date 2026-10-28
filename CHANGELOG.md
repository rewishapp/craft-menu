# Release Notes for Menu

## 1.1.0 - 2026-10-28

### Added
- **Anchor** (`#fragment`) and **Separator** node types.
- Per-node meta (stored under `data.meta`, no new columns): HTML ID, ARIA label, title attribute, `rel`, clickable toggle, badge text/style, icon class, description, featured, mega-menu settings (columns / column), mobile options (hide, order, collapsible).
- **Visibility rules** per node (always, logged in/out, user group, site, date range, environment). All rules must pass; a hidden node hides its whole subtree. Applied by `craft.menu.tree()`, `activeTree()`, GraphQL, the JSON API, and the CP preview.
- **Missing-element fallback** for linked nodes: hide, keep as plain label, or use a fallback URL.
- **Link health** checks (ok / warning / broken) shown in the node list and on the Menus index.
- **Duplicate** a menu (settings, field layout, and node tree) or a single node with its descendants.
- **Bulk actions** in the node list: enable, disable, delete selected.
- Node list polish: indent rails, live filter, link-health dot, visit link, denser rows, keyboard reordering (↑ / ↓ on a focused handle).
- Node editor grouped into Link, Appearance, Accessibility, Visibility, Mega menu, Mobile, and Advanced sections.
- Menu **enabled** toggle (disabled menus render nothing), plus menu **CSS class** and **HTML attributes**.
- Dynamic sources: **order by** title (A–Z / Z–A) or date (newest / oldest).
- `menu/_macros/nav` Twig macro `renderNav(tree, options)` and `craft.menu.render(handle, options)`: `<nav>` landmark, nested lists, `aria-current`, `<details>` mega panels.
- **Menu field type** (dropdown of menus; value is the menu).
- CP **Preview** page for a menu per site (visible vs. all nodes).
- Optional read-only **JSON API** (`/menu-api/menus`, `/menu-api/menus/<handle>`), enabled only when an API token is set in Settings.
- GraphQL: node `htmlId`, `ariaLabel`, `titleAttr`, `badgeText`, `badgeStyle`, `iconClass`, `description`, `rel`, `featured`, `megaEnabled`, `megaColumns`, `clickable`, `separator`, `activeAncestor`; menu `cssClass`, `htmlAttributes`, `enabled`. `menuNodes` now honors visibility rules.
- Services: `visibility`, `linkHealth`, `duplicators`, `menuCache`.
- Schema **0.3.0** (`menu_menus.cssClass`, `menu_menus.htmlAttributes`).
- Import / export carry the new menu and node fields.

## 1.0.0 - 2026-10-27

### Added
- Polished CP builder UI: type chips, badges, sticky editor sections, empty states.
- Reliable drag-and-drop (before / after / nest) with in-place refresh and session discard.
- Collapsible **Developer helpers** on the node builder: Twig + GraphQL copy panels.
- Migrate from Verbb lives under **Settings** (nav no longer has a separate Migrate item).
- Menus index shows node counts.
- Plugin docs under `docs/` (Twig, GraphQL, CP, migrate).
- CP translations: `en`, `de`, `fr`, `it`, `nl`.
- CP site switcher for node editing; build sessions with discard/restore snapshot.
- Commerce Product node type + product-type dynamic source (when Commerce is installed).
- Schema **0.2.1** (`menu_build_sessions`).
- Dynamic asset-volume source.
- Dynamic nodes (entry section / category group) with projected children in trees.
- Per-menu node field layout designer; menu site enablement.
- Per-menu manage permissions (`menu:manageMenu:{uid}`).
- Schema **0.2.0** (`menu_menus_sites`, `nodeFieldLayoutId`, node `data`).
- Passive (label-only) and Site node types.
- Active matching: Twig `craft.menu.activeTree()`, `isActive` / `isCurrent`; GraphQL `active` / `current` / nested `children`.
- Nested node hierarchy (parent select, structure placement).
- Element pickers for entry / category / asset nodes; custom attributes JSON.
- Import / export menu JSON from the node builder.
- GraphQL: `menu`, `menus`, `menuNodes`, `menuBreadcrumbs`.
- Twig `craft.menu.breadcrumbs('handle')`.
- Migrate from Verbb: full import (CP + `menu/migrate/navigation`), dry-run or live; skips existing handles and unsupported types.
- Initial scaffold: menus and nodes as elements, CP builder, Twig `craft.menu`, settings, hamburger plugin icons.

### Fixed
- Export JSON no longer fatals on dynamic projected children (`ProjectedNode`).
- Drag-drop no longer disables pointer events while dragging (drops work again).
- Load dynamic node `data` from the DB (NodeQuery select).
- Active trees with projected children no longer type-error.
- GraphQL `children` includes projected dynamic nodes; `projected` flag.
- Node custom fields from the menu field layout render and save.
