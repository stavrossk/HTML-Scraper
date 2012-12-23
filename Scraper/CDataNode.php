<?php

require_once 'TextNode.php';
class CDataNode extends TextNode {

	protected $start = '<![CDATA[';
	protected $end = ']]>';

    public function __construct($cdat, $doc) {
        parent::__construct('::cdata', $doc);
        $this->text = $doc->fixEncoding($cdat);
    }
	
    public function isType($t) {
		if($t == 'block')
			return strpos($this->text, "\n") !== FALSE;
        return parent::isType($t, $this->name);
    }

    public function str($indentLevel = 0, $inline = false) {
        $t = str_repeat($this->document->indentString, $indentLevel);
		$t2 = $t.$this->document->indentString;
        if(strpos($this->text, "\n") !== FALSE) {
            return ($inline?'':$t).$this->start."\n$t2".str_replace("\n","\n$t2",$this->text)."\n$t".$this->end;
        } else
            return ($inline?'':$t).$this->start.' '.$this->text.' '.$this->end;
    }

    public function prettyPrint($indentLevel = 0, $inline = false) {
        $t = str_repeat($this->document->indentStringHTML, $indentLevel);
		$t2 = $t.$this->document->indentStringHTML;
        if(strpos($this->text, "\n") !== FALSE) {
            return ($inline?'':$t).$this->wrapIn($this->start,substr($this->name,2))."<br/>$t2".str_replace("\n","<br/>$t2",$this->wrapIn($this->text,substr($this->name,2)))."<br/>$t".$this->wrapIn($this->end,substr($this->name,2));
        } else
            return ($inline?'':$t).$this->wrapIn($this->start.' '.$this->text.' '.$this->end,substr($this->name,2));
    }
}

?>