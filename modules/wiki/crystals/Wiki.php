<?php

namespace ClearView;

/**
 * MarkdownToShard — recursive descent markdown parser.
 *
 * Converts markdown text directly to Shard-compatible arrays,
 * skipping the HTML intermediate step entirely.  No jsonmangler,
 * no fromhtml(), no ProcessWire dependency.
 *
 * Output shape:
 *   {glyph: 'h1', value: 'Title'}
 *   {glyph: 'p', children: [{glyph: 'text', value: 'Hello'}]}
 *   {glyph: 'ul', children: [{glyph: 'li', children: [...]}]}
 *
 * Wikilinks [[path|label]] → {glyph: 'a', href: 'path', value: 'label'}
 */
class MarkdownToShard
{
    /** @var array<string> Source lines, skipping frontmatter */
    private array $lines;

    /** @var int Current line index */
    private int $pos = 0;

    /** @var string|null Relative base for [[wikilinks]] */
    private ?string $wikiBase = null;

    /**
     * Parse a markdown string into a Shard-compatible tree.
     *
     * @param string $markdown  Raw markdown text
     * @param string|null $wikiBase  Base path for wikilink resolution
     * @return array{children: array}  Shard-compatible tree
     */
    public static function parse(string $markdown, ?string $wikiBase = null): array
    {
        $parser = new self($markdown, $wikiBase);
        return $parser->parseDocument();
    }

    private function __construct(string $markdown, ?string $wikiBase)
    {
        $this->wikiBase = $wikiBase;
        $this->lines = $this->splitAndStrip($markdown);
        $this->pos = 0;
    }

    // ── Splitting ──────────────────────────────────────────────

    /**
     * Split markdown into lines, strip YAML frontmatter.
     */
    private function splitAndStrip(string $markdown): array
    {
        $lines = explode("\n", $markdown);

        // Skip YAML frontmatter (starts and ends with ---)
        if (isset($lines[0]) && trim($lines[0]) === '---') {
            $end = 1;
            while ($end < count($lines) && trim($lines[$end]) !== '---') {
                $end++;
            }
            // Remove lines 0 through $end (inclusive)
            array_splice($lines, 0, $end + 1);
        }

        return array_values($lines);
    }

    // ── Document parsing ───────────────────────────────────────

    /**
     * Parse the full document — top-level block elements.
     */
    private function parseDocument(): array
    {
        $children = [];

        while ($this->pos < count($this->lines)) {
            $line = $this->lines[$this->pos];

            // Blank lines: skip
            if (trim($line) === '') {
                $this->pos++;
                continue;
            }

            $block = $this->parseBlock();
            if ($block !== null) {
                $children[] = $block;
            }
        }

        return ['children' => $children];
    }

    // ── Block parsing ──────────────────────────────────────────

    /**
     * Dispatch to the right block parser based on the current line.
     */
    private function parseBlock(): ?array
    {
        $line = $this->lines[$this->pos];

        // Heading: # through ###### at start of line
        if (preg_match('/^(#{1,6})\s+(.+)/', $line, $m)) {
            $this->pos++;
            $level = strlen($m[1]);
            return ['glyph' => "h{$level}", 'value' => $this->parseInline($m[2])];
        }

        // Fenced code block: ``` or ~~~
        if (preg_match('/^(```|~~~)/', $line)) {
            return $this->parseFencedCode();
        }

        // Unordered list: - or * followed by space
        if (preg_match('/^[\-\*]\s/', $line)) {
            return $this->parseList('ul', '/^[\-\*]\s/');
        }

        // Ordered list: 1. 2. etc.
        if (preg_match('/^\d+\.\s/', $line)) {
            return $this->parseList('ol', '/^\d+\.\s/');
        }

        // Horizontal rule: ---, ***, ___
        if (preg_match('/^(\-{3,}|\*{3,}|_{3,})\s*$/', $line)) {
            $this->pos++;
            return ['glyph' => 'hr'];
        }

        // Blockquote: >
        if (str_starts_with($line, '>')) {
            return $this->parseBlockquote();
        }

        // Paragraph: anything else — consume until blank line
        return $this->parseParagraph();
    }

