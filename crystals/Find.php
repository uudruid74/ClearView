<?php

namespace ClearView;

use ClearView\Crystal;
use ProcessWire;

/**
 * Crystal for finding pages in ProcessWire.
 *
 * Wraps ProcessWire’s pages object to provide search functionality for pages using selectors. Returns Page
 * Crystals for single pages or PageArrays for multiple pages.
 *
 * @see \ClearView\Crystal
 */
class Find extends Crystal
{
    /**
     * Initializes the Find Crystal with a ProcessWire pages object.
     *
     * Called during system initialization or when searching for pages. Uses ProcessWire’s `pages()` function
     * if no object is provided.
     *
     * Why: Sets up page search functionality within ClearView’s data model.
     *
     * @param mixed $pwObject The ProcessWire pages object (defaults to Pages via `pages()`).
     */
    public function __construct($pwObject = null,$panename=null,$inlayname=null)
    {
        parent::__construct($pwObject ?? \ProcessWire\pages(),$panename,$inlayname);
    }

    /**
     * Finds a single page by selector.
     *
     * Used to retrieve a single page matching a ProcessWire selector, wrapped as a Page Crystal.
     *
     * Why: Enables page searches via Mosaic::getVar() with selectors.
     *
     * @param string $selector The ProcessWire selector (e.g., 'template=home').
     * @return Page|null A Page Crystal if found, null otherwise.
     */
    public function getVar($selector = null)
    {
        Exception::debug("Find $selector");
        $page = $this->data[Config::PAGE_PWOBJECT]->findOne($selector);
        return $page ? new Page($page) : null;
    }

    /**
     * Finds multiple pages by selector.
     *
     * Used to retrieve multiple pages matching a ProcessWire selector, returned as a PageArray.
     * FIXME: Should return results as a Shard with primaryField set to 'contents'.
     *      Contents will be an array of Page Shards
     *      Every Page shard should override 'contents' to be the children of the Page
     *
     * Why: Enables batch page searches via Mosaic::getVars().
     *
     * @param string $selector The ProcessWire selector (e.g., 'template=blog').
     * @return \ProcessWire\PageArray The matching pages.
     */
    public function getVars($selector): array
    {
        return $this[Config::PAGE_PWOBJECT]->find($selector); // Returns PageArray directly
    }
}
