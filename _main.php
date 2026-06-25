<?php
namespace ClearView;
use ClearView\Framework;
use ClearView\Exception;

try {
    Framework::loadInlay()->handleCommand();
} 
catch (\Throwable $e) {
    throw new Exception($e);
}