    /**
     * Parse a paragraph (lines until blank line or block element).
     */
    private function parseParagraph(): array
    {
        $textLines = [];

        while ($this->pos < count($this->lines)) {
            $line = $this->lines[$this->pos];
            if (trim($line) === '') break;
            // Stop at next block element
            if (preg_match('/^(#{1,6}\s|```|~~~|[\-\*]\s|\d+\.\s|>\s|\-{3,}|\*{3,}|_{3,})/', $line)) break;
            $textLines[] = $line;
            $this->pos++;
        }

        $text = trim(implode("\n", $textLines));
        if ($text === '') return null;

        return ['glyph' => 'p', 'children' => $this->parseInlineToChildren($text)];
    }

    // ── List parsing ───────────────────────────────────────────

    /**
     * Parse a list (ordered or unordered).
     */
    private function parseList(string $glyph, string $itemPattern): array
    {
        $items = [];

        while ($this->pos < count($this->lines)) {
            $line = $this->lines[$this->pos];
            if (trim($line) === '') {
                $this->pos++;
                continue;
            }

            if (!preg_match($itemPattern, $line)) break;

            // Strip the list marker
            $content = preg_replace($itemPattern, '', $line, 1);
            $items[] = ['glyph' => 'li', 'children' => $this->parseInlineToChildren($content)];
            $this->pos++;
        }

        return ['glyph' => $glyph, 'children' => $items];
    }

    // ── Fenced code block ──────────────────────────────────────

    /**
     * Parse a fenced code block (``` or ~~~).
     */
    private function parseFencedCode(): array
    {
        $line = $this->lines[$this->pos];
        $fence = str_starts_with($line, '```') ? '```' : '~~~';
        $this->pos++;

        $codeLines = [];
        while ($this->pos < count($this->lines)) {
            $line = $this->lines[$this->pos];
            if (str_starts_with($line, $fence)) {
                $this->pos++;
                break;
            }
            $codeLines[] = $line;
            $this->pos++;
        }

        return [
            'glyph' => 'pre',
            'children' => [[
                'glyph' => 'code',
                'value' => implode("\n", $codeLines),
            ]],
        ];
    }

    // ── Blockquote ─────────────────────────────────────────────

    /**
     * Parse a blockquote (> lines).
     */
    private function parseBlockquote(): array
    {
        $lines = [];

        while ($this->pos < count($this->lines)) {
            $line = $this->lines[$this->pos];
            if (trim($line) === '') {
                $this->pos++;
                break;
            }
            if (!str_starts_with($line, '>')) break;

            // Strip the '>' prefix (and optional following space)
            $content = preg_replace('/^>\s?/', '', $line);
            $lines[] = $content;
            $this->pos++;
        }

        return [
            'glyph' => 'blockquote',
            'children' => $this->parseInlineToChildren(implode("\n", $lines)),
        ];
    }

    // ── Inline parsing ─────────────────────────────────────────

    /**
     * Parse inline markdown into a plain string or array of children.
     * Returns a string for pure text, array for mixed content.
     */
    private function parseInline(string $text)
    {
        $children = $this->parseInlineToChildren($text);
        // Single text node → return just the string
        if (count($children) === 1 && ($children[0]['glyph'] ?? '') === 'text') {
            return $children[0]['value'];
        }
        return $children;
    }

