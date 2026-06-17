# ClearView Testing Framework — Consolidated Design

Status: Draft design document for Kanban task `t_dc4f5a9d`.  
Consolidates requirements from `t_89d2d9be`, test catalog `t_86caf25d`, fixture/harness design `t_95cc0135`, and architecture ADR `t_f138dcba`.

## 1. High-level overview

The ClearView Testing Framework turns the existing command-line utilities (`utility/dumpurl.php`, `utility/jsonmangler.php`) into a deliberate, local-and-CI-ready test system.  It keeps the render/inlay code path as the primary seam and layers curl smoke tests on top.

### P0–P3 capability summary

| Priority | Capability | Primary seam |
|---|---|---|
| P0 | Fast, deterministic unit tests for `Element` render output | `Element::render()` / `Shard::getHtml()` |
| P0 | Programmatic `View`/`Mosaic` assembly with assertion helpers | `ClearView\Test\Fixture\ViewBuilder` |
| P1 | Injectable synthetic data source for inlays | `ClearView\Test\Fixture\InlayStub` + `InlayRegistry` |
| P1 | Stable verification methods on `Pane` and `Element` subclasses | `Pane::handleCommand()` + CSRF validation via `Pane::validateCsrf()` |
| P2 | Curl-based smoke tests against a live server instance | `ClearView\Test\Harness\ServerHarness` |
| P3 | CI-ready test runner with concise output and failure diffs | `utility/clearview-test.php` |

## 2. Detailed component descriptions

### 2.1 Test runner — `utility/clearview-test.php`

Thin CLI wrapped around PHPUnit.

```text
php utility/clearview-test.php [options]

Options:
  --filter <pattern>     Run tests whose class/method matches <pattern>
  --suite <name>         Run one PHPUnit testsuite (unit|integration|smoke)
  --no-smoke             Exclude smoke tests even if present in the suite
  --base-url <url>       Override base URL for smoke tests
  --ci                   Concise one-line-per-test output for CI
  --bootstrap <file>     Override tests/bootstrap.php
```

Exit-code contract:

| Code | Meaning |
|---|---|
| 0 | All assertions pass |
| 1 | Assertion failure |
| 2 | Bootstrap / config / environment failure |
| 3 | No tests matched the selector |

### 2.2 Bootstrap — `tests/bootstrap.php`

Fast path loads ClearView core classes and minimal ProcessWire stubs, then initializes `Mosaic`.  Integration and smoke tests may still use `utility/dumpurl.php` or a running server when a full boot is required.

### 2.3 Fixtures — `ClearView\Test\Fixture`

#### `ViewBuilder`

Fluent runtime assembler for views/elements.  Resets `ClearView`, `Mosaic`, and `Facet` singleton state on creation.

```php
ViewBuilder::new(?string $panename = 'TestPage', ?string $inlay = 'Default'): self
    ->withShard(string $id, array $data, ?string $inlay = null): self
    ->withElement(string $id, string $tag, array $attrs, ?string $inlay = null): self
    ->withChild(string $parentId, string $childId): self
    ->withVar(string $expression, mixed $value): self
    ->withInlayStub(InlayStub $stub): self
    ->reset(): self
    ->render(?string $id = null): string;
```

- `Pane::name` and the Pane crystal are initialized from `$panename`.
- `Page::url` defaults to `/{panename}/`.
- `render()` captures output via `Facet::record()` or equivalent reflection-safe capture.

#### `InlayStub`

Provide synthetic inlay data without touching module glyphs or ProcessWire.

```php
InlayStub::for(string $panename, string $inlayname): self
    ->returns(array|Shard $data): self
    ->returnsCallable(callable $fn): self
    ->register(): self;
```

`ClearView::loadInlay()` consults `ClearView\Test\InlayRegistry` when `ClearView::inTesting()` is true.  If a stub exists for the requested pane/inlay pair, the registry returns a generated class that exposes the configured data.

#### `TestFixtureException`

Used for duplicate IDs, missing named shards, and invalid stub return values.

### 2.4 Harness — `ClearView\Test\Harness`

#### `ServerHarness` and `TestResponse`

Curl-based smoke helper.  Defaults to 30s timeout.

