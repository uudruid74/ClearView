<?php

namespace ClearView\Test;

use ClearView\Pane;

/**
 * Synthetic Pane that reads its data from InlayRegistry.
 *
 * When ClearView::loadInlay() returns this class name, ClearView::init()
 * instantiates it.  The constructor stores the panename/inlayname and
 * render()/open() replay the registered stub data as HTML output.
 */
class StubPane extends Pane
{
    /** @var string The pane name passed at construction. */
    private string $panename;

    /** @var string The inlay name passed at construction. */
    private string $inlayname;

    /**
     * @param string $panename Pane name.
     * @param string $inlayname Inlay name.
     */
    public function __construct($panename, $inlayname = 'Default')
    {
        $this->panename  = $panename;
        $this->inlayname = $inlayname;
        parent::__construct($panename, $inlayname);
    }

    /**
     * Resolve the stub data, invoking a callable if needed.
     *
     * @return mixed Resolved array or Shard.
     */
    private function resolveData(): mixed
    {
        $data = InlayRegistry::getStub($this->panename, $this->inlayname);
        if ($data === null) {
            return null;
        }
        if ($data instanceof \Closure) {
            return ($data)();
        }
        return $data;
    }

    /**
     * Render the stub data as HTML.
     *
     * If the stub payload is an array, it is JSON-encoded inside a
     * <div data-stub="..."> so tests can assert against it.  If it is a
     * Shard, its getHtml() is called.
     */
    public function render(): void
    {
        $data = $this->resolveData();
        if ($data !== null) {
            if (is_array($data)) {
                echo '<div data-stub="' . htmlspecialchars(json_encode($data), ENT_QUOTES, 'UTF-8') . '"></div>';
            } else {
                // Shard or object with getHtml()
                if (method_exists($data, 'getHtml')) {
                    echo $data->getHtml();
                } else {
                    echo (string)$data;
                }
            }
        }
    }

    /**
     * Delegate open() to render() so dialog-style tests see stub data.
     */
    public function open(): void
    {
        $this->render();
    }
}
