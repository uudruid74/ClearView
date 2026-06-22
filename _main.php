<?php
namespace ClearView;
use ClearView\Runtime;
use ClearView\Exception;

try {
    (new Runtime($page->template()))
    	->handleCommand();
} 
catch (\Throwable $e) {
    throw new Exception($e);
}
