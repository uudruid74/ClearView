![ClearView Logo](assets/Clearview-Icon-CIR-256.png)

# ClearView Architecture Overview

Authoritative current-state entry point for the ClearView framework.
Last updated: 2026-06-16. This document describes what is **implemented and working now**.
For proposed changes not yet built, see §8 (Architecture Status).

> **Source of truth:** the [ClearView wiki](/home/ekl/vault/wiki/entities/clearview/_index.md) is the master spec.
> This file is a stable repo-bundled summary that tracks the wiki.

---

## 1. What Is ClearView?

ClearView is a server-side PHP framework for building dynamic web applications
without a build step. It layers on top of ProcessWire CMS and uses HTMX for
AJAX, Surreal for encapsulated CSS/JS, and PicoCSS for styling. All application
logic lives on the server — the browser is a dumb display device.

Key design principles:
- **No build step.** All output is human-readable HTML for easy debugging.
- **URLs are the message bus.** Every interaction is a GET or POST to a
  `/pane/inlay/method/` URL. HTMX attributes are generated server-side.
- **ProcessWire is the backend.** Auth, routing, database, and admin UI are all
  ProcessWire. ClearView wraps ProcessWire APIs as "Crystals".
- **Shards carry state.** Temporary per-request state lives in the Mosaic, a
  server-side key-value store deflated to hidden inputs on the client.

---

## 2. Architecture Overview

```
Nginx → PHP-FPM → ProcessWire → ClearView
                       │
                  _init.php / _main.php
                       │
            Pane boot (Crystal::loadAll)
                       │
            Pane::loadInlay(panename, inlayname)
                       │
            Pane::handleCommand($method)
                       │
            Facet rendering → HTML output
```

### Class hierarchy

```
ClearView\Shard
  └── ClearView\Element
        ├── ClearView\Main
        ├── ClearView\Inlay
        └── (glyphs)

ClearView\Pane (standalone, implements ArrayAccess)
  └── ClearView\Inlay
  └── ClearView\Main

ClearView\Crystal extends Page
  └── ClearView
  └── Config
  └── (other crystals)
```

Core concepts:

| Concept  | Role |
|----------|------|
| **Pane** | A self-contained UI namespace with its own URL prefix and Mosaic. Standalone base class (implements ArrayAccess). |
| **Inlay** | A PHP class loaded into a Pane that handles a portion of the UI (e.g., a form tab). |
| **Shard** | The basic data unit — like a mini-cookie stored in the page, not the browser. |
| **Mosaic** | Request-scoped key-value store of Shards, indexed by `inlay-id`, deflated to hidden inputs. Owned by the current Pane. |
| **Facet** | Rendering engine with tag stack, template expansion (`{{}}`), and OOB output. |
| **Crystal** | Singleton Shard wrapping a ProcessWire API (Page, User, Session, etc.). |
| **Glyph** | An Element subclass that renders a specific HTML tag with custom behavior. |
| **Reference** | Proxy glyph — named children live once in Mosaic; tree slots hold References. |

### Module layout

| Path | Purpose |
|------|---------|
| `modules/<module>/glyphs/<name>.php` | Element subclasses |
| `modules/<module>/views/<name>.php` | Fragment/layout views |
| `modules/<module>/panes/<pane>/<inlay>.php` | Inlay subclasses |
| `modules/site/` | Site-specific overrides, tried before vendor |
| `modules/vendor/` | Pristine ClearView code, last fallback |

Activated modules (including site/vendor) are listed in `Config::modules-list` and tried in order.

---

## 3. Request Lifecycle

### 3.1 Bootstrap

1. ProcessWire receives the HTTP request and matches it to a template file.
2. `_init.php` and `_main.php` bootstrap ProcessWire in delayed-output mode.
3. `Crystal::loadAll()` initializes all crystals. The Pane bootstrap is triggered by the module stack and URL routing.

### 3.2 URL Parsing

- **Default template** (`$template == 'Default'`): panename = `'Default'`, inlayname = `'Pane'`.
  This is the main page — it renders ProcessWire page content via the `<main>` glyph.
- **All other templates**: The page URL is split on `/` to extract `panename`, `inlayname`, and `command`.
  Example: `/form/login/` → pane=`form`, inlay=`login`, command=``.

### 3.3 Crystal Registration

`Crystal::plugAllCrystals()` auto-instantiates all concrete Crystal subclasses in `crystals/`
and registers them under the `'ClearView'` inlay. This makes ProcessWire APIs (Page, User, Session, etc.)
available as Crystal singletons via `{{CrystalName::field}}` expressions.

