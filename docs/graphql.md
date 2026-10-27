# GraphQL

Enable the Menu fields on your schema, then query:

```graphql
{
  menu(handle: "main") {
    id
    title
    handle
  }

  menus {
    id
    title
    handle
  }

  menuNodes(handle: "main", level: 1) {
    id
    title
    url
    type
    projected
    active(uri: "about/team")
    current(uri: "about/team")
    children {
      id
      title
      url
      projected
    }
  }

  menuBreadcrumbs(handle: "main", uri: "about/team") {
    id
    title
    url
  }
}
```

## Notes

- `menuNodes` returns structure order. Pass `level: 1` for roots; use `children` for nesting.
- Dynamic sources inject **projected** children into `children` (`projected: true`). Those are not stored Node elements.
- `active` / `current` take an optional `uri` argument (defaults to the current request when available).
