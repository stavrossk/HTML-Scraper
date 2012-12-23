<?php

class Element {

    public function __get($name) {
        if(method_exists($this, ($method = 'get_'.$name)))
            return $this->$method();
        else
            return;
    }
  
    public function __set($name, $value) {
        if(method_exists($this, ($method = 'set_'.$name)))
            $this->$method($value);
    }
  
    protected $name;
    protected $ID;

    protected $attributes = Array();
    protected $childNodes = Array();
    protected $parentNode;

    protected $innerHTML;
    protected $innerText;
    protected $outerHTML;
	
	public $document;

    public function __construct($name, $doc) {
        $this->name = strtolower($name);
		$this->document = $doc;
    }

    public function __toString() {
        return $this->str();
    }
	
	public function querySelectorAll($selector) {
		return $this->document->querySelectorAll($selector, $this);
	}
	
	public function querySelector($selector, $idx = 0) {
		return $this->document->querySelector($selector, $this, $idx);
	}

    public function matchesCSSToken($token) {
        $si = array();
        $ps = array();
        $ss = array();
        
        $inQ = false;
        $inA = false;
        $inP = false;
        $quo = '';

        $t = '';

        for($i=0; $i<strlen($token); ++$i) {
            $c = $token[$i];
            if($inQ) {
                $t.=$c;
                if($c == $quo)
                    $inQ = false;
                continue;
            }
            if($inP) {
                $t.=$c;
                if($c == ')') {
                    $inP = false;
                    $ps[] = $t;
                    $t='';
                }
                continue;
            }
            if($inA) {
                $t.=$c;
                if($c == ']') {
                    $inA = false;
                    $ss[] = $t;
                    $t = '';
                }
                continue;
            }
            if(in_array($c, array('#','.',':','[')) || $i+1 == strlen($token)) {
                if($i+1 == strlen($token)) $t.=$c;
                if(strlen($t)) {
                    switch ($t[0]) {
                        case ':':
                            $ps[] = $t;
                            break;
                        case '[':
                            $ss[] = $t;
                            break;
                        default:
                            $si[] = $t;
                            break;
                    }
                }
                $t = '';
            } elseif($c == '(' && $t[0] == ':') {
                $inP = true;
            } elseif($c == '"' || $c == "'") {
                $inQ = true;
                $quo = $c;
            }
            $t .= $c;
        }

        foreach($si as $t) {
            if(!$this->matchesCSSSimple($t))
                return false;
        }
        foreach($ps as $t) {
            if(!$this->matchesCSSPseudo($t))
                return false;
        }
        foreach($ss as $t) {
            if(!$this->matchesCSSAttrib($t))
                return false;
        }
        return true;
    }

    private function matchesCSSSimple($selector) {
        switch($selector[0]) {
            case '*':
                return substr($this->name,0,2) != '::';
            case '#':
                if(!$this->hasAttribute('id'))
                    return false;
                else
                    return $this->attributes['id'] == substr($selector, 1);
            case '.':
                if (!$this->hasAttribute('class'))
                    return false;
                $classes = explode(' ', $this->attributes['class']);
                foreach ($classes as $iclass)
                    if($iclass == substr($selector, 1))
                        return true;
                return false;
            default:
                return $this->name == $selector;
        }
    }

    private function matchesCSSAttrib($selector) {
        $aN = '';
        $op = '';
        $va = '';

        $selector = substr($selector,1,-1);
        $in = 'aN';
        for($i=0; $i<strlen($selector); ++$i) {
            $c = $selector[$i];
            if(in_array($c, array('=','~','|','^','$','*')))
                $in = 'op';
            elseif($in == 'op')
                $in = 'va';
            $$in .= $c;
        }

        if(strlen($va))
            if(substr($va,0,1)==substr($va,-1) && in_array($va[0], array('"',"'")))
                $va = substr($va,1,-1);

        if(!$this->hasAttribute($aN))
            return false;

        switch ($op) {
            case '=':
                return $this->attributes[$aN] == $va;
            case '~=':
                return in_array($va, explode(' ',$this->attributes[$aN]));
            case '|=':
                return $this->attributes[$aN] == $va || substr($this->attributes[$aN],0,strpos($this->attributes[$aN],'-')) == $va;
            case '^=':
                return substr($this->attributes[$aN],0,strlen($va)) == $va;
            case '$=':
                return substr($this->attributes[$aN],-strlen($va)) == $va;
            case '*=':
                return strpos($this->attributes[$aN], $va) !== FALSE;
        }

        return true;
    }

