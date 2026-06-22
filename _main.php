<?php
namespace ClearView;
use ClearView\Framework;
use ClearView\Exception;

try {
    (new Framework($page->template()))
    	->handleCommand();
} 
catch (\Throwable $e) {
    throw new Exception($e);
}
