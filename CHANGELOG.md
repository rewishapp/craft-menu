# Release Notes for Menu

## 1.0.0

### Added
- Polished CP builder UI: type chips, badges, sticky editor sections, empty states.
- Reliable drag-and-drop (before / after / nest) with in-place refresh and session discard.
- Collapsible **Developer helpers** on the node builder: Twig + GraphQL copy panels.
- Migrate from Verbb lives under **Settings** (nav no longer has a separate Migrate item).
- Menus index shows node counts.
- Plugin docs under `docs/` (Twig, GraphQL, CP, migrate).
- CP translations matching Freeform locales: `en`, `de`, `fr`, `it`, `nl`.
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
