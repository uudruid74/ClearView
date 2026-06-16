<?php

use PHPUnit\Framework\TestCase;
use ClearView\Test\Fixture\InlayStub;
use ClearView\Test\InlayRegistry;
use ClearView\Test\StubPane;
use ClearView\Test\TestFixtureException;
use ClearView\ClearView;

/**
 * Acceptance criteria from design doc:
 *   A PHPUnit test can register a stub for OrderPane/OrderList,
 *   create a ViewBuilder for that pane/inlay, and assert the
 *   rendered output contains the stub data.
 *
 * Since ViewBuilder is implemented in a separate task (t_3f36d1d2),
 * this test verifies the core InlayStub/InlayRegistry seam:
 *   - loadInlay() returns the stub class when a stub is registered.
 *   - StubPane::render() emits the registered data.
 */
class InlayStubTest extends TestCase
{
    protected function setUp(): void
    {
        InlayRegistry::reset();
    }

    protected function tearDown(): void
    {
        InlayRegistry::reset();
    }

    /** @test */
    public function loadInlayReturnsStubClassWhenRegistered(): void
    {
        InlayStub::for('OrderPane', 'OrderList')
            ->returns(['orders' => [['id' => 42]]])
            ->register();

        // ClearView::inTesting() should be true in CLI (PHPUnit).
        $this->assertTrue(ClearView::inTesting(), 'PHPUnit must run in CLI mode');

        $class = ClearView::loadInlay('OrderPane', 'OrderList');
        $this->assertSame(StubPane::class, $class);
    }

    /** @test */
    public function loadInlayFallsThroughWhenNoStubRegistered(): void
    {
        // No stub registered — should throw (no real inlay file for BogusPane).
        $this->assertTrue(ClearView::inTesting());

        $this->expectException(\ClearView\Exception::class);
        ClearView::loadInlay('BogusPane', 'BogusInlay');
    }

    /** @test */
    public function stubPaneRenderEmitsStubData(): void
    {
        InlayStub::for('OrderPane', 'OrderList')
            ->returns(['orders' => [['id' => 42]]])
            ->register();

        $class = ClearView::loadInlay('OrderPane', 'OrderList');

        // Capture output of stub render().
        ob_start();
        $pane = new $class('OrderPane', 'OrderList');
        $pane->render();
        $html = ob_get_clean();

        $this->assertStringContainsString('42', $html);
        $this->assertStringContainsString('data-stub', $html);
    }

    /** @test */
    public function returnsCallableStubRendersResult(): void
    {
        InlayStub::for('OrderPane', 'OrderList')
            ->returnsCallable(function (string $panename, string $inlayname, array $context): array {
                return ['panename' => $panename, 'total' => 99];
            })
            ->register();

        $class = ClearView::loadInlay('OrderPane', 'OrderList');

        ob_start();
        $pane = new $class('OrderPane', 'OrderList');
        $pane->render();
        $html = ob_get_clean();

        $this->assertStringContainsString('99', $html);
        $this->assertStringContainsString('OrderPane', $html);
    }

    /** @test */
    public function registerWithoutPayloadThrows(): void
    {
        $this->expectException(TestFixtureException::class);
        $this->expectExceptionMessage('call returns() or returnsCallable() before register()');

        InlayStub::for('FooPane', 'BarInlay')->register();
    }

    /** @test */
    public function hasStubReturnsFalseWhenNotRegistered(): void
    {
        $this->assertFalse(InlayRegistry::hasStub('NoPane', 'NoInlay'));
    }

    /** @test */
    public function resetClearsAllStubs(): void
    {
        InlayStub::for('APane', 'AnInlay')
            ->returns(['x' => 1])
            ->register();

        $this->assertTrue(InlayRegistry::hasStub('APane', 'AnInlay'));

        InlayRegistry::reset();

        $this->assertFalse(InlayRegistry::hasStub('APane', 'AnInlay'));
    }
}