    private function matchesCSSPseudo($selector) {
        $fn = substr($selector,1);
        $arg = null;
        if(strpos($selector,'(')) {
            $fn = substr($selector, 1, strpos($selector,'(') - 1);
            $arg = substr($selector, strpos($selector,'(') + 1, -1);
        }
        if(substr($fn,-6) == '-child')
            $nodes = $this->parentNode->getChildElementsByTagName('*');
        if(substr($fn,-8) == '-of-type')
            $nodes = $this->parentNode->getChildElementsByTagName($this->name);
        switch ($fn) {
            case 'not':
                return !$this->matchesCSSToken($arg);
            case 'target':
                if($this->hasAtrribute('id'))
                    return $this->attributes['id'] == $arg;
                else
                    return false;
            case 'root':
                return $this->parentNode->name == '::root';
            case 'empty':
                return count($this->childNodes) == 0;
            case 'contains':
                return strpos($this->innerText, $arg) !== FALSE;
            case 'only-of-type':
            case 'only-child':
                return count($nodes) == 1;
            case 'first-child':
            case 'first-of-type':
                return $nodes[0] === $this;
            case 'last-child':
            case 'last-of-type':
                return $nodes[count($nodes) - 1] === $this;
            case 'nth-last-child':
            case 'nth-last-of-type':
                $nodes = array_reverse($nodes);
            case 'nth-child':
            case 'nth-of-type':
                if($arg == 'even' || $arg == 'odd') {
                    for($i = ($arg == 'even' ? 0 : 1); $i < count($nodes); $i+=2)
                        if($nodes[$i] === $this)
                            return true;
                    return false;
                }
                if(strpos($arg,'n') === FALSE)
                    if($arg > 0 && $arg <= count($nodes))
                        return $nodes[$arg - 1] === $this;
                    else
                        return false;
                else {
                    $arg = str_replace(' ', '', strtolower($arg));
                    $coeff = intval($arg);
                    $offse = intval(substr($arg,strpos($arg,'n')+1));
                    if(substr($arg,0,2)=='-n') $coeff = -1;
                    if(substr($arg,0,1)=='n') $coeff = 1;
                    if($coeff == 0)
                        if($offse > 0 && $offse <= count($nodes))
                            return $nodes[$offse - 1] === $this;
                        else
                            return false;
                    if($coeff > 0) {
                        for($i=0; $coeff*$i+$offse <= count($nodes); ++$i)
                            if($coeff*$i+$offse > 0)
                                if($nodes[$coeff*$i+$offse - 1] === $this)
                                    return true;
                    } else {
                        for($i=0; $coeff*$i+$offse > 0; ++$i)
                            if($coeff*$i+$offse <= count($nodes))
                                if($nodes[$coeff*$i+$offse - 1] === $this)
                                    return true;
                    }
                    return false;
                }
            default:
                return true;
        }
    }

    public function hasAtrributes() {
        return count($this->attributes) > 0;
    }

    public function hasChildNodes() {
        return count($this->childNodes) > 0;
    }

    public function indexOf($node) {
        for($i = 0; $i < count($this->childNodes); ++$i)
            if($this->childNodes[$i] === $node)
                return $i;
        return count($this->childNodes);
    }

    public function insertBefore($node, $target) {
        array_splice($this->childNodes, $this->indexOf($target), 0, array($node));
    }

    public function item($i) {
        return $this->childNodes[$i];
    }

    public function removeAttribute($attr) {
        unset($this->attributes[$attr]);
    }

    public function insertAfter($node, $target) {
        array_splice($this->childNodes, $this->indexOf($target) + 1, 0, array($node));
    }

    public function get_ID() {
        return $this->getAttribute('id');
    }

    public function getAttribute($attr) {
        if(isset($this->attributes[$attr]))
            return $this->attributes[$attr];
        else
            return null;
    }

    public function removeChild($node) {
        for($i=0; $i<count($this->childNodes); ++$i) {
            if($this->childNodes[$i] === $node) {
                unset($this->childNodes[$i]);
                $this->childNodes = array_values($this->childNodes);
                break;
            }
        }
    }

    public function replaceChild($old, $new) {
        $this->insertBefore($new, $old);
        $this->removeChild($old);
    }

    public function hasAttribute($attr) {
        return isset($this->attributes[$attr]);
    }

    public function get_attributes() {
        return $this->attributes;
    }

    public function setAttribute($attr, $value) {
        $this->attributes[$attr] = $value;
    }

    public function appendChild($node) {
        $this->childNodes[] = $node;
        $node->parentNode = $this;
    }

    public function get_childNodes() {
        return $this->childNodes;
    }

    public function get_parentNode() {
        return $this->parentNode;
    }

    public function get_name() {
        return $this->name;
    }

    public function set_innerHTML($ih) {
        $this->childNodes = array();
        $this->document->loadInto($ih, $this);
    }

    public function set_innerText($t) {
        foreach($this->childNodes as $cn)
            if($cn->name == '::text') {
                $cn->text = $t;
                return;
            }
        $this->appendChild(new TextNode($t));
    }

    public function get_innerHTML() {
        $s = '';
        foreach($this->childNodes as $node)
            $s .= $node->str() ."\n";
        return $s;
    }

    public function get_innerText() {
        $s = '';
        foreach($this->childNodes as $node) {
            if(strlen($s))
                if(substr($s,-1)!=' ')
                    $s = "$s ";
            if($node->name == '::text')
                $s .= $node->str();
            else
                $s .= $node->get_innerText();
        }
        return $s;
    }

