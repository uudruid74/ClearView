![ClearView Logo](assets/Clearview-Icon-CIR-256.png)

# ClearView Architecture Overview

Authoritative current-state entry point for the ClearView framework.
Last updated: 2026-06-10. This document describes what is **implemented and working now**.
For proposed changes not yet built, see §8 (Refactor In Progress) and `changes.txt`.

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
            ClearView::init($template)
                       │
            loadInlay(panename, inlayname)
                       │
            Pane::handleCommand($method)
                       │
            Facet rendering → HTML output
```

Core concepts:

| Concept  | Role |
|----------|------|
| **Pane** | A self-contained UI namespace with its own URL prefix and Mosaic. Subclass of Element. |
| **Inlay** | A PHP class loaded into a Pane that handles a portion of the UI (e.g., a form tab). |
| **Shard** | The basic data unit — like a mini-cookie stored in the page, not the browser. |
| **Mosaic** | Singleton key-value store of Shards, indexed by `inlay-id`, deflated to hidden inputs. |
| **Facet** | Rendering engine with tag stack, template expansion (`{{}}`), and OOB output. |
| **Crystal** | Singleton Shard wrapping a ProcessWire API (Page, User, Session, etc.). |
| **Glyph** | An Element subclass that renders a specific HTML tag with custom behavior. |
| **Reference** | Proxy glyph — named children live once in Mosaic; tree slots hold References. |

---

## 3. Request Lifecycle

### 3.1 Bootstrap

1. ProcessWire receives the HTTP request and matches it to a template file.
2. `_init.php` and `_main.php` bootstrap ProcessWire in delayed-output mode.
3. `ClearView::init($template)` is called with the template name.

### 3.2 URL Parsing

- **Default template** (`$template == 'Default'`): panename = `'Default'`, inlayname = `'Pane'`.
  This is the main page — it renders ProcessWire page content via the `<main>` glyph.
- **All other templates**: The page URL is split on `/` to extract `panename`, `inlayname`, and `command`.
  Example: `/form/login/` → pane=`form`, inlay=`login`, command=``.

### 3.3 Command Resolution

- If this is an **hx-boosted** request (from `<main>` navigation): command is forced to `'html'`.
- If the URL had no command segment: `ClearView::defaultMethod()` maps
  `GET→open`, `POST→html`, `PUT→put`, `DELETE→delete`.
- Otherwise the URL command is used directly.

### 3.4 Pane Loading

`ClearView::loadInlay(panename, inlayname)` searches for the class file:

1. Module stack: `modules/<module>/panes/<lowerpanename>s/<Inlayname>.php`
2. Module stack: `modules/<module>/panes/<Panename>.php`
3. Base: `panes/<lowerpanename>s/<Inlayname>.php`
4. Base: `panes/<Panename>.php`

The Pane Crystal is wired to the correct ProcessWire page so that
`Pane::name`, `Pane::title`, etc. resolve from the page's fields.

### 3.5 Dispatch

`Pane::handleCommand($command)`:
1. Loads Mosaic data from `Input::all` (slurps client-side state).
2. Validates the **PaneKey** CSRF token (`Pane::Key` must match `Session::PaneKey`).
3. Refuses private/underscored methods.
4. Executes the method via Facet forwarding.
5. Falls back to **ProcessWire page field** lookup if no method exists.
6. Dumps buffered OOB data and scripts.

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

### 4.3 Pane (Element subclass)

```
ClearView\Pane extends Element
├── Constructor registers $this as CurrentPane
├── handleCommand($command) — CSRF validation + method dispatch
├── open() — renders pane HTML via Facet
├── redirect($url) / reloadPage()
├── triggerevent($event) — sends HX-Trigger header
└── doesNotUnderstand() — error for unknown commands
```

The Pane is the top-level rendering context for a URL namespace.
All inlay classes extend Pane.

### 4.4 Mosaic

Singleton key-value store. Shards are stored at address `"inlay-id"`.
Client-side state is deflated into hidden `<input>` fields and re-inflated
on each request.

Key operations:
- `loadMosaic($input)` — Parses POST data. Keys in `inlay-id` format are
  loaded as Shards; plain keys are stored under `Shared::lastInlay`.
- `getVar($expression)` — Uses QueryParser to resolve `Inlay::id.field`
  expressions, including Crystal lookups.
- `setVar($varname, $val)` — Stores a value, creating a Shard if needed.
- `index($inlay, $id)` — Direct Shard retrieval by address.
- `dumpVars()` — Outputs hidden inputs for changed Shards.

### 4.5 Facet

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

### 4.6 Crystal (Page subclass)

Abstract base wrapping ProcessWire objects. `plugAllCrystals()` auto-instantiates
all concrete subclasses in `crystals/` and registers them under the `'ClearView'`
inlay.

Active crystals:
| Crystal | Wraps | Example |
|---------|-------|---------|
| `Config` | Static config array | `{{Config::layername_clearview}}` |
| `Page` | ProcessWire `$page` | `{{Page::title}}` |
| **Pane** | ProcessWire page for pane URL | `Pane::name` (page name), `Pane::title` |
| `User` | Current ProcessWire user | `{{User::displayname}}` |
| `Session` | ProcessWire `$session` | Login state, CSRF tokens |
| `Input` | `$input` (GET/POST) | `{{Input::requestMethod}}` |
| `Find` | `$pages->find()` | `Find::template=basic-page` |
| `View` | View file loading | `View::filename` |
| `Sanitizer` | ProcessWire sanitizers | `{{text20\Sanitizer::variablename}}` |

### 4.7 Pane Crystal (crystals/Pane.php)

Wraps the ProcessWire page identified by the current pane name.
`Pane::name`, `Pane::title`, and other field accesses delegate to
the ProcessWire page. Falls back to **raw Mosaic shards** under the
`'Pane'` inlay for transient values (e.g., `Pane::Key` as a CSRF token
stored via URL parameters before the Mosaic is opened).

### 4.8 Reference Glyph (glyphs/reference.php)

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

### 4.9 <main> Glyph (glyphs/main.php)

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

`ClearView::buildModuleStack()` walks the ProcessWire page hierarchy collecting
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

Views support `*_append.php` files: `head_append.php` adds to the parent
module's `head.php` rather than replacing it.

---

## 6. Namespace Conventions

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

## 7. Dispatch Defaults

`ClearView::defaultMethod()` is the single source of truth:

| Request Method | Default Method |
|---------------|----------------|
| GET           | `open`         |
| POST          | `html`         |
| PUT           | `put`          |
| DELETE        | `delete`       |
| CLI           | `open`         |

These apply when the URL has no explicit command segment. The `<main>` element
overrides to `html` for hx-boosted navigation regardless of request method.

---

## 8. Refactor In Progress

The following changes are described in `changes.txt` and have **not yet been
implemented** (as of 2026-06-10). They are listed here so readers can
distinguish current behavior from future plans.

| Item | Status |
|------|--------|
|| Move panes & inlays under `glyphs/` directory | **Done** — Pane.php → glyphs/pane.php, Form.php merged into Pane |
| Drop `s` suffix on inlay directories | **Planned** — `panes/forms/` → `panes/form/` |
| `<pane>` tag creates embedded pane with `hx-trigger="load"` | **Planned** — CSRF token created by `<pane>` and `<button>` tags |
| Layerstack as `<layerstack>` glyph with `addLayer()` | **Planned** — replaces ad-hoc dialog management |
| Boolean function operators in templates (`\|\|`, `&&`) | **Planned** — `{{User::isLoggedIn()\|\|"Please log in"}}` |
| Children type coercion (StringArray ↔ ShardArray merging) | **Planned** |
| `Reference` glyph fully replaces `__unnamed` | **Done** — canonicalizeChildren() is implemented |
| Module search path stacking (`buildModuleStack()`) | **Done** |
| `Pane::` → Pane Crystal, `Shared::` → Mosaic shared namespace | **Done** |
| `<main>` hx-boost with OOB field updates | **Done** |
| `defaultMethod()` request→method mapping | **Done** |
| Two-tier glyph/module loader replacing legacy `panes/<name>s/` | **Done** |
| Field content loaded from `Page::contents` instead of `json_template` | **Done** |

---

## 9. Key Files

| File | Role |
|------|------|
| `ClearView.php` | Singleton controller: init(), loadInlay(), loadGlyph(), buildModuleStack(), defaultMethod() |
| `Pane.php` | Base Pane class: constructor, handleCommand(), event/redirect helpers |
| `Shard.php` | Core data unit: loadShard(), canonicalizeChildren(), field access |
| `Element.php` | HTML rendering: HTMX attributes, CSS vars, event handlers |
| `Mosaic.php` | Shard store: loadMosaic(), getVar(), setVar(), index(), dumpVars() |
| `Facet.php` | Rendering engine: tag stack, template expansion, OOB, recording |
| `Crystal.php` | Abstract ProcessWire wrapper: plugAllCrystals() |
| `crystals/Pane.php` | Pane Crystal: ProcessWire page for pane URL |
| `crystals/Config.php` | Configuration constants |
| `glyphs/main.php` | `<main>` element: hx-boost + watched field updates |
| `glyphs/reference.php` | Reference proxy glyph |
| `Page.php` | ProcessWire page wrapper |

---

*This document supersedes `overview.txt` and `docs/OVERVIEW.md` as the
authoritative current-state architecture reference. See `changes.txt` for
the detailed refactor proposal that drives ongoing development.*
