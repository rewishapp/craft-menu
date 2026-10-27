# Control panel

## Menus

Create menus from **Menu → Menus**. Each menu has:

- Handle (Twig / GraphQL key)
- Max nesting levels
- Enabled sites
- Optional custom field layout for nodes

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
| Dynamic | Projects children from a section, category group, volume, or product type |

### Import / export

- **Export JSON** from the menu toolbar
- **Import JSON** at the bottom of the builder (optional replace by handle)

## Settings

**Menu → Settings**:

- Default max levels for new menus
- **Migrate from Verbb Navigation** (same page)

The CP also surfaces copy-ready **Twig** and **GraphQL** helpers under **Developer helpers** on each node builder.
