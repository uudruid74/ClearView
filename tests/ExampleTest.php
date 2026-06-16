<?php

namespace ClearView\Test;

use PHPUnit\Framework\TestCase;
use ClearView\Test\Fixture\ViewBuilder;
use ClearView\Test\PaneKeyHelper;
use ClearView\Mosaic;

/**
 * Example test verifying the testing infrastructure end-to-end:
 * bootstrap → autoloader → ViewBuilder → PaneKeyHelper.
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

        $panename = Mosaic::getVar('Pane::name');
        $this->assertSame('Inventory', $panename, 'Pane::name should be in Mosaic');
    }

    /**
     * ViewBuilder::new() sets Page::url so templates resolve.
     */
    public function testViewBuilderSetsPageUrl(): void
    {
        ViewBuilder::new('Demo', 'Default');

        $url = Mosaic::getVar('Page::url');
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

    // ── PaneKeyHelper tests ────────────────────────────────────────

    /**
     * PaneKeyHelper::seed() produces a matching token pair
     * accessible via Mosaic::getVar().
     */
    public function testPaneKeyHelperSeedsMatchingTokens(): void
    {
        ViewBuilder::new('KeyTest', 'Default');

        $token = PaneKeyHelper::seed('KeyTest');

        $this->assertNotEmpty($token);
        $this->assertStringStartsWith('tok_', $token);

        $paneKey    = Mosaic::getVar('Pane::Key');
        $sessionKey = Mosaic::getVar('Session::PaneKey');

        $this->assertSame($token, $paneKey, 'Pane::Key should match');
        $this->assertSame($token, $sessionKey, 'Session::PaneKey should match');
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