```php
ServerHarness::at(string $baseUrl): self
    ->withHeader(string $name, string $value): self
    ->withTimeout(int $seconds): self
    ->asHtmx(): self
    ->get(string $path): TestResponse
    ->post(string $path, array $data = []): TestResponse
    ->put(string $path, array $data = []): TestResponse
    ->delete(string $path): TestResponse
    ->postMosaic(string $path, array $mosaicData): TestResponse;
```

```php
TestResponse::status(): int;
TestResponse::body(): string;
TestResponse::headers(): array;
TestResponse::header(string $name): ?string;
TestResponse::json(): ?array;
```

Network errors are captured with status `0` and the cURL error message in the body.  `ServerHarness::at()` throws `TestHarnessException` if curl is unavailable.

#### `Pane::validateCsrf()`

Pane-level CSRF validation replaces the need for a separate helper.
`Pane::validateCsrf()` compares `Pane::Key` against `Session::PaneKey`
and returns `true` when `Pane::inTesting()` is true. Integration tests
exercise the real security gate without a separate token generator.

### 2.5 Inlay registry — `ClearView\Test\InlayRegistry`

Central lookup for stubs.  Hook in `ClearView::loadInlay()`:

```php
if (self::inTesting() && InlayRegistry::hasStub($panename, $inlayname)) {
    return InlayRegistry::getClass($panename, $inlayname);
}
```

## 3. Resolved conflicts

### 3.1 Pane-key validation in integration tests

Resolution:

- **Unit tests** invoke handler logic directly, or `Pane::validateCsrf()` returns true when `inTesting()`.
- **Integration tests** use `Pane::validateCsrf()` with real session data to exercise the real dispatch/security path.

This gives both speed and fidelity without production seam changes.

### 3.2 Render-output capture seam

The architecture doc suggested a possible return-capture seam on `Element::render()`.  The fixture design relies on `Facet::record()` / `Shard::getHtml()` existing today.  Resolution:

- Phase 1 uses existing `Shard::getHtml()` / `Facet::record()` capture.
- If that proves unreliable for a class under test, a small `Element::render(?Shard $shard = null): mixed` seam may be added as a follow-up task.

### 3.3 PHPUnit vs custom runner

Both parent docs agree: reuse `phpunit.phar`.  `utility/clearview-test.php` is a convenience wrapper, not a replacement engine.  `phpunit.xml.dist` remains the single source of truth for suites and directories.

## 4. Acceptance criteria for the framework MVP

1. `php utility/clearview-test.php --suite unit` runs P0 unit tests without a live ProcessWire install.
2. `ViewBuilder::new()->withElement(...)->render()` returns deterministic HTML for at least one existing glyph (`button`, `input`).
3. `InlayStub::for(...)->returns(...)->register()` causes a pane render to use injected data instead of page fields.
4. `Pane::validateCsrf()` returns true for matching tokens and throws on mismatch, confirmed via a headless integration test.
5. `ServerHarness::at($baseUrl)->get('/')` returns a `TestResponse` with correct status and body for a running ClearView server.
6. `--ci` emits one line per test/suite and returns exit code `1` on assertion failure, `0` on pass, `2` on bootstrap failure.
7. The framework achieves ≥ 80% line coverage in `ClearView.php`, `Mosaic.php`, `Shard.php`, `Element.php`, `Facet.php`, `Pane.php`, and `QueryParser.php`.
8. Existing utilities `dumpurl.php`, `jsonmangler.php`, and `phpunit.phar` remain functional and are referenced by the runner rather than replaced.

## 5. Rollout plan

### Phase 1 — Seams and fixtures
- Add/verify `ClearView\\Test\\InlayRegistry` hook in `ClearView::loadInlay()`.
- Implement `ViewBuilder`, `InlayStub`, `TestFixtureException`.
- Add round-trip helpers `Shard::toArray()` and `Mosaic::toArray()` if absent.

### Phase 2 — Unit and integration tests
- Write unit tests for `Element`, `Shard`, `Mosaic`, `QueryParser`, `ClearView::defaultMethod()`.
- Write integration tests for `ViewBuilder`, `Facet`, inlay stubs, and pane dispatch with `Pane::validateCsrf()`.
- Update `phpunit.xml.dist` with `tests/unit`, `tests/integration`, `tests/smoke` directories.