    public function getElementByTagName($tag, $index = 0, $includeShadows = true) {
        $els = $this->getElementsByTagName($tag, $includeShadows);
        if($index < count($els))
            return $els[$index];
        else
            return null;
    }

    public function getElementsByTagName($tag, $includeShadows = true) {
        $els = array();
        foreach ($this->childNodes as $childNode) {
            if ($childNode->name == $tag)
                $els[] = $childNode;
            else
                if ($tag == '*')
                    if($this->document->includeShadows && $includeShadows)
                        $els[] = $childNode;
                    else
                        if(substr($childNode->name,0,2) != '::')
                            $els[] = $childNode;
            
            if (!empty($childNode->childNodes)) $els = array_merge($els, $childNode->getElementsByTagName($tag));
        }
        return $els;
    }

    public function queryCSSToken($token) {
        $els = array();
        foreach ($this->childNodes as $childNode) {
            if ($childNode->matchesCSSToken($token))
                $els[] = $childNode;            
            if (!empty($childNode->childNodes)) $els = array_merge($els, $childNode->queryCSSToken($token));
        }
        return $els;
    }

    public function getChildElementsByTagName($tag) {
        $els = array();
        foreach ($this->childNodes as $childNode)
            if ($childNode->name == $tag)
                $els[] = $childNode;
            else
                if ($tag == '*')
                    if(substr($childNode->name,0,2) != '::')
                            $els[] = $childNode;
        return $els;
    }

    public function getElementsByClassName($class) {
        $els = array();
        $classes = explode(' ', $class);
        foreach ($this->childNodes as $childNode) {
            if (!empty($childNode->attributes['class'])) {
                $iclasses = explode(' ', $childNode->attributes['class']);
                foreach ($classes as $iclass)
                    if (in_array($iclass, $iclasses)) {
                        $els[] = $childNode;
                        break;
                    }
            }
            if (!empty($childNode->childNodes)) $els = array_merge($els, $childNode->getElementsByClassName($class));
        }
        return $els;
    }

    public function isType($t) {
        return $this->document->isType($t, $this->name);
    }

    public function get_outerHTML() {
        return $this->str(0, true);
    }

    public function str($indentLevel = 0, $inline = false) {
        $t = str_repeat($this->document->indentString, $indentLevel);
        $s = '';

        if(!$inline) $s.= $t;
        $s.= '<'.$this->name;

        foreach ($this->attributes as $key => $value) {
            $value = htmlspecialchars($value);
            $s.= " $key=\"$value\"";
        }

        if($this->isType('empty')) return $s.'/>';
        $s.= '>';

        if(count($this->childNodes)) {
            $hasBlock = false;
            foreach ($this->childNodes as $childNode)
                if($childNode->isType('block'))
                    $hasBlock = true;

            if($this->isType('block') || $hasBlock) {
                $s.= "\n";
                foreach ($this->childNodes as $childNode)
                    $s .= $childNode->str($indentLevel + 1, false) . "\n";
                $s.= $t;
            } else {
                foreach ($this->childNodes as $childNode)
                    $s .= $childNode->str($indentLevel + 1, true);
            }
        }

        $s.= '</'.$this->name.'>';

        return $s;
    }

    protected function wrapIn($str, $cla) {
        return '<span class="'.$this->document->classPrefix.$cla.'">'.htmlspecialchars($str).'</span>';
    }

    public function prettyPrint($indentLevel = 0, $inline = false) {
        $t = str_repeat($this->document->indentStringHTML, $indentLevel);
        $s = '';

        if(!$inline) $s.= $t;
        $s.= $this->wrapIn('<','html');
        $s.= $this->wrapIn($this->name,'tag');

        foreach ($this->attributes as $key => $value) {
            $value = htmlspecialchars($value);
            $s.= ' ';
            $s.= $this->wrapIn($key,'attrib');
            $s.= $this->wrapIn('=', 'html');
            $s.= $this->wrapIn('"'.$value.'"', 'value');
        }

        if($this->isType('empty')) return $s.$this->wrapIn('/>','html');
        $s.= $this->wrapIn('>','html');

        if(count($this->childNodes)) {
            $hasBlock = false;
            foreach ($this->childNodes as $childNode)
                if($childNode->isType('block'))
                    $hasBlock = true;

            if($this->isType('block') || $hasBlock) {
                $s.= "<br/>";
                foreach ($this->childNodes as $childNode)
                    $s .= $childNode->prettyPrint($indentLevel + 1, false) . "<br/>";
                $s.= $t;
            } else {
                foreach ($this->childNodes as $childNode)
                    $s .= $childNode->prettyPrint($indentLevel + 1, true);
            }
        }

        $s.= $this->wrapIn('</','html');
        $s.= $this->wrapIn($this->name,'tag');
        $s.= $this->wrapIn('>','html');

        return $s;
    }
}

?>