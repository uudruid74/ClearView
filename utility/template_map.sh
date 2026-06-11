#!/usr/bin/php
<?php namespace ProcessWire;
include("/var/www/devel.virtuallyreal.games/htdocs/index.php"); // bootstrap ProcessWire

function listPage($page, $level = 0) {
  echo str_repeat("   ", $level) . $page->title . " [" . $page->template . "]\n";
  foreach($page->children as $child) {
    listPage($child, $level+1);
  }
}
listPage($pages->get("/")); // start at homepage

