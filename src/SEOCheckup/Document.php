<?php

namespace SEOCheckup;

use DOMDocument;
use DOMNodeList;
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

        $dom  = new DOMDocument();
        $html = trim($this->html);

        $previous = libxml_use_internal_errors(true);

        try {
            // HTML5 tags such as <main> and <section> are unknown to this
            // parser and raise "Tag invalid"; that is why errors are muted.
            // Replacing the parser is a deferred item, not a change for 1.0.0.
            if ($html !== '') {
                // DOMDocument::loadHTML() defaults to ISO-8859-1 when no charset
                // is declared, corrupting UTF-8. Encode non-ASCII to numeric entities.
                $html = mb_encode_numericentity($html, [0x80, 0x10FFFF, 0, 0x1FFFFF], 'UTF-8');
                $dom->loadHTML($html);
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        return $this->dom = $dom;
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
