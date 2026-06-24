<?php

namespace ClearView\Test\Unit;

use PHPUnit\Framework\TestCase;
use ClearView\Test\Fixture\ViewBuilder;
use ClearView\Element;

/**
 * Unit test for the Element::render($capture) seam.
 * Verifies that elements can be rendered to a string for assertion
 * without side-effecting output.
 */
class ElementCaptureTest extends TestCase
{
    /** @test */
    public function buttonRenderCaptureReturnsHtmlString(): void
    {
        $builder = ViewBuilder::new('Demo', 'Default')
            ->withElement('submit', 'button', [
                'value'   => 'Save',
                'hx-post' => '/demo/save/',
                'hx-swap' => 'outerHTML',
            ]);

        $element = $builder->getElement('submit');
        $this->assertInstanceOf(Element::class, $element);

        $html = $element->render(true);

        $this->assertStringContainsString('<button', $html);
        $this->assertStringContainsString('Save', $html);
        $this->assertStringContainsString('hx-post', $html);
        $this->assertStringContainsString('outerHTML', $html);
    }

    /** @test */
    public function spanRenderCaptureContainsExplicitAttributes(): void
    {
        $builder = ViewBuilder::new('Demo', 'Default')
            ->withElement('nav', 'span', [
                'value'   => 'Navigate',
                'hx-get'  => '/nav/',
                'hx-swap' => 'innerHTML',
            ]);

        $element = $builder->getElement('nav');
        $html = $element->render(true);

        $this->assertStringContainsString('Navigate', $html);
        $this->assertStringContainsString('hx-get', $html);
        $this->assertStringContainsString('/nav/', $html);
    }

    /** @test */
    public function inputElementCapturesValue(): void
    {
        $builder = ViewBuilder::new('Form', 'Default')
            ->withElement('username', 'input', [
                'value'       => 'neo',
                'placeholder' => 'Enter username',
                'type'        => 'text',
            ]);

        $element = $builder->getElement('username');
        $html = $element->render(true);

        $this->assertStringContainsString('input', $html);
        $this->assertStringContainsString('neo', $html);
    }
}
