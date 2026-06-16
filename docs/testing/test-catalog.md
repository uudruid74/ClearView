# ClearView Test Catalog

Source: Wintermute requirements captured in `t_89d2d9be` and the ClearView architecture skill.

## 1. Metrics to measure

| Metric | Definition | Tool / seam |
|--------|------------|-------------|
| Render output correctness | HTML fragment matches expected structure, attributes, and text | Direct class method or CLI runner parse |
| Mosaic addressing determinism | Children appear at expected addresses; traversal is stable | Unit test against `Mosaic` assembly APIs |
| Pane/inlay dispatch coverage | Each method (`open`, `html`, `put`, `delete`) reaches the intended handler | CLI runner + mocked/synthetic ProcessWire state |
| HTMX attribute emission | `hx-get`, `hx-post`, `hx-trigger`, `hx-swap`, `hx-target`, `hx-include` are present and point to correct URLs/IDs | DOM/XPath assertions on rendered output |
| Element rendering isolation | An `Element` subclass produces deterministic output for fixed input data without external DB calls | Direct render method with injected data |
| Synthetic inlay fidelity | Inlays can resolve from injected data instead of ProcessWire page fields | Test seam in inlay data-provider or loader |
| End-to-end smoke coverage | Critical paths (`/`, a pane load, an HTMX swap, an OOB update) return 200 and expected markers | `curl` against a live ClearView server |
| Test run time & failure diff quality | Fast feedback, readable side-by-side diffs on mismatch | Custom runner output + `DOMDocument` normalization |

## 2. Behaviors to verify on every code change

High-risk behaviors that should stay green:

1. **Element rendering**
   - `Element::render()` or equivalent returns the expected tag, attributes, and body for a given shard.
   - `Element` subclasses handle missing/empty data gracefully.
   - Conditional `class`/`id`/ARIA attributes are emitted when expected.

2. **Mosaic construction**
   - Children are appended at the correct addresses.
   - Mosaic serialization/deserialization round-trips through `Input::all` without data loss.
   - Duplicate or moved children do not corrupt sibling ordering.

3. **Pane dispatch**
   - `Pane::handleCommand()` (or the planned refactor equivalent) routes `GET/POST/PUT/DELETE/CLI` to the right method.
   - `Pane::Key` validation against `Session::PaneKey` rejects forgeries and accepts valid keys.
   - Fallback to ProcessWire page-field lookup occurs when no handler matches.

4. **Facet/render pipeline**
   - `Facet` renders HTML, then dumps Mosaic/script/debug OOB output correctly.
   - HTML output contains expected wrapped fragments for HTMX-boosted `<main>` requests.

5. **ClearView boot**
   - `utility/dumpurl.php` still boots ProcessWire, resolves the correct page, and prints HTML for a known URL.
   - `ClearView::defaultMethod()` returns the documented mapping.

6. **HTMX contract**
   - Boosted requests force response content type/format to HTML.
   - Pane-load URLs include the correct Mosaic via `hx-include`.
   - `glyph/pane` stubs emit `hx-trigger="load"` only when they have no children.

## 3. Classes and components needing dedicated verification methods

The seam should be a small, public or test-only method that returns a string/array for assertion.

| Class | Verification method seam | Why |
|-------|--------------------------|-----|
| `Element` (and subclasses) | `render(Shard $shard, array $context = []): string` | HTML fragments are the primary contract; must be deterministic. |
| `Shard` | `toArray(): array` or `__toString(): string` | Carries UI state; must round-trip with Mosaic. |
| `Mosaic` | `loadMosaic(array $input): static`, `toArray(): array`, `childAt(string $address): ?Shard` | Central registry; address stability is load-bearing. |
| `Facet` | `render(Inlay $inlay, Mosaic $mosaic): string` or similar | Ties rendering to output; side effects (OOB data) need capture. |
| `Pane` (runtime) | `handleCommand(string $command, array $input): array|string` | Dispatch routing is the core request mechanic. |
| `ClearView` | `defaultMethod(string $verb): string` | Maps HTTP verbs to ClearView method names; small, stable surface. |
| `QueryParser` | `parse(string $query): array` | URL/query parsing should be deterministic and regression-safe. |
| `Crystal`/`crystals/*` | `plugAllCrystals()` + reflection checks | ProcessWire wrappers must register consistently. |
| `View` | `attach(Shard $child)`, `render(): string` | Dynamic view assembly supports regression tests on layout. |
| Inlay subclasses / data providers | `data(array $overrides = []): array` | Synthetic data injection for ProcessWire-free tests. |
| `Input`, `Session`, `User`, `Page` crystals | `all()` / getter return shapes | Request-state crystals are mocked/injected in headless tests. |

## 4. Test granularity

### Unit tests (P0)

- Target: individual methods on `Element`, `Shard`, `Mosaic`, `QueryParser`, `ClearView::defaultMethod()`.
- Run time: milliseconds each.
- Dependencies: none except the class under test; crystals are injected as arrays or minimal stubs.
- Example:
  ```php
  public function testButtonRendersHxPost(): void {
      $shard = new Shard(['type' => 'button', 'action' => '/foo/save']);
      $html = (new ButtonElement())->render($shard);
      $this->assertStringContainsString('hx-post="/foo/save"', $html);
  }
  ```

### Integration tests (P1)