### 3.4 Command Resolution

- If this is an **hx-boosted** request (from `<main>` navigation): command is forced to `'html'`.
- If the URL had no command segment: `ClearView::defaultMethod()` maps
  `GET→open`, `POST→html`, `PUT→put`, `DELETE→delete`.
- Otherwise the URL command is used directly.

### 3.5 Pane Loading

`Pane::loadInlay(panename, inlayname)` (delegated to Element for glyph/inlay resolution) searches for the class file:

1. Module stack: `modules/<module>/panes/<lowerpanename>s/<Inlayname>.php`
2. Module stack: `modules/<module>/panes/<Panename>.php`
3. Base: `panes/<lowerpanename>s/<Inlayname>.php`
4. Base: `panes/<Panename>.php`

The Pane Crystal is wired to the correct ProcessWire page so that
`Pane::name`, `Pane::title`, etc. resolve from the page's fields.

### 3.6 Dispatch

`Pane::handleCommand($command)`:
1. Loads Mosaic data from `Input::all` (slurps client-side state).
2. Validates the **PaneKey** CSRF token (`Pane::Key` must match `Session::PaneKey`).
3. Refuses private/underscored methods.
4. Executes the method via Facet forwarding.
5. Falls back to **ProcessWire page field** lookup if no method exists.
6. Dumps buffered OOB data and scripts.

### 3.7 Lifecycle Events

One polymorphic `inlaychange` event is fired whenever the active inlay or main
layout changes. Receivers filter by the sending pane name in the JSON payload
(`{pane: 'loginform', ...}`).

| Transition | Trigger |
|------------|---------|
| `paneopen` | Pane::open() completes |
| `closepane` | Pane::close() called |
| `inlaychange` | Form tab changes (`Shared::prevInlay` != current inlay) |
| `inlaychange` | Main layout changes (`Shared::mainLayout` != requested view) |

---

## 4. Component Reference

### 4.1 Shard

```
ClearView\Shard
├── implements Stringable, ArrayAccess, JsonSerializable, Iterator
├── $data[]       — field storage (glyph, value, children, name, id…)
├── $primaryField — default field returned on string cast (usually 'value')
├── $address      — "inlay-id" key in Mosaic
├── $childType    — StringArray|ShardArray|PageArray|ChildArray|UndefinedArray
└── $canonicalId  — true when id="#" (expands to pane-inlay-name on read)
```

Key methods:
- `loadShard($obj, id, inlay, glyph, from)` — Factory: parses input (HTML, JSON, mangled, view),
  creates the correct glyph class, calls `canonicalizeChildren()`.
- `canonicalizeChildren()` — Recursively stores named children in Mosaic and replaces
  their tree slots with Reference glyphs. Unnamed children are left inline.
- `getField($name)` — Returns a single field. Supports search operators (`name=Evan`).
- `getVar($expression)` — Delegates to `Mosaic::getVar()`.

### 4.2 Element (Shard subclass)

```
ClearView\Element extends Shard
├── HTMX attribute generation (hx-post, hx-swap, hx-target, hx-trigger)
├── CSS variable injection (css::varname)
├── Inline style/script/event handlers (style, script, on::event)
└── render() → outputs HTML via Facet
```

Every HTML tag rendered by ClearView is an Element. Glyph subclasses (in `glyphs/`)
override `init()`, `render()`, or `html()` to customize behavior. Examples:
`main`, `button`, `input`, `formheader`, `layout`, `pane`, `reference`.

### 4.3 Pane (standalone base class)

```
ClearView\Pane
├── implements ArrayAccess
├── Constructor registers $this as CurrentPane
├── handleCommand($command) — CSRF validation + method dispatch
├── open() — renders pane HTML via Facet
├── redirect($url) / reloadPage()
├── triggerevent($event) — sends HX-Trigger header
└── doesNotUnderstand() — error for unknown commands
```

The Pane is the top-level rendering context for a URL namespace.
It does not extend Element; it owns a Mosaic and renders through
its body Element. All inlay classes extend Pane.

### 4.4 Inlay (Pane subclass)

`ClearView\Inlay` is the base class for inlay URL handlers. It extends Pane.
The default `html()` method returns `Page::body` for the pane's ProcessWire page.
Inlay classes live under `panes/<panename>/<inlayname>.php` and are loaded
by `Pane::loadInlay()`.

### 4.5 Main (Pane subclass)

