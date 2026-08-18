<?php

namespace SEOCheckup;

use DOMDocument;
use DOMNodeList;
use DOMProcessingInstruction;
use DOMText;
use DOMXPath;

final class Document
{
    private ?DOMDocument $dom = null;

    private ?DOMXPath $xpath = null;

    public function __construct(private readonly string $html)
    {
    }

    public function dom(): DOMDocument
    {
        if ($this->dom instanceof DOMDocument) {
            return $this->dom;
        }

        $dom = new DOMDocument();

        // A UTF-8 byte-order mark is not whitespace, so trim() alone leaves
        // it in front of <!DOCTYPE>, where the parser reads it as body text
        // and collapses <head> into <body>.
        $html = trim($this->html);

        if (str_starts_with($html, self::UTF8_BOM)) {
            $html = substr($html, strlen(self::UTF8_BOM));
        }

        $previous = libxml_use_internal_errors(true);

        try {
            // HTML5 tags such as <main> and <section> are unknown to this
            // parser and raise "Tag invalid"; that is why errors are muted.
            // Replacing the parser is a deferred item, not a change for 1.0.0.
            if ($html !== '') {
                // The prolog tells libxml the bytes are UTF-8, which is what
                // makes the raw-text regions entityEncode() leaves alone come
                // through intact instead of being read as ISO-8859-1.
                $dom->loadHTML('<?xml encoding="UTF-8"?>' . self::entityEncode($html));
                self::removeProlog($dom);
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        return $this->dom = $dom;
    }

    private const UTF8_BOM = "\xEF\xBB\xBF";

    /**
     * DOMDocument::loadHTML() defaults to ISO-8859-1 when no charset is
     * declared, corrupting UTF-8, so non-ASCII is turned into numeric
     * entities first. But libxml never decodes entities inside <script>,
     * <style> or comments — the tokenizer's raw-text regions — so encoding
     * those would leave literal "&#NNN;" in inlineCss(), inline scripts and
     * cache()'s comment output. They are left untouched: their bytes are
     * already UTF-8 and the parser copies raw text through verbatim.
     * The regions end where the HTML tokenizer ends them: at the first
     * closing tag / "-->" after the opener.
     */
    private static function entityEncode(string $html): string
    {
        $parts = preg_split(
            '/(<script\b[^>]*>.*?<\/script\s*>|<style\b[^>]*>.*?<\/style\s*>|<!--.*?-->)/is',
            $html,
            -1,
            PREG_SPLIT_DELIM_CAPTURE
        );

        if ($parts === false) {
            $parts = [$html];
        }

        // Even offsets are the markup between raw-text regions; odd offsets
        // are the captured regions themselves.
        foreach ($parts as $i => $part) {
            if ($i % 2 === 0) {
                $parts[$i] = mb_encode_numericentity($part, [0x80, 0x10FFFF, 0, 0x1FFFFF], 'UTF-8');
            }
        }

        return implode('', $parts);
    }

    /**
     * The encoding prolog is a parser hint, not part of the page: it would
     * otherwise show up as a DOMProcessingInstruction child of the document
     * and in saveHTML() output.
     */
    private static function removeProlog(DOMDocument $dom): void
    {
        foreach ($dom->childNodes as $node) {
            if ($node instanceof DOMProcessingInstruction && $node->target === 'xml') {
                $dom->removeChild($node);

                return;
            }
        }
    }

    public function xpath(): DOMXPath
    {
        return $this->xpath ??= new DOMXPath($this->dom());
    }

    /**
     * @return DOMNodeList<\DOMElement>
     */
    public function tags(string $name): DOMNodeList
    {
        /** @var DOMNodeList<\DOMElement> */
        return $this->dom()->getElementsByTagName($name);
    }

    public function text(): string
    {
        $nodes = $this->xpath()->query(
            '//text()[not(ancestor::script) and not(ancestor::style)]'
        );

        if ($nodes === false) {
            return '';
        }

        $text = '';

        foreach ($nodes as $node) {
            // The expression selects text nodes; the instanceof keeps the
            // DOMNameSpaceNode arm of DOMXPath::query()'s union out of the way.
            if (!$node instanceof DOMText) {
                continue;
            }

            $text .= $node->textContent . ' ';
        }

        return $text;
    }
}
