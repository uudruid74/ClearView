![ClearView Logo](assets/Clearview-Icon-CIR-256.png)

## ClearView

A **server-authoritative PHP framework** that treats the browser as a dumb display. No build step. No JavaScript framework. No API layer. Just PHP classes that render HTML, syncing server-side state to the page through hidden inputs swapped by HTMX.

---

### Architecture Overview

ClearView sits on top of ProcessWire CMS and uses HTMX for AJAX, Surreal for encapsulated CSS/JS, and PicoCSS for styling. The URL is the message bus — every interaction is a GET or POST to `/pane/inlay/method/`, mapping a filesystem path to a PHP class method.

**Request lifecycle:**
1. ProcessWire receives the request; `Crystal::loadAll()` initializes crystals.
2. The URL is split into `panename`, `inlayname`, and `command`. Default method mapping: `GET → open`, `POST → html`, `PUT → put`, `DELETE → delete`.
3. `Pane::loadInlay()` searches the module stack for the class file.
4. `Pane::handleCommand($command)` validates the CSRF token, loads Mosaic state from POST data, and dispatches to the matching method.
5. The Facet rendering engine outputs HTML with OOB (out-of-band) updates for changed Shards.

---

### Core Concepts

**Pane** — A self-contained UI namespace with its own URL prefix and Mosaic. The Pane is the top-level rendering context. Every page area is a Pane.

**Inlay** — A PHP class extending `Pane` that handles a portion of the UI (e.g., a form tab, a login widget). Inlays are the unit of development — you write an Inlay per user-facing feature.

**Shard** — The basic data unit, like a mini-cookie stored on the page, not in the browser. Each Shard carries an address (inlay-id), a glyph (its HTML tag type), and data fields. Named children are canonicalized — stored once in the Mosaic with lightweight Reference proxies in the DOM tree, avoiding redundant state duplication.

**Mosaic** — A server-side key-value store of Shards indexed by inlay-id. On render, it deflates to hidden `<input>` fields inside the HTML. On the next request, those inputs are POSTed back and re-inflated. The Mosaic glyph ensures only *changed* Shards are sent, keeping the wire efficient.

**Facet** — The rendering engine with a static tag stack. Tags are opened, content is forwarded through method calls, and tags auto-close on output. Supports template expansion (`{{Crystal::field}}`), OOB sections, recording, and conditional rendering via `match:`/`unless:` qualifiers.

**Crystal** — Singleton Shards wrapping ProcessWire APIs. Available in templates and code:
- `Page::title` — the current ProcessWire page
- `User::displayname` — the logged-in user
- `Session::*` — session data and CSRF tokens
- `Pane::*` — the ProcessWire page for the current pane URL
- `Config::*`, `Input::*`, `Find::*`, `View::*`

**Reference** — A proxy glyph that forwards all access to a named Shard stored in the Mosaic. When a child element has a `name` attribute, it is extracted, stored once in the Mosaic, and replaced in the DOM tree with a Reference. This solves redundant state duplication without requiring a normalization layer like Redux or Vuex.

---

### Module System

ClearView builds a module stack by walking the ProcessWire page hierarchy (child-first, always appending `"vendor"`). Both `loadInlay()` and `loadGlyph()` search the module stack before falling back to base directories:

```
modules/<module>/panes/<lowerpanename>s/<Inlayname>.php
modules/<module>/panes/<Panename>.php
panes/<lowerpanename>s/<Inlayname>.php
panes/<Panename>.php
```

Child pages inherit parent modules and can add their own overrides without editing vendor code.

---

### A Taste: Login Form

This is a complete Inlay from the framework's module library. It handles both login and logout, using `setVars()` to update form content and `triggerevent()` to notify other panes:

```php
namespace ClearView;
class loginform_login extends Inlay
{
    public function logout()
    {
        ClearView::Session()->logout();
        $this->triggerevent('userchange');
    }

    public function login()
    {
        // Requires inputs named username and password
        if (ClearView::Session()->trylogin()) {
            $this->setVars([
                'headline' => 'Login Succeeded!',
                'summary'  => 'Welcome back<br>{{text20\\User::displayname}}!',
                'login'    => 'Success!'
            ]);
            $this->triggerevent('userchange')
                 ->close();         // close the form
        } else {
            $this->setVars([
                'headline' => "Login Failed!",
                'summary'  => "Try again, or reset your password using the link below",
                'login'    => 'Try Again!'
            ]);
        }
    }
}
```

POST to `/loginform/login/login/` or hook up a button with `hx-post`. ProcessWire handles authentication; `setVars()` replaces the form's content via Mosaic diffs; `close()` removes the form from the DOM.

---

### SPA Navigation

The `<main>` glyph enables SPA-style navigation via `hx-boost`. A `page-fields` attribute lists field names (e.g., `headline,summary,sidebar`). When content swaps, `<main>` emits `hx-swap-oob` elements for each field, keeping page chrome in sync during partial swaps — with zero JavaScript.

---

### The Stack

| Layer | Technology |
|-------|------------|
| App server | RoadRunner |
| Backend / Auth / CMS | ProcessWire |
| AJAX transport | HTMX |
| CSS framework | PicoCSS |
| Encapsulated CSS/JS | Surreal (by Gnat) |
| State & rendering | Shard, Mosaic, Facet, Crystal |
| Gesture / touch | InteractJS (coming) |

---

### Getting Started

See **[docs/overview.md](docs/overview.md)** for the full architecture reference — class hierarchy, request lifecycle, rendering pipeline, module system, Crystal API wrappers, template syntax, and QueryParser reference.

You'll need a ProcessWire site, then drop ClearView into your site's root and wire up `_init.php`. Everything you need is in this repo.

---

ClearView was built for developers who got tired of debugging render trees in DevTools and just wanted to read the HTML. If that sounds like you, dig in.