`ClearView\Main` (formerly `Default.php`) is the default route handler for URLs
without their own pane. It renders the full `<html>` document and delegates
content to the `<main>` glyph.

### 4.6 Mosaic

Request-scoped key-value store owned by the current Pane. Shards are stored at address `"inlay-id"`.
Client-side state is deflated into hidden `<input>` fields and re-inflated
on each request.

Key operations:
- `loadMosaic($input)` — Parses POST data. Keys in `inlay-id` format are
  loaded as Shards; plain keys are stored under `Shared::lastInlay`.
- `getVar($expression)` — Uses QueryParser to resolve `Inlay::id.field`
  expressions, including Crystal lookups.
- `setVar($varname, $val)` — Stores a value, creating a Shard if needed.
- `index($inlay, $id)` — Direct Shard retrieval by address.
- Deflates changed Shards to hidden inputs via the Mosaic glyph.

### 4.7 Facet

Rendering engine with a static tag stack. Methods chain:
```
(new Facet($this))
    ->open("<div>")        // push tag onto stack
    ->forward('render')    // call $this->render()
    ->close();             // pop stack, auto-close tags
```

Key features:
- **Template expansion**: `{{Page::title}}` resolves via `Facet::me()->getField()`
  or `Mosaic::getVar()`.
- **OOB output**: `->oob()` sections buffer HTML for `Mosaic::sendOOB()`.
- **Recording**: `->record()` captures output to string.
- **Conditionals**: `match:` and `unless:` arrays gate output.
- **onClose callbacks**: Registered methods execute on tag close.

### 4.8 Crystal

Abstract base extending Page. `plugAllCrystals()` auto-instantiates
all concrete subclasses in `crystals/` and registers them under the `'ClearView'`
inlay.

Active crystals:
| Crystal | Wraps | Example |
|---------|-------|---------|
| `Config` | Static config array | `{{Config::layername_clearview}}` |
| `Page` | ProcessWire `$page` | `{{Page::title}}` |
| `Pane` | ProcessWire page for pane URL | `Pane::name` (page name), `Pane::title` |
| `User` | Current ProcessWire user | `{{User::displayname}}` |
| `Session` | ProcessWire `$session` | Login state, CSRF tokens |
| `Input` | `$input` (GET/POST) | `{{Input::requestMethod}}` |
| `Find` | `$pages->find()` | `Find::template=basic-page` |
| `View` | View file loading | `View::filename` |
| `Sanitizer` | ProcessWire sanitizers | `{{text20\Sanitizer::variablename}}` |

### 4.9 Pane Crystal (crystals/Pane.php)

Wraps the ProcessWire page identified by the current pane name.
`Pane::name`, `Pane::title`, and other field accesses delegate to
the ProcessWire page. Falls back to **raw Mosaic shards** under the
`'Pane'` inlay for transient values (e.g., `Pane::Key` as a CSRF token
stored via URL parameters before the Mosaic is opened).

### 4.10 Reference Glyph (glyphs/reference.php)

A proxy Element that forwards all access to a named Shard stored in Mosaic.
References live at the **anonymous inlay** (`__anonymous`) so they never
register in Mosaic themselves — that would overwrite the target.

```
Tree before canonicalization:           Tree after:
<div>                                    <div>
  <input name="username">                  <reference name="username">
</div>                                   </div>
                                         Mosaic:
                                           form-username → <input …>
```

- `getField()` forwards to target (except `name` and `glyph`).
- `render()` / `getHtml()` delegate to target.
- `deflate()` emits only `{glyph, name}` — minimal wire format.
- Resolution uses `__refInlay` to find the target in Mosaic.

This replaces the legacy `__unnamedXXXX` system. Named elements exist exactly
once — in the Mosaic. The DOM tree holds cheap references.

### 4.11 `<main>` Glyph (glyphs/main.php)

The `<main>` element is the default content area for ProcessWire pages.
Its `init()` method sets:

```php
hx-boost="true"     // All internal links/forms within <main> use AJAX
hx-target="this"    // Replace <main> on navigation
hx-ext="preload buzz"
```

When `<main>` navigates via hx-boost, `pushWatchedFieldOOB()` reads the
`page-fields` attribute (comma-separated field names like `headline,summary,sidebar`)
and emits OOB `innerHTML` swaps for each field. This keeps header, sidebar,
and other page chrome in sync during SPA-style navigation.

---

## 5. Module System

`Page::buildModuleStack()` walks the ProcessWire page hierarchy collecting
`modules` field values (child-first), always appending `"vendor"` last.

