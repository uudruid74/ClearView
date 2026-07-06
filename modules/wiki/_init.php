<?php

/**
 * Wiki module initialization.
 *
 * Runs AFTER all crystals are loaded (Crystal::loadAll() loads _init.php
 * files last).  Two jobs:
 *
 * 1. Swap WikiPage crystal into the Page inlay slot so that
 *    {{Page::body}} resolves through the wiki markdown reader.
 *
 * 2. Return wiki configuration: baseWikiUrl (URL prefix for wiki pages)
 *    and baseWikiPath (filesystem path to the vault wiki directory).
 */

use ClearView\Mosaic;

// ── Page crystal override ────────────────────────────────────
// Find the WikiPage crystal (auto-loaded as 'ClearView-WikiPage')
// and re-register it as 'ClearView-Page', replacing the root Page.

$wikiPage = Mosaic::index('ClearView', 'WikiPage');
if ($wikiPage !== null) {
    // Re-key as Page — address determines the inlay-id key
    $wikiPage->setField('id', 'Page');
    $wikiPage->setField('name', 'Page');
    // Point address at the Page slot (public property on Shard)
    $wikiPage->address = 'ClearView-Page';
    // addShard overwrites at the address since it's already set
    Mosaic::addShard($wikiPage);
}

// ── Config ───────────────────────────────────────────────────

return [
    'baseWikiUrl'  => '/wiki/',
    'baseWikiPath' => '/home/ekl/vault/wiki/entities/clearview/',
];