- Target: `Facet`, `Pane::handleCommand()`, `View` assembly + render, inlay synthetic data path.
- Run time: tens to hundreds of milliseconds.
- Dependencies: real ClearView classes wired with a synthetic `Mosaic` and stubbed crystals.
- Example:
  ```php
  public function testPaneHtmlReachesSaveHandler(): void {
      $pane = new SavePane();
      $pane->setMosaic(Mosaic::fromArray([...]));
      $output = $pane->handleCommand('html', Input::all());
      $this->assertSame('saved', $output['status']);
  }
  ```

### End-to-end smoke tests (P2)

- Target: live HTTP paths via `curl`.
- Run time: seconds; only for critical paths.
- Dependencies: a running ClearView/ProcessWire server.
- Example: `curl -s -o /dev/null -w "%{http_code}" http://localhost/foo/bar/`
- These verify wiring, not algorithmic correctness.

### CI-ready runner (P3)

- A single CLI entry point: `utility/clearview-test.php`.
- Reuses `utility/dumpurl.php` boot path where possible; falls back to direct class tests for speed.
- Exit codes:
  - `0` = all assertions pass
  - `1` = assertion failure
  - `2` = bootstrap/config/environment failure
  - `3` = no tests matched the selector
- Output: one line per test case with ID, status, and a short diff on failure.

## 5. Coverage strategy

1. **Cover every Element subclass render path.** If an element has conditional branches, each branch gets a test.
2. **Cover every Pane/inlay dispatch combination** (`open`, `html`, `put`, `delete`) at least once via the seam.
3. **Cover Mosaic round-trips** for empty, single-child, nested, and reordered cases.
4. **Cover error/fallback paths**: missing pane key, unknown command, missing page field.
5. **Cover the HTMX contract** for `glyph/pane`, boosted `<main>`, and swap targets.
6. Aim for **line coverage ≥ 80%** in `ClearView.php`, `Mosaic.php`, `Shard.php`, `Element.php`, `Facet.php`, `Pane.php`, and `QueryParser.php`.
7. End-to-end smoke tests should exercise:
   - full page render,
   - pane load,
   - HTMX swap,
   - OOB update.

## 6. Naming conventions

### Files

- Unit tests: `tests/unit/<ClassName>Test.php`
- Integration tests: `tests/integration/<ComponentName>Test.php`
- Smoke tests: `tests/smoke/<path-description>.curl` or `.json`
- Fixtures: `tests/fixtures/<name>.php`

### Test methods

- `test<Unit>_<Condition>_<Expected>`
- Examples:
  - `testElement_Button_WithActionRendersHxPost`
  - `testMosaic_Load_FromEmptyArray_IsEmpty`
  - `testPane_HandleCommand_UnknownCommandFallsBackToPageField`

### Test case IDs (for runner)

- `<file-basename>.<class>.<method>`
- Example: `MosaicTest.Mosaic.loadMosaicPreservesOrder`

## 7. Sample test matrix

| Area | Unit | Integration | Smoke | Priority |
|------|:----:|:-----------:|:-----:|:--------:|
| Element render output | ✅ | ☐ | ☐ | P0 |
| Mosaic assembly/addressing | ✅ | ✅ | ☐ | P0 |
| View dynamic construction | ✅ | ✅ | ☐ | P0 |
| Pane dispatch routing | ✅ | ✅ | ☐ | P1 |
| Pane key validation | ✅ | ✅ | ☐ | P1 |
| Inlay synthetic data | ✅ | ✅ | ☐ | P1 |
| QueryParser parsing | ✅ | ☐ | ☐ | P1 |
| Facet OOB output | ☐ | ✅ | ☐ | P1 |
| ClearView defaultMethod | ✅ | ☐ | ☐ | P1 |
| HTMX-boosted main response | ☐ | ✅ | ✅ | P2 |
| Server curl smoke tests | ☐ | ☐ | ✅ | P2 |
| CI runner exit codes | ☐ | ✅ | ✅ | P3 |

## 8. Seams and test helpers

Add these stable seams so tests do not depend on visual HTML comparison alone:

- `Element::render(Shard $shard, array $context = []): string`
- `Shard::assertAddress(string $expected): void`
- `Mosaic::fromArray(array $data): static`
- `Mosaic::toArray(): array`
- `Mosaic::childAt(string $address): ?Shard`
- `Pane::handleCommand(string $command, array $input): array|string`
- `Inlay::withData(array $data): static` (synthetic data provider)
- `ClearView::bootForTest(array $server): static` (optional test bootstrap)

## 9. Implementation order

1. Add return-type seams to `Element`, `Shard`, `Mosaic`, `Pane`, and inlay data providers.
2. Write unit tests for `Element`, `Shard`, and `Mosaic`.
3. Add synthetic-data method to inlays; write integration tests for `View` + `Facet`.
4. Create `utility/clearview-test.php` CLI runner.
5. Add curl smoke tests against a real server.
6. Plug runner into CI with the exit-code contract.

## 10. Local validation note

If ProcessWire is not running locally, mark E2E tests as `pending local validation` and include the exact command to run them in a real environment:

```bash
php utility/clearview-test.php --smoke --base-url http://localhost/
```

Unit and integration tests should run headlessly without a live ProcessWire install.

See [framework-design](framework-design.md) for the consolidated design, acceptance criteria, and validation plan.  See [framework-architecture](framework-architecture.md) for the full runner architecture and module list.
