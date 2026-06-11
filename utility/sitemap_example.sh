#!/usr/bin/php
<?php namespace ProcessWire;

include("/var/www/devel.virtuallyreal.games/htdocs/index.php"); // bootstrap ProcessWire

// Path to save the sitemap
$sitemapFile = "/var/www/devel.virtuallyreal.games/htdocs/sitemap.xml";

// Open XML structure
$xml = new \DOMDocument("1.0", "UTF-8");
$xml->formatOutput = true;

$urlset = $xml->createElement("urlset");
$urlset->setAttribute("xmlns", "http://www.sitemaps.org/schemas/sitemap/0.9");
$urlset->setAttribute("xmlns:image", "http://www.google.com/schemas/sitemap-image/1.1");

// Recursive function to add pages + images
function addPageToSitemap(\DOMDocument $xml, \DOMElement $urlset, $page) {
    // Skip hidden/unpublished pages
    if(!$page->viewable()) return;

    $url = $xml->createElement("url");

    // Page URL
    $loc = $xml->createElement("loc", htmlspecialchars($page->httpUrl));
    $url->appendChild($loc);

    // Last modified
    if($page->modified) {
        $lastmod = $xml->createElement("lastmod", date("Y-m-d", $page->modified));
        $url->appendChild($lastmod);
    }

    // Optional SEO hints
    $url->appendChild($xml->createElement("changefreq", "weekly"));
    $url->appendChild($xml->createElement("priority", $page->id == 1 ? "1.0" : "0.5"));

    // Images (loop through all Pageimage fields)
    foreach($page->getFields() as $field) {
        if($field->type instanceof \ProcessWire\FieldtypeImage) {
            foreach($page->$field as $image) {
                /** @var \ProcessWire\Pageimage $image */
                $imgNode = $xml->createElement("image:image");

                $imgLoc = $xml->createElement("image:loc", htmlspecialchars($image->httpUrl));
                $imgNode->appendChild($imgLoc);

                if($image->description) {
                    $imgCaption = $xml->createElement("image:caption", htmlspecialchars($image->description));
                    $imgNode->appendChild($imgCaption);
                }

                $url->appendChild($imgNode);
            }
        }
    }

    $urlset->appendChild($url);

    // Recurse into children
    foreach($page->children as $child) {
        addPageToSitemap($xml, $urlset, $child);
    }
}

// Start at homepage
addPageToSitemap($xml, $urlset, $pages->get("/"));

// Finalize XML
$xml->appendChild($urlset);
$xml->save($sitemapFile);

echo "Sitemap with images written to $sitemapFile\n";
