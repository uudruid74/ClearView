<?php

namespace ClearView\Test;

use ClearView\Runtime;

/**
 * Base class for dynamically-generated stub panes.
 *
 * When InlayRegistry returns a stub, it creates an anonymous subclass
 * of StubPane that bakes in the synthetic data.  This base class
 * provides the render() method that emits the data as an HTML
 * fragment with a `data-stub` marker for test assertions.
 */
class StubPane extends Runtime
{
    /** @var array<string,callable> Shared callable store for dynamically-generated subclasses */
    public static array $callables = [];

    /** @var string Pane name (set by constructor) */
    protected string $panename;

    /** @var string Inlay name (set by constructor) */
    protected string $inlayname;

    /**
     * @param string $panename
     * @param string $inlayname
     */
    public function __construct(string $panename, string $inlayname)
    {
        $this->panename  = $panename;
        $this->inlayname = $inlayname;
    }

    /**
     * Subclasses override this to return the stub payload.
     */
    protected function getStubData(): array
    {
        return [];
    }

    /**
     * Render the stub data as an HTML fragment.
     */
    public function render(): void
    {
        $data = $this->getStubData();
        echo '<div class="stub-pane" data-stub="' . htmlspecialchars($this->panename . '/' . $this->inlayname) . '">';
        echo $this->renderArray($data);
        echo '</div>';
    }

    /**
     * Recursively render an array as nested <dl>/<ul> elements.
     */
    private function renderArray(array $data, bool $isList = false): string
    {
        $out = '';
        $isSequential = array_keys($data) === range(0, count($data) - 1);

        if ($isSequential) {
            foreach ($data as $item) {
                $out .= '<div class="stub-item">';
                $out .= is_array($item) ? $this->renderArray($item, true) : htmlspecialchars((string) $item);
                $out .= '</div>';
            }
        } else {
            foreach ($data as $key => $value) {
                $out .= '<div class="stub-entry">';
                $out .= '<span class="stub-key">' . htmlspecialchars((string) $key) . '</span>';
                $out .= '<span class="stub-value">';
                $out .= is_array($value) ? $this->renderArray($value) : htmlspecialchars((string) $value);
                $out .= '</span>';
                $out .= '</div>';
            }
        }

        return $out;
    }
}