    /**
     * Parse inline markdown into an array of Shard-compatible child nodes.
     *
     * Handles: **bold**, *italic*, `code`, [links](url), [[wikilinks]].
     */
    private function parseInlineToChildren(string $text): array
    {
        $children = [];
        $len = strlen($text);
        $i = 0;

        while ($i < $len) {
            // Escape: backslash escapes the next character
            if ($text[$i] === '\\' && $i + 1 < $len) {
                $children[] = ['glyph' => 'text', 'value' => $text[$i + 1]];
                $i += 2;
                continue;
            }

            // Bold: **text**
            if ($this->matchAt($text, $i, '**')) {
                $end = $this->findClosing($text, $i + 2, '**');
                if ($end !== false) {
                    $inner = substr($text, $i + 2, $end - $i - 2);
                    $children[] = ['glyph' => 'strong', 'value' => $this->parseInline($inner)];
                    $i = $end + 2;
                    continue;
                }
            }

            // Italic: *text* (but not **)
            if ($text[$i] === '*' && ($i + 1 >= $len || $text[$i + 1] !== '*')) {
                $end = $this->findClosing($text, $i + 1, '*');
                if ($end !== false) {
                    $inner = substr($text, $i + 1, $end - $i - 1);
                    $children[] = ['glyph' => 'em', 'value' => $this->parseInline($inner)];
                    $i = $end + 1;
                    continue;
                }
            }

            // Inline code: `text`
            if ($text[$i] === '`') {
                $end = $this->findClosing($text, $i + 1, '`');
                if ($end !== false) {
                    $inner = substr($text, $i + 1, $end - $i - 1);
                    $children[] = ['glyph' => 'code', 'value' => $inner];
                    $i = $end + 1;
                    continue;
                }
            }

            // Wikilink: [[path|label]] or [[path]]
            if ($this->matchAt($text, $i, '[[')) {
                $end = $this->findClosing($text, $i + 2, ']]');
                if ($end !== false) {
                    $inner = substr($text, $i + 2, $end - $i - 2);
                    $children[] = $this->parseWikilink($inner);
                    $i = $end + 2;
                    continue;
                }
            }

            // Regular link: [text](url)
            if ($text[$i] === '[' && ($i === 0 || $text[$i - 1] !== '[')) {
                $closeBracket = $this->findClosing($text, $i + 1, ']');
                if ($closeBracket !== false && $closeBracket + 1 < $len && $text[$closeBracket + 1] === '(') {
                    $closeParen = $this->findClosing($text, $closeBracket + 2, ')');
                    if ($closeParen !== false) {
                        $linkText = substr($text, $i + 1, $closeBracket - $i - 1);
                        $linkUrl = substr($text, $closeBracket + 2, $closeParen - $closeBracket - 2);
                        $children[] = ['glyph' => 'a', 'href' => $linkUrl, 'value' => $this->parseInline($linkText)];
                        $i = $closeParen + 1;
                        continue;
                    }
                }
            }

            // Plain text: accumulate until next special character
            $start = $i;
            while ($i < $len &&
                   $text[$i] !== '\\' &&
                   $text[$i] !== '*' &&
                   $text[$i] !== '`' &&
                   $text[$i] !== '[') {
                $i++;
            }
            if ($i > $start) {
                $children[] = ['glyph' => 'text', 'value' => substr($text, $start, $i - $start)];
            }
        }

        return $children;
    }

    // ── Wikilink resolution ────────────────────────────────────

    /**
     * Parse a [[path|label]] or [[path]] wikilink into an anchor Shard.
     */
    private function parseWikilink(string $inner): array
    {
        if (str_contains($inner, '|')) {
            [$path, $label] = explode('|', $inner, 2);
        } else {
            $path = $inner;
            $label = $inner;
        }

        $path = trim($path);
        $label = trim($label);

        // Resolve relative path
        $href = $path;
        if ($this->wikiBase !== null && !str_starts_with($path, '/')) {
            $href = $this->wikiBase . $path;
        }

        return ['glyph' => 'a', 'href' => $href, 'value' => $label];
    }

    // ── Helpers ────────────────────────────────────────────────

    /**
     * Check if string $text matches $needle at position $i.
     */
    private function matchAt(string $text, int $i, string $needle): bool
    {
        return substr($text, $i, strlen($needle)) === $needle;
    }

    /**
     * Find the closing delimiter, respecting backslash escapes.
     * Returns the position of the first character of the delimiter, or false.
     */
    private function findClosing(string $text, int $start, string $delim): int|false
    {
        $len = strlen($text);
        $dlen = strlen($delim);
        for ($i = $start; $i <= $len - $dlen; $i++) {
            if ($text[$i] === '\\') {
                $i++; // skip escaped character
                continue;
            }
            if (substr($text, $i, $dlen) === $delim) {
                return $i;
            }
        }
        return false;
    }
}
