<?php

require_once 'TextNode.php';
class DoctypeNode extends TextNode {

    public function __construct($doct, $doc) {
        parent::__construct('::doctype', $doc);
        $this->text = trim($doct);
    }

    public function str($indentLevel = 0, $inline = false) {
        $t = str_repeat($this->document->indentString, $indentLevel);
        $u = $this->document->indentString.$t;
        if(strpos($this->text, "\n") !== FALSE)
            return ($inline?'':$t).'<!'."\n".$u.str_replace("\n","\n$u",$this->text)."\n".$t.'>';
        else
            return ($inline?'':$t).'<!'.$this->text.'>';
    }

    public function prettyPrint($indentLevel = 0, $inline = false) {
        $t = str_repeat($this->document->indentStringHTML, $indentLevel);
        $u = $this->document->indentStringHTML.$t;
        if(strpos($this->text, "\n") !== FALSE)
            return ($inline?'':$t).$this->wrapIn('<!','doctype')."<br/>".$u.str_replace("\n","<br/>$u",$this->wrapIn($this->text,'doctype'))."<br/>".$t.$this->wrapIn('>','doctype');
        else
            return ($inline?'':$t).$this->wrapIn('<!'.$this->text.'>','doctype');
    }
}
	
?>