# ClearView Testing Framework Architecture

Status: Draft ADR for Kanban task `t_f138dcba`.
Derived from: requirements in `t_89d2d9be`, fixture/harness design `t_95cc0135`, and test catalog `t_86caf25d`.

## 1. Goals and scope

Transform the existing utility command-line scripts (`utility/dumpurl.php`, `utility/jsonmangler.php`) from one-off debugging tools into a deliberate, pluggable testing framework that ClearView contributors can run locally and in CI.

The framework must satisfy the P0–P3 capability list captured in the requirements fabric entry:

- P0: fast, deterministic unit tests for Element render output.
- P0: programmatic View/Mosaic assembly with assertion helpers.
- P1: injectable synthetic data source for inlays.
- P1: stable verification/test methods on Pane and Element subclasses.
- P2: curl-based smoke tests against a live server instance.
- P3: CI-ready test runner with concise output and failure diffs.

Primary seam remains the render/inlay code path. HTTP smoke tests are an outer layer, not the primary mechanism.

## 2. Guiding principles

1. **Isolate singletons, do not remove them.** `ClearView`, `Mosaic`, and `Facet` are singletons. Tests reset their static state between cases instead of fighting the pattern.
2. **Prefer headless tests.** A full ProcessWire bootstrap (`utility/dumpurl.php`) is available but slow; it is reserved for integration and smoke tests.
3. **No production code changes unless they are tiny seams.** The framework may add small test-only hooks (`Element::render()` return capture, `InlayRegistry` lookup in `ClearView::loadInlay()`), but it must not rearchitect ClearView.
4. **Reuse PHPUnit.** PHPUnit `10.x` is already present as `phpunit.phar` with `phpunit.xml.dist`. The new runner is a thin CLI wrapper, not a replacement test engine.
5. **One source of truth for test selection.** Configuration lives in `phpunit.xml` / `phpunit.xml.dist`; the runner reads suites from it and adds optional filters for local convenience.

## 3. Runner lifecycle

Every test invocation follows the same lifecycle:

```
Discovery → Setup → Execution → Reporting → Teardown
```

### 3.1 Discovery

- Read suites from `phpunit.xml` / `phpunit.xml.dist`.
- Discover `<directory>` entries: `tests/unit`, `tests/integration`, `tests/smoke`.
- Optional CLI filters narrow by path/name: `--filter Name`, `--suite unit`, `--smoke`, `--no-smoke`.

### 3.2 Setup

- Reset singletons (`ClearView`, `Mosaic`, `Facet`) before each test case.
- Initialize headless ClearView state when `dumpurl.php`-style ProcessWire bootstrap is not used.
- Register synthetic inlays via `ClearView\Test\InlayRegistry` when `inTesting()` is true.
- Configure `curl` smoke harness with base URL, default headers, and timeout.

### 3.3 Execution

- PHPUnit runs unit and integration tests as normal.
- Smoke tests are thin PHPUnit test cases that call `ServerHarness` to hit a live server.
- Pane-key and CSRF helper utilities generate matching tokens for command-dispatch tests.

### 3.4 Reporting

- Default PHPUnit output for local dev.
- Concise one-line-per-test format for CI, with exit-code contract:
  - `0` = all assertions pass
  - `1` = assertion failure
  - `2` = bootstrap/config/environment failure
  - `3` = no tests matched the selector
- Failure diffs use normalized DOM/HTML when comparing render output.

### 3.5 Teardown

- Output buffers drained.
- Singletons reset for the next case.
- ServerHarness closes no persistent resources; curl handles are per-request.

## 4. Command interface

Primary CLI entry point: `utility/clearview-test.php`

```text
php utility/clearview-test.php [options]

Options:
  --filter <pattern>     Run tests whose class/method matches <pattern>.
  --suite <name>         Run one PHPUnit testsuite (unit|integration|smoke).
  --no-smoke             Exclude smoke tests even if present in the suite.
  --base-url <url>       Override base URL for smoke tests.
  --ci                   Concise output + exit-code contract for CI.
  --bootstrap <file>     Override tests/bootstrap.php.
```

Legacy direct commands remain available:

- `php utility/dumpurl.php <url>` — boot ProcessWire and render one URL to stdout.
- `php utility/jsonmangler.php` — (if converted) standalone JSON mangler tests.

## 5. Configuration format

Framework configuration is layered:

1. `phpunit.xml` / `phpunit.xml.dist` — canonical suite/directory/bootstrap config.
2. `.clearview-test.json` (optional) — project-specific overrides:
   - default smoke base URL
   - CI formatter settings
   - paths to skip
   - inlay stub namespace whitelist

Example `.clearview-test.json`:

```json
{
  "smokeBaseUrl": "http://clearview.local",
  "ciOutput": true,
  "skip": ["tests/smoke/needs-real-db"],
  "inlayStubNamespaces": ["ClearView\\Test\\Fixture"]
}
```

## 6. Test registration and invocation

Framework tests are ordinary PHPUnit test cases plus ClearView fixture helpers.

Registration is file-based, by convention:

| Directory | Purpose | Example |
|---|---|---|
| `tests/unit/` | Pure class/method tests | `tests/unit/ShardTest.php` |
| `tests/integration/` | Multi-class fixtures | `tests/integration/ViewBuilderTest.php` |
| `tests/smoke/` | Live HTTP checks | `tests/smoke/HomePageSmokeTest.php` |

Invocation paths:

- Local: `php utility/clearview-test.php --suite unit`
- IDE: run PHPUnit directly with `phpunit.xml.dist`.
- CI: `php utility/clearview-test.php --ci`

Programmatic API namespacing:

- `ClearView\Test\Fixture\*` — builders and stubs.
- `ClearView\Test\Harness\*` — HTTP server harness.
- `ClearView\Test\*` — runner bootstrap utilities (`InlayRegistry`, pane-key helper).

## 7. Integration with ClearView

### 7.1 Singleton isolation

`ViewBuilder::reset()` uses reflection to null `ClearView::$instance`, `Mosaic::$instance`, and Facet static fields before each test.

### 7.2 ProcessWire bootstrap

- Fast path: `tests/bootstrap.php` loads ClearView core classes and minimal `ProcessWire` stubs, then calls `Mosaic::init()`.
- Full path: integration and smoke tests may still invoke `utility/dumpurl.php` or a running server.

### 7.3 Inlay stub registry

`ClearView::loadInlay()` already consults `ClearView\Test\InlayRegistry` when `inTesting()` is true. Stub classes are generated on the fly so tests can inject synthetic data without altering module glyphs.

### 7.4 Verification seams

Small production seams recommended (each is its own implementation task):

- `Element::render(?Shard $shard = null): mixed` — capture output for assertion without changing the echo path.
- `Shard::toArray(): array` and `Mosaic::toArray(): array` — round-trip helpers.
- `Pane::handleCommand()` already exists; tests call it with synthetic input.

## 8. Reporting output

Local default:

- PHPUnit progress + failure details.

CI mode (`--ci`):

```text
PASS  tests/unit/ShardTest.php  12 tests  0.02s
FAIL  tests/integration/ViewBuilderTest.php::testRendersHxPost
      Expected attribute hx-post="/demo/save/"
      Actual:   hx-post="/demo/save"
SKIP  tests/smoke/LoginSmokeTest.php  needs CLEARVIEW_BASE_URL
```

Exit codes match Section 3.4.

## 9. New and modified modules

### New files

| Path | Purpose |
|---|---|
| `utility/clearview-test.php` | Main CLI test runner |
| `tests/bootstrap.php` | Minimal/fast bootstrap (exists, may be extended) |
| `Test/Fixture/ViewBuilder.php` | Runtime view assembly |
| `Test/Fixture/InlayStub.php` | Synthetic inlay data |
| `Test/InlayRegistry.php` | Stub lookup hook for `ClearView::loadInlay()` |
| `Test/Fixture/TestFixtureException.php` | Fixture errors |
| `Test/Harness/ServerHarness.php` | Curl-based smoke helper |
| `Test/Harness/TestResponse.php` | HTTP response wrapper |
| `Test/Harness/TestHarnessException.php` | Harness errors |
| `Test/PaneKeyHelper.php` | Generate matching pane-key tokens |
| `docs/testing/framework-architecture.md` | This document |
| `docs/testing/framework-architecture.svg.html` | Architecture diagram |

### Files to modify (tiny seams or integration)

| Path | Change |
|---|---|
| `ClearView.php` | Already contains InlayRegistry hook; verify `inTesting()` semantics are stable |
| `Element.php` | Optionally add return-capture seam to `render()` for assertion helpers |
| `phpunit.xml.dist` | Add directories `tests/unit`, `tests/integration`, `tests/smoke` once created |

### Existing tools retained

| Path | Role in framework |
|---|---|
| `utility/dumpurl.php` | Full-URL render for integration smoke checks |
| `utility/jsonmangler.php` | Utility class used by headless tests |
| `phpunit.phar` | Underlying test engine |

## 10. Local vs CI invocation

Local:

```bash
# Unit only, fast
php utility/clearview-test.php --suite unit

# Integration with verbose PHPUnit output
php vendor/bin/phpunit --testsuite integration

# Smoke against local dev server
php utility/clearview-test.php --suite smoke --base-url http://localhost/
```

CI:

```bash
php utility/clearview-test.php --ci
```

CI pipeline should supply `CLEARVIEW_BASE_URL` for smoke tests; if absent, smoke tests self-skip with exit code `0` at the runner level unless `--require-smoke` is set.

## 11. References

- Requirements fabric entry: `vault/fabric/wintermute-research-wintermute-requirements-clearview-testin-85d7.md`
- Fixture/harness design: `vault/wiki/entities/clearview/design-testing-fixtures-and-harness.md`
- Test catalog: `ClearView/docs/testing/test-catalog.md`
- Consolidated design: `vault/wiki/entities/clearview/design-clearview-testing-framework.md`
