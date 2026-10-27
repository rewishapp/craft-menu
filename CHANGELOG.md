# Release Notes for Menu

## 1.0.0

### Fixed
- Load dynamic node `data` from the DB (NodeQuery select).
- Active trees with projected children no longer type-error.
- GraphQL `children` includes projected dynamic nodes; `projected` flag.
- Node custom fields from the menu field layout render and save.

### Added
- CP translations matching Freeform locales: `en`, `de`, `fr`, `it`, `nl`.
- CP site switcher for node editing; build sessions with discard/restore snapshot.
- Drag-drop builder refreshes in-place (no full page reload).
- Commerce Product node type + product-type dynamic source (when Commerce is installed).
- Schema **0.2.1** (`menu_build_sessions`).
- Dynamic asset-volume source.
- Dynamic nodes (entry section / category group) with projected children in trees.
- Per-menu node field layout designer; menu site enablement.
- Per-menu manage permissions (`menu:manageMenu:{uid}`).
- Schema **0.2.0** (`menu_menus_sites`, `nodeFieldLayoutId`, node `data`).
- Drag-and-drop structure builder (before / after / into) with AJAX move.
- Passive (label-only) and Site node types.
- Active matching: Twig `craft.menu.activeTree()`, `isActive` / `isCurrent`; GraphQL `active` / `current` / nested `children`.
- Nested node hierarchy (parent select, structure placement).
- Element pickers for entry / category / asset nodes; custom attributes JSON.
- Import / export menu JSON from the node builder.
- GraphQL: `menu`, `menus`, `menuNodes`, `menuBreadcrumbs`.
- Twig `craft.menu.breadcrumbs('handle')`.
- Migrate from Verbb: full import (CP + `menu/migrate/navigation`), dry-run or live; skips existing handles and unsupported types.
- Initial scaffold: menus and nodes as elements, CP builder, Twig `craft.menu`, settings, hamburger plugin icons.
