<?php

require_once 'Element.php';
class XMLHeadNode extends Element {

    public function __construct($name, $doc) {
        parent::__construct(":?$name", $doc);
    }

    public function str($indentLevel = 0, $inline = false) {
        $t = str_repeat($this->document->indentString, $indentLevel);
        $s = '';

        if(!$inline) $s.= $t;
        $s.= '<?'.substr($this->name,2);

        foreach ($this->attributes as $key => $value) {
            $value = htmlspecialchars($value);
            $s.= " $key=\"$value\"";
        }

        $s.= '?>';
        return $s;
    }

    public function prettyPrint($indentLevel = 0, $inline = false) {
        $t = str_repeat($this->document->indentStringHTML, $indentLevel);
        $s = '';

        if(!$inline) $s.= $t;
        $s.= $this->wrapIn('<?'.substr($this->name,2),'xml');

        foreach ($this->attributes as $key => $value) {
            $value = htmlspecialchars($value);
            $s.= ' ';
            $s.= $this->wrapIn($key,'attrib');
            $s.= $this->wrapIn('=', 'html');
            $s.= $this->wrapIn('"'.$value.'"', 'value');
        }

        $s.= $this->wrapIn('?>','xml');
		return $s;
    }
}

?>