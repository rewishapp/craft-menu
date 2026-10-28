# Control panel

## Menus

Create menus from **Menu → Menus**. Each menu has:

- Handle (Twig / GraphQL key)
- Enabled toggle (disabled menus render nothing on the front end)
- CSS class and extra HTML attributes for the wrapper `<nav>`
- Max nesting levels
- Enabled sites
- Optional custom field layout for nodes

Use **Duplicate** on the Menus index to copy a menu with its settings, field layout, and node tree. The index also shows enabled status and a link-health summary.

## Node builder

Open a menu to edit nodes. Drag the handle to reorder:

- Drop on the **top / bottom edge** → place before / after
- Drop in the **middle** → nest under that node

**Session moves** tracks structure changes; **Discard** restores the snapshot from the start of the session.

### Node types

| Type | Behavior |
| --- | --- |
| Custom URL | Manual URL |
| Entry / Category / Asset / Product | Linked element (+ optional follow title) |
| Passive | Label only, no URL |
| Site | Links to a Craft site base URL |
| Dynamic | Projects children from a section, category group, volume, or product type (ordered by title or date) |
| Anchor | In-page `#fragment` link |
| Separator | Visual divider (no label link, no URL) |

### Node list

- **Filter** rows by title or URL as you type; ancestors of matches stay visible.
- Tick rows (or **Select all**) to **Enable**, **Disable**, or **Delete** in bulk.
- Indent rails show nesting; the dot shows link health (green ok, amber warning, red broken — hover for the reason).
- The globe opens the link; **Duplicate** copies a node with its descendants.
- Focus a drag handle and press ↑ / ↓ to move a node without the mouse.
- **Preview** shows how the menu resolves for a site, with visibility rules applied (or all nodes).

### Node editor sections

| Section | Fields |
| --- | --- |
| Link | Target by type, missing-element fallback (keep as label / hide / fallback URL), `rel`, clickable |
| Appearance | Icon class, badge text + style, description, featured, HTML ID, CSS classes |
| Accessibility | ARIA label, title attribute |
| Visibility | Rules; the node shows only when **all** rules pass, and hiding a node hides its children |
| Mega menu | Enable, columns (1–6), column within the parent's panel |
| Mobile | Hide on mobile, order, collapsible |
| Advanced | URL suffix, custom attributes (JSON) |

Visibility rule values: user group (ID or handle), site (ID), date range (`YYYY-MM-DD,YYYY-MM-DD`, either end optional), environment (`CRAFT_ENVIRONMENT`, comma-separated).

### Menu field

Add the **Menu** field type to any field layout to pick a menu per element. The value is the menu, so `craft.menu.render(entry.myMenuField)` works.

### Import / export

- **Export JSON** from the menu toolbar
- **Import JSON** at the bottom of the builder (optional replace by handle)

## Settings

**Menu → Settings**:

- Default max levels for new menus
- **API token** — enables the read-only JSON API (`/menu-api/menus`, `/menu-api/menus/<handle>?token=…`). Leave empty to disable it. The token can also be sent as `Authorization: Bearer …`.
- **Migrate from Verbb Navigation** (same page)

The CP also surfaces copy-ready **Twig** and **GraphQL** helpers under **Developer helpers** on each node builder.
