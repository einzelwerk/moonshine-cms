# Changelog

All notable changes to `ewk/moonshine-cms` are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and the project adheres to [Semantic Versioning](https://semver.org/). Until
`1.0.0` a minor version may contain breaking changes.

## 0.1.0 — 2026-10-16

First public release: a block CMS for Laravel 13 with an admin for MoonShine 4,
built on `ewk/moonshine-media` for files, pictures and content languages.

### Blocks (`Ewk\Cms\Blocks`)

- Block types declared by one schema each, from which the admin form, the
  validation rules, the stored shape, the payload and the OpenAPI description
  are derived; a neutral field dictionary with translatable values.
- One row per block with nesting in named slots of containers, checked on every
  write; shared blocks inserted on many pages and refused deletion while in
  use.
- Drafts and publication: the live rows are the draft, `PublishOwner` writes
  the snapshot the public answer reads and refuses with the list of what stands
  in its way.
- Typed links stored by kind and identifiers and resolved on render, with a
  registry of resolvers and of actions.
- Dynamic blocks with declared parameters, three pagination modes, filters and
  ready addresses; one query encoder for every address of the CMS.
- A swagger-php processor that generates the contract of the registered block
  types.
- Usages of media files registered for every block, with the owner named in the
  list of «where this file is used».

### Pages (`Ewk\Cms\Pages`)

- The tree of pages with derived paths, strict slugs and reserved segments.
- Page types declared in code: free pages, singletons and templates rendered
  for the entities of a model at the address of a path template.
- `GET /resolve` and `GET /blocks/{uuid}`, with contributors that extend the
  answer, and link resolvers for pages, catalogues with filters, entities and
  routes of the frontend.

### Layouts (`Ewk\Cms\Layouts`)

- Layouts declared by the application, headers and footers with declared
  fields, menus put into a closed dictionary of slots, places for menus on
  records of the application.
- `GET /chrome` and `GET /menus/{code}`, the same for every visitor; the layout
  of a page in the answer of the resolver.

### SEO (`Ewk\Cms\Seo`)

- Hand-filled metadata per record with fallbacks computed when the document is
  handed out, in the shape of `Metadata` of Next: canonical addresses,
  language versions, Open Graph and Twitter cards, robots.
- A registry of JSON-LD builders and the sitemap feed (`GET /sitemap`,
  `GET /sitemap/{id}`) derived from the page types.
- The picture of a card is an absolute address even when the disk of the media
  library answers with a bare path: such a path is completed with `app.url`,
  and an absolute address passes through unchanged.

### Admin (`Ewk\MoonShineCms`)

- The block editor with a panel of publication, one language switch, cards
  sorted by drag and drop across slots, and an insertion library narrowed per
  point of insertion.
- Sections of pages (a tree with templates as tiles), shared blocks, layouts,
  headers, footers and menus (a tree of items), with entity forms in one
  language scope.
- The link picker with its lookups inside the panel, and the metadata tab with
  a snippet preview and counters.
- Usage links and captions registered in the `UsageLinkRegistry` of the media
  admin; the «Медиа» section placed in `MoonShineCms::menuItems()`.
- Russian and English translations.
