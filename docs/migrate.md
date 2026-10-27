# Migrate from Verbb Navigation

Imports `navigation_menus` / `navigation_nodes` into Menu elements.

## CP

**Menu → Settings → Migrate from Verbb Navigation**

- Dry-run to preview
- Run migrate to import

Existing Menu handles are skipped. Unsupported types (e.g. Product, Dynamic in older Navigation setups) are skipped.

## Console

```bash
php craft menu/migrate/navigation --dry-run=1
php craft menu/migrate/navigation --dry-run=0
```

DDEV:

```bash
ddev craft menu/migrate/navigation --dry-run=1
```
