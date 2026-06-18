<?php
namespace ClearView;
use ClearView\Pane;
use ClearView\Exception;

try {
    (new Pane($page->template()))
    	->handleCommand();
} 
catch (\Throwable $e) {
    throw new Exception($e);
}