### Phase 3 — Smoke harness
- Implement `ServerHarness`, `TestResponse`, `TestHarnessException`.
- Write smoke tests for `/`, a pane load, an HTMX swap, and an OOB update.

### Phase 4 — Runner and CI
- Implement `utility/clearview-test.php` with the command interface and exit-code contract.
- Add `.clearview-test.json` schema and CI invocation example.
- Run the representative validation scenario (Section 6) end-to-end.

## 6. Validation plan

### Representative change

Refactor `ButtonElement` to emit `hx-post` from a normalized URL helper instead of raw shard data.

### Proof steps

1. **Unit** — `tests/unit/ButtonElementTest.php` asserts that a shard with `action: '/demo/save'` renders `hx-post="/demo/save"` before and after the refactor.
2. **Integration** — `tests/integration/ViewBuilderButtonTest.php` assembles a view containing a `button` glyph and asserts the rendered HTML contains the normalized attribute.
3. **Smoke** — `tests/smoke/DemoButtonSmokeTest.php` uses `ServerHarness` to request the demo pane and asserts the response body contains the normalized `hx-post` value.
4. Before/after comparison: run the suite against the pre-refactor commit, observe pass; apply the refactor; run again; observe pass.  A mis-normalized URL (e.g., dropping the trailing slash) is caught by at least one of the three layers.

### Success signal

The representative change passes all three layers without manual browser verification and produces a clear diff on regression.

## 7. Configuration

### `phpunit.xml.dist`

Canonical suite configuration.  Directories: `tests/unit`, `tests/integration`, `tests/smoke`.  Bootstrap: `tests/bootstrap.php`.

### `.clearview-test.json` (optional)

```json
{
  "smokeBaseUrl": "http://clearview.local",
  "ciOutput": true,
  "skip": ["tests/smoke/needs-real-db"],
  "inlayStubNamespaces": ["ClearView\\Test\\Fixture"]
}
```

## 8. New and modified modules

### New files

| Path | Purpose |
|---|---|
| `utility/clearview-test.php` | Main CLI test runner |
| `tests/bootstrap.php` | Fast headless bootstrap (extend if existing) |
| `Test/Fixture/ViewBuilder.php` | Runtime view assembly |
| `Test/Fixture/InlayStub.php` | Synthetic inlay data |
| `Test/InlayRegistry.php` | Stub lookup hook |
| `Test/Fixture/TestFixtureException.php` | Fixture errors |
| `Test/Harness/ServerHarness.php` | Curl smoke helper |
| `Test/Harness/TestResponse.php` | HTTP response wrapper |
| `Test/Harness/TestHarnessException.php` | Harness errors |
| `Test/PaneKeyHelper.php` | REMOVED — replaced by Pane::validateCsrf() built into handleCommand() |
| `docs/testing/framework-design.md` | This consolidated document |

### Files to modify

| Path | Change |
|---|---|
| `ClearView.php` | Verify `InlayRegistry` hook and `inTesting()` semantics |
| `Element.php` | Optional: return-capture seam if `Facet::record()` proves insufficient |
| `phpunit.xml.dist` | Add `tests/unit`, `tests/integration`, `tests/smoke` directories |

### Retained existing tools

| Path | Role |
|------|------|
| `utility/dumpurl.php` | Full-URL render for integration smoke checks |
| `utility/jsonmangler.php` | Utility class used by headless tests |

## 9. Local and CI invocation

Local:

```bash
php utility/clearview-test.php --suite unit
php utility/clearview-test.php --suite smoke --base-url http://localhost/
```

CI:

```bash
php utility/clearview-test.php --ci
```

CI should supply `CLEARVIEW_BASE_URL` for smoke tests; if absent, smoke tests self-skip unless `--require-smoke` is set.

## 10. References

- Requirements: `vault/fabric/wintermute-research-wintermute-requirements-clearview-testin-85d7.md`
- Test catalog: `docs/testing/test-catalog.md`
- Fixture/harness design: `vault/wiki/entities/clearview/design-testing-fixtures-and-harness.md`
- Architecture ADR: `vault/wiki/entities/clearview/design-testing-framework-architecture.md`