Example: if `/foo/bar` has `modules = "barmodule"` and `/foo/bar/baz` has
`modules = "bazmodule"`, the stack is `["bazmodule", "barmodule", "vendor"]`.

Both `loadInlay()` and `loadGlyph()` search the module stack before falling
back to ClearView's base directories.

Directory layout:
```
ClearView/
├── modules/
│   └── vendor/
│       ├── glyphs/     ← Built-in glyphs (button.php, input.php, etc.)
│       └── views/      ← Built-in views
├── glyphs/             ← Base glyph directory (fallback)
├── panes/              ← Base pane/inlay directory (fallback)
├── crystals/           ← Crystal classes
└── views/              ← Base view directory (fallback)
```

Views use wildcard globs for combining: `<fragment view="head/*"/>` loads all\nmatching files across every module and combines them as children.

---

## 6. Views and Fragments

- Views are PHP files under `modules/<module>/views/<name>.php`.
- `jsonmangler::fromhtml()` converts captured HTML/fields into Shard trees.
- Self-closing tags (`<hr/>`, `<img/>`) never load default views.
- Default views can nest: `<head>` may load `views/head/<child>.php`.
- View names may contain folders: `<fragment view="icons/*"/>` loads all matching files as siblings.
- `view=""` on an element overrides its children with the loaded view fragment.

---

## 7. Namespace Conventions

| Prefix    | Resolves To |
|-----------|------------|
| `Pane::`  | **Pane Crystal** — ProcessWire page fields for the pane URL |
| `Page::`  | **Page Crystal** — ProcessWire page associated with the request |
| `Shared::`| **Mosaic shared namespace** — transient inlay-scoped variables |
| `User::`  | ProcessWire current user |
| `Config::`| ClearView config constants |
| `Session::`| ProcessWire session data |
| `Input::` | Raw GET/POST variables |
| `Find::`  | ProcessWire page search |
| `View::`  | View file loader |

Key distinction: `Pane::` was historically an ambiguous Mosaic shared namespace.
It is now a **Crystal** wrapping the ProcessWire page. Mosaic shared state uses
`Shared::` instead (e.g., `Shared::lastInlay`).

---

## 8. Dispatch Defaults

`Pane::defaultMethod()` is the single source of truth:

| Request Method | Default Method |
|---------------|----------------|
| GET           | `open`         |
| POST          | `html`         |
| PUT           | `put`          |
| DELETE        | `delete`       |
| CLI           | `open`         |

These apply when the URL has no explicit command segment. The `<main>` element
overrides to `html` for hx-boosted navigation regardless of request method.
`hx-boost` requests from `<article>` are still dispatched normally; it is the
`<article>` that boosts, not `<main>`.

---

## 9. QueryParser Extensions

The template expression parser (`{{}}`) supports these extensions beyond basic field access:

| Syntax | Behavior |
|--------|----------|
| `Pane::field` | Resolves fields from the ProcessWire page represented by the pane URL |
| `Inlay::method` | Calls methods on the current inlay instance |
| `Glyph::method` | Calls methods on the current element |
| `CrystalName::field` | General method dispatch for any crystal matching the inlay name |
| `^^` (XOR) | `{{A}}^^ {{B}}` returns the non-null one; error if both non-null |

Null values eat one trailing space in template strings: `{{null}} value` → `"value"`.

---

## 10. Key Files

| File | Role |
|------|------|
| `ClearView.php` | Crystal: OOB buffer, script/async JS, debug output, HTMX helpers |
| `Pane.php` | Base Pane class: constructor, handleCommand(), defaultMethod(), event/redirect helpers |
| `Inlay.php` | Base Inlay class for inlay URL handlers |
| `Main.php` | Default route handler (extends Pane) |
| `Shard.php` | Core data unit: loadShard(), canonicalizeChildren(), field access |
| `Element.php` | HTML rendering: HTMX attributes, CSS vars, event handlers |
| `Mosaic.php` | Shard store: loadMosaic(), getVar(), setVar(), index(), ArrayAccess |
| `Facet.php` | Rendering engine: tag stack, template expansion, OOB, recording |
| `Crystal.php` | Abstract ProcessWire wrapper: plugAllCrystals() |
| `crystals/Pane.php` | Pane Crystal: ProcessWire page for pane URL |
| `crystals/Config.php` | Configuration constants |
| `glyphs/main.php` | `<main>` element: hx-boost + watched field updates |
| `glyphs/reference.php` | Reference proxy glyph |
| `Page.php` | ProcessWire page wrapper |

