<?php

namespace ClearView\Test;

use PHPUnit\Framework\TestCase;
use ClearView\Test\Fixture\ViewBuilder;
use ClearView\Test\Fixture\TestFixtureException;

/**
 * Acceptance tests for ViewBuilder.
 * Verifies that ViewBuilder can assemble a view and render HTML
 * with expected attributes and text.
 */
class ViewBuilderTest extends TestCase
{
    // ── Core acceptance test ──────────────────────────────────────────────

    public function testButtonEmitsExpectedAttributesAndText(): void
    {
        $html = ViewBuilder::new('Demo', 'Default')
            ->withElement('submit', 'button', [
                'value'   => 'Save',
            ])
            ->render('submit');

        $this->assertStringContainsString('Save', $html);
        $this->assertStringContainsString('<button', $html);
        $this->assertStringContainsString('hx-post', $html);
        $this->assertStringContainsString('hx-swap', $html);
        $this->assertStringContainsString('type="submit"', $html);
    }

    // ── hx-* attributes via span (doesn't auto-override URLs) ─────────────

    public function testSpanPreservesExplicitHxAttributes(): void
    {
        $html = ViewBuilder::new('Demo', 'Default')
            ->withElement('nav', 'span', [
                'value'   => 'Go',
                'hx-post' => '/demo/save/',
                'hx-swap' => 'outerHTML',
            ])
            ->render('nav');

        $this->assertStringContainsString('Go', $html);
        $this->assertStringContainsString('hx-post="/demo/save/"', $html);
        $this->assertStringContainsString('outerHTML', $html);
    }

    // ── Duplicate ID ──────────────────────────────────────────────────────

    public function testDuplicateIdThrowsException(): void
    {
        $this->expectException(TestFixtureException::class);
        $this->expectExceptionMessage("Duplicate element ID 'dup'");

        ViewBuilder::new()
            ->withElement('dup', 'span', ['value' => 'first'])
            ->withElement('dup', 'span', ['value' => 'second']);
    }

    // ── Missing shard on render ───────────────────────────────────────────

    public function testRenderMissingShardThrowsException(): void
    {
        $this->expectException(TestFixtureException::class);
        $this->expectExceptionMessage("Named shard 'nope'");

        ViewBuilder::new()->render('nope');
    }

    // ── Render all shards ─────────────────────────────────────────────────

    public function testRenderAllReturnsAllRegisteredElements(): void
    {
        $html = ViewBuilder::new('Test', 'Default')
            ->withElement('a', 'span', ['value' => 'Alpha'])
            ->withElement('b', 'span', ['value' => 'Beta'])
            ->render();

        $this->assertStringContainsString('Alpha', $html);
        $this->assertStringContainsString('Beta', $html);
    }

    // ── withShard creates a plain Shard ───────────────────────────────────

    public function testWithShardCreatesPlainShard(): void
    {
        $html = ViewBuilder::new()
            ->withShard('plain', [
                'text'  => 'Hello Shard',
                '__pF'  => 'text',
            ])
            ->render('plain');

        $this->assertStringContainsString('Hello Shard', $html);
    }

    // ── withChild attaches children ───────────────────────────────────────

    public function testWithChildAttachesChildToParent(): void
    {
        $builder = ViewBuilder::new()
            ->withElement('container', 'span', [
                'value' => 'Wrapper',
            ])
            ->withElement('item', 'span', [
                'value' => 'Child!',
            ]);

        // Should not throw — both parent and child exist
        $builder->withChild('container', 'item');
        $this->assertTrue(true);
    }

    // ── withChild with missing parent throws ──────────────────────────────

    public function testWithChildMissingParentThrows(): void
    {
        $this->expectException(TestFixtureException::class);
        $this->expectExceptionMessage("Parent shard 'ghost'");

        ViewBuilder::new()
            ->withElement('kid', 'span', ['value' => 'orphan'])
            ->withChild('ghost', 'kid');
    }

    // ── Singleton reset between tests ─────────────────────────────────────

    public function testResetPreventsCrossTestPollution(): void
    {
        // First builder registers 'x'
        ViewBuilder::new('Test1')->withElement('x', 'span', ['value' => 'A']);

        // Second builder should have a clean slate — 'x' must be re-registerable
        $html = ViewBuilder::new('Test2')
            ->withElement('x', 'span', ['value' => 'B'])
            ->render('x');

        $this->assertStringContainsString('B', $html);
        $this->assertStringNotContainsString('A', $html);
    }
}
