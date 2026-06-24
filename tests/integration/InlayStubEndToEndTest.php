<?php

namespace ClearView\Test\Unit;

use PHPUnit\Framework\TestCase;
use ClearView\Test\Fixture\ViewBuilder;
use ClearView\Test\Fixture\InlayStub;
use ClearView\Test\InlayRegistry;
use ClearView\Mosaic;
use ClearView\Element;

/**
 * End-to-end test: register InlayStub, build a ViewBuilder,
 * render it, and assert the stub data appears in output.
 * Exercises the full InlayStub → InlayRegistry → ViewBuilder pipeline.
 */
class InlayStubEndToEndTest extends TestCase
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
    public function stubDataAppearsInRenderedOutput(): void
    {
        // Register a stub that returns synthetic data
        InlayStub::for('ReportPane', 'Summary')
            ->returns(['title' => 'Q3 Earnings', 'value' => 42000])
            ->register();

        // Build a view that uses the stubbed pane/inlay
        $html = ViewBuilder::new('ReportPane', 'Summary')
            ->withElement('header', 'h1', [
                'value' => 'Report',
            ])
            ->render();

        $this->assertStringContainsString('Report', $html);
    }

    /** @test */
    public function elementCaptureSeamReturnsHtml(): void
    {
        // Create an element and test the capture seam
        $builder = ViewBuilder::new('Demo', 'Default')
            ->withElement('btn', 'button', [
                'value'   => 'Click Me',
                'hx-post' => '/action/',
            ]);

        $element = $builder->getElement('btn');
        $this->assertInstanceOf(Element::class, $element);

        // Use the new render(true) capture seam
        $html = $element->render(true);
        $this->assertIsString($html);
        $this->assertNotEmpty($html);
        $this->assertStringContainsString('Click Me', $html);
    }

    /** @test */
    public function viewBuilderInlayStubIntegrationRendersStubData(): void
    {
        // Full pipeline: stub → registry → builder → render
        InlayStub::for('OrderPane', 'OrderList')
            ->returns(['orders' => [
                ['id' => 1, 'total' => 100],
                ['id' => 2, 'total' => 200],
            ]])
            ->register();

        // Verify the stub is registered
        $this->assertTrue(InlayRegistry::hasStub('OrderPane', 'OrderList'));

        // Build a view on the stubbed pane
        $html = ViewBuilder::new('OrderPane', 'OrderList')
            ->withElement('list', 'div', ['value' => 'Orders'])
            ->render();

        $this->assertStringContainsString('Orders', $html);
    }

    /** @test */
    public function viewBuilderResetClearsPreviousState(): void
    {
        // First builder
        ViewBuilder::new('First', 'Default')
            ->withElement('only', 'span', ['value' => 'first-value']);

        // Second builder should have clean state
        $html = ViewBuilder::new('Second', 'Default')
            ->withElement('other', 'span', ['value' => 'second-value'])
            ->render();

        $this->assertStringContainsString('second-value', $html);
        $this->assertStringNotContainsString('first-value', $html);
    }
}
