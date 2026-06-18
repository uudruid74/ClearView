<?php

namespace ClearView\Test;

use PHPUnit\Framework\TestCase;
use ClearView\Test\Fixture\ViewBuilder;
use ClearView\Mosaic;

/**
 * Example test verifying the testing infrastructure end-to-end:
 * bootstrap → autoloader → ViewBuilder.
 */
class ExampleTest extends TestCase
{
    // ── ViewBuilder tests ──────────────────────────────────────────

    /**
     * ViewBuilder::new() initialises Mosaic and sets Pane::name.
     */
    public function testViewBuilderSetsPaneName(): void
    {
        ViewBuilder::new('Inventory', 'Default');

        $panename = ClearView::Mosaic()->getVar('Pane::name');
        $this->assertSame('Inventory', $panename, 'Pane::name should be in Mosaic');
    }

    /**
     * ViewBuilder::new() sets Page::url so templates resolve.
     */
    public function testViewBuilderSetsPageUrl(): void
    {
        ViewBuilder::new('Demo', 'Default');

        $url = ClearView::Mosaic()->getVar('Page::url');
        $this->assertSame('/Demo/', $url, 'Page::url should match panename');
    }

    /**
     * ViewBuilder::withElement() creates a glyph Element that
     * can be retrieved via getElement().
     */
    public function testViewBuilderCreatesElement(): void
    {
        $builder = ViewBuilder::new('Demo', 'Default')
            ->withElement('submit', 'button', [
                'value'   => 'Save',
                'hx-post' => '/demo/save/',
            ]);

        $element = $builder->getElement('submit');
        $this->assertInstanceOf(\ClearView\Element::class, $element);
        $this->assertSame('Save', $element->getField('value'));
        $this->assertSame('/demo/save/', $element->getField('hx-post'));
    }

    /**
     * Missing element throws TestFixtureException.
     */
    public function testGetMissingElementThrows(): void
    {
        $builder = ViewBuilder::new('Demo', 'Default');

        $this->expectException(TestFixtureException::class);
        $builder->getElement('nonexistent');
    }

    // ── Exception tests ────────────────────────────────────────────

    /**
     * TestFixtureException is throwable.
     */
    public function testFixtureExceptionIsThrowable(): void
    {
        $this->expectException(TestFixtureException::class);
        throw new TestFixtureException('Duplicate shard id: submit');
    }

    /**
     * TestHarnessException is throwable (already exists).
     */
    public function testHarnessExceptionIsThrowable(): void
    {
        $this->expectException(TestHarnessException::class);
        throw new TestHarnessException('Server unreachable');
    }
}