---

## 11. Architecture Status

The following items have been implemented and are now **current behavior**.
They are listed here for historical reference:

| Item | Status |
|------|--------|
| Pane.php → glyphs/pane.php, Form.php merged into Pane | **Done** |
| `Reference` glyph fully replaces `__unnamed` | **Done** |
| Module search path stacking (`buildModuleStack()`) | **Done** |
| `Pane::` → Pane Crystal, `Shared::` → Mosaic shared namespace | **Done** |
| `<main>` hx-boost with OOB field updates | **Done** |
| `defaultMethod()` request→method mapping | **Done** |
| Two-tier glyph/module loader replacing legacy `panes/<name>s/` | **Done** |
| Field content loaded from `Page::contents` instead of `json_template` | **Done** |
| `inlay` data field removed — `inlay()` returns `Input::inlayname` | **Done** (2026-06) |
| `SHARD_ANONINLAY` constant → `isAnonymous()` method (no name = anonymous) | **Done** (2026-06) |
| `childType` flags removed → `getChildType()` runtime inspection | **Done** (2026-06) |
| Prisms: AlertBox (N-button modal), Confirm (transparent yes/no) | **Done** (2026-06) |
| `listen` attribute on `<pane>` for lifecycle events → `events.php` inlay | **Done** (2026-06) |
| `triggerevent`/`sendHtmxHeader`/`retargetResult` made static with `on*` instance hooks | **Done** (2026-06) |
| Per-module `_init.php` config via `Config::fill()` | **Done** (2026-06) |
| `Module` crystal for PW module autoloading | **Done** (2026-06) |
| User crystal `add()`/`update()`/`delete()` with auto-save | **Done** (2026-06) |
| Wiki module: MarkdownToShard parser, WikiPage crystal, `_init.php` Page override | **Done** (2026-06) |
| Default view lookup removed — explicit `view=` required; wildcards combine across modules | **Done** (2026-06) |

### Planned (not yet implemented)

| Item | Status |
|------|--------|
| Drop `s` suffix on inlay directories (`panes/forms/` → `panes/form/`) | **Planned** |
| `<pane>` tag creates embedded pane with `hx-trigger="load"` | **Planned** |
| Layerstack as `<layerstack>` glyph with `addLayer()` | **Planned** |
| Boolean function operators in templates (\\|\\|, &&) | **Done** (2026-06) |
| Children type coercion (StringArray ↔ ShardArray migration) | **Done** — `getChildType()` inspects array, `addChildren()` enforces, `replaceChildren()` normalizes |
| Testing framework per design docs | **Planned** |

---

## 12. Key Glyphs

| Glyph | Role |
|-------|------|
| **main** | No self-boost; renders the Mosaic inside itself; layout driven by `Shared::mainLayout` |
| **article** | hx-boost target; `inlay=` sets initial `Shared::prevInlay` |
| **attr** | Surreal script tag; copies/modifies parent attrs; `view="newlayout"` triggers layout change |
| **pane** | Pane loader `<div>` with hx-get, hx-target=self, hx-swap=outerHTML |
| **aside** | Registers `name` in `Shared::updateFields`; OOB-updated on inlay/layout change |
| **layout** | Loads a view and replaces itself with the rendered fragment |
| **tabbar** | Renders tabs that fetch inlay URLs into the nearest article |
| **button** | Action button with login/logout/submit modes |
| **closebutton** | Listens for `closepane` event and closes parent pane |
| **reference** | Proxy Element — forwards all access to a named Shard in Mosaic |
| **input** | Form input with Mosaic-backed value and validation |
| **form** | Form container with CSRF and Mosaic integration |

---

## 13. Hazards

- This overview is a **reference snapshot**. The wiki is the master spec.
- `setlocalmethod()` is removed from `Element.php` and from all docs.
- `Form.php` is removed; its behavior merges into `Pane.php` and `glyphs/pane.php`.
- Anonymous Shards have no `name` field — detected via `isAnonymous()`, never sent to client.
- Canonical ids (`id="#"`) expand to `<panename>-<inlay>-<name>` via `Element::getField('id')`.
- The generated phpdoc in `docs/phpdoc/` is auto-generated via `phpDocumentor` (configured in `phpdoc.dist.xml`). Do not edit it by hand.

---

*This document supersedes `overview.txt` and `docs/OVERVIEW.md` as the
authoritative current-state architecture reference. See the wiki at
`/home/ekl/vault/wiki/entities/clearview/_index.md` for the master spec.*
