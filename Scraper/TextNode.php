<?php

require_once 'Element.php';
class TextNode extends Element {
    public $text;

    public function __construct($txt, $doc) {
        parent::__construct('::text', $doc);
        $this->text = preg_replace('/\\s+/ms', ' ', $doc->fixEncoding($txt));
    }

    public function str($indentLevel = 0, $inline = false) {
        return ($inline?'':str_repeat($this->document->indentString, $indentLevel)).$this->text;
    }

    public function prettyPrint($indentLevel = 0, $inline = false) {
        return ($inline?'':str_repeat($this->document->indentStringHTML, $indentLevel)).$this->wrapIn($this->text,'text');
    }    
}

?>