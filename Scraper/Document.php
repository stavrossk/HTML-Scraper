<?php

require_once 'Element.php';
require_once 'CDataNode.php';
require_once 'TextNode.php';
require_once 'CommentNode.php';
require_once 'DoctypeNode.php';
require_once 'XMLHeadNode.php';

class Document {

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
  
    public $indentString = '  ';
    public $indentStringHTML = '&nbsp;&nbsp;';
    public $includeShadows = true;
    public $classPrefix = 'syntax_';

    private $documentElement;

    public function isType($type, $name) {
		if($type=='block')
			if($name == '::root')
				return true;
			else
				return substr($name,0,2) != '::';
        return false;
    }

    public function parseCSSS($csss) {
        $csss = preg_replace('/\\s+/ms', ' ', trim($csss));
        $csss = preg_replace('/\\s([\\+>\\~<&])\\s/ms', '$1', $csss);
        $s = '';
        $p = array();
        for($i=0; $i<strlen($csss); ++$i) {
            $c=$csss[$i];
            if(in_array($c, array('~','>',' ','+','<','&'))) {
                $p[]=$s;
                $s='';
            }
            $s.=$c;
        }
        $p[] = $s;
        return $p;
    }

    public function querySelector($csss, $rootNode=false, $i=0) {
		if(!$rootNode) $rootNode = $this->documentElement;
        $els = $this->querySelectorAll($csss, $rootNode);
        if($i < count($els))
            return $els[$i];
        else
            return null;
    }

    public function querySelectorAll($csss, $rootNode=false) {
        $css = $this->parseCSSS($csss);
        $els = array();

		if(!$rootNode) $rootNode = $this->documentElement;
        $els[0] = $rootNode->queryCSSToken($css[0]);
        $nodes = $els[0];

        for($i=1; $i<count($css); ++$i) {

            $nodes = array();
            $elems = $els[$i-1];
            $sel = substr($css[$i],1);
            
            for($j=0; $j<count($elems); ++$j) {
            
                $el = $elems[$j];
            
                switch ($css[$i][0]) {
                    case '>':
                        foreach($el->childNodes as $cn)
                            if($cn->matchesCSSToken($sel))
                                $nodes[]=$cn;
                        break;
            
                    case '~':
                        for($k = $j+1; $k<count($elems); ++$k)
                            if($elems->parentNode != $el->parentnode)
                                break;
                            else
                                if($elems[$k]->matchesCSSToken($sel))
                                    $nodes[] = $elems[$k];
                        break;
            
                    case '+':
                        if($j+1<count($elems))
                            if($elems[$j+1]->parentNode === $el->parentNode)
                                if($elems[$j+1]->matchesCSSToken($sel))
                                    $nodes[]=$elems[$j+1];
                        break;

                    case '<':
                        if($el->parentNode->matchesCSSToken($sel))
                            if(!in_array($el->parentNode, $nodes, true))
                                $nodes[]=$el->parentNode;
                        break;

                    case '&':
                        $cn = $el;
                        while($cn->parentNode != null) {
                            if($cn->parentNode->matchesCSSToken($sel))
                                if(!in_array($cn->parentNode, $nodes, true))
                                    $nodes[]=$cn->parentNode;
                            $cn = $cn->parentNode;
                        }
                        break;
            
                    case ' ':
                    default:
                        $newNodes = $el->queryCSSToken($sel);
                        foreach($newNodes as $n)
                            if(!in_array($n, $nodes, true))
                                $nodes[] = $n;
                        break;
                }
            }
            $els[$i] = $nodes;
        }
        return $nodes;
    }

    public function load($doc) {
        $this->loadInto($doc, $this->documentElement);
    }

    public function loadInto($doc, $element) {
        $doc = str_replace("\r\n","\n",$doc);
        $doc = preg_replace('/<\\s+/','<',$doc);
        $doc = preg_replace('/<\\/\\s+/','</',$doc);
        $doc = preg_replace('/\\s+>/','>',$doc);
        $tag = '';
        $val = '';
        $attr = '';
        $cdata = '';
        $nothing = '';
        $comment = '';
        $doctype = '';
        $xtag = '';
        $xval = '';
        $xattr = '';
        $in = 'nothing';
        $quoteChar = '';
        $closing = false;
        $skip = 0;
        $thisEl = null;
        for ($i = 0;$i < strlen($doc);++$i) {
            if ($skip) {
                $skip--;
                continue;
            }
            $c = $doc[$i];
            if ($in == 'nothing' && $c == '<') $closing = ($doc[$i + 1] == '/');
            if ($in == 'nothing' && $c == '<') {
                if(strlen(trim($nothing)))
                    $element->appendChild(new TextNode($nothing, $this));
                $nothing = '';
			    if ($doc[$i + 1] == '!') {
                    if (($doc[$i + 2] . $doc[$i + 3]) == '--') $in = 'comment';
                    else $in = 'doctype';
				} elseif($doc[$i + 1] == '?') {
					$in = 'xtag';
                    $xtag = '';
                    $xval = '';
                    $xattr = '';
					$i++;
					continue;
                } else {
                    $in = 'tag';
                    $tag = '';
                    $val = '';
                    $attr = '';
                    if ($closing) $skip++;
                    continue;
                }
            }
            if ($in == 'val' && ($c == $quoteChar || (($c == '>' || trim($c) == '') && $quoteChar == '*'))) {
                $in = 'attr';
                $thisEl->setAttribute(trim($attr), $this->fixEncoding($val));
                $val = '';
                $attr = '';
                if ($c != '>') continue;
                else $in = 'tag';
            }
            if ($in == 'xval' && ($c == $quoteChar || (($c == '>' || trim($c) == '') && $quoteChar == '*'))) {
                $in = 'xattr';
                $thisEl->setAttribute(trim($xattr), $this->fixEncoding($xval));
                $xval = '';
                $xattr = '';
                if ($c != '>') continue;
                else $in = 'xtag';
            }
            if ($in == 'tag' && trim($c) == '') {
                if ($element->name == $tag && $this->isType('autoclose', $tag)) $element = $element->parentNode;
                $thisEl = new Element($tag, $this);
                $element->appendChild($thisEl);
                $in = 'attr';
                $val = '';
                $attr = '';
                continue;
            }
            if ($in == 'xtag' && trim($c) == '') {
                $thisEl = new XMLHeadNode($xtag, $this);
                $element->appendChild($thisEl);
                $in = 'xattr';
                $xval = '';
                $xattr = '';
                continue;
            }
            if ($in == 'doctype' && $c == '>') {
                $element->appendChild(new DoctypeNode(trim(substr($doctype, 2)), $this));
                $in = 'nothing';
                continue;
            }
            if ($in == 'comment' && ($doc[$i - 2] . $doc[$i - 1] . $c) == '-->') {
                if(strlen(trim($comment)))
                    $element->appendChild(new CommentNode(substr($comment, 4, -2), $this));
                $comment = '';
                $in = 'nothing';
                continue;
            }
            if ($in == 'attr' && $c == '=') {
                $in = 'val';
                $val = '';
                if ($doc[$i + 1] == '"' || $doc[$i + 1] == "'") $quoteChar = $doc[$i + 1];
                else $quoteChar = '*';
                if ($quoteChar != '*') $skip++;
                continue;
            }
            if ($in == 'attr' && ($c == ' ' || $c == "\t" || $c == "\n")) {
            	if(strlen(trim($attr)))
	                $thisEl->setAttribute(trim($attr), true);
                $attr = '';
                continue;
            }
            if ($in == 'xattr' && $c == '=') {
                $in = 'xval';
                $xval = '';
                if ($doc[$i + 1] == '"' || $doc[$i + 1] == "'") $quoteChar = $doc[$i + 1];
                else $quoteChar = '*';
                if ($quoteChar != '*') $skip++;
                continue;
            }
            if ($in == 'attr' && ($c == '>' || $c == '/')) {
                $in = 'tag';
                if (trim($attr) != '') $thisEl->setAttribute(trim($attr), true);
            }
            if ($in == 'xattr' && ($c == '>' || $c == '?')) {
                $in = 'xtag';
                if (trim($xattr) != '') $thisEl->setAttribute(trim($xattr), true);
            }
            if ($in == 'xtag' && $c == '?' && $doc[$i+1] == '>') {
                if ($thisEl == null) {
                    $thisEl = new XMLHeadNode($xtag, $this);
                    $element->appendChild($thisEl);
                }
            }
            if ($in == 'tag' && $c == '>') {
                if (!$closing) {
                    if ($element->name == $tag && $this->isType('autoclose', $tag)) $element = $element->parentNode;
                    if ($thisEl == null) {
                        $thisEl = new Element($tag, $this);
                        $element->appendChild($thisEl);
                    }
                }
            }
            if (($in == 'tag' || $in == 'attr') && $c == '>') {
                if (!empty($thisEl) && $thisEl != null) {
                    if ($this->isType('cdata', $thisEl->name)) {
                        $k = stripos($doc, '</' . $thisEl->name . '>', $i);
                        $skip = $k - $i - 1;
                        $cdata = substr($doc, $i + 1, $skip);
                        if(strlen(trim($cdata)))
                            $thisEl->appendChild(new CDataNode($cdata, $this));
                    }
                }
                if ($closing) {
                    $levelUps = 1;
                    if (!empty($tag)) {
                        for ($el = $element;$el->name != $tag;$el = $el->parentNode) {
                            if ($el->parentNode == null || $el == null) {
                                $levelUps = - 1;
                                break;
                            } else {
                                $levelUps++;
                            }
                        }
                    }
                    for (;$levelUps > 0;--$levelUps) $element = $element->parentNode;
                } else {
                    if (!$this->isType('empty', $thisEl->name)) $element = $thisEl;
                }
                $thisEl = null;
                $closing = false;
                $in = 'nothing';
                $c = '';
            }
            if (($in == 'xtag' || $in == 'xattr') && $c == '?' && $doc[$i+1] == '>') {
			    $thisEl = null;
                $closing = false;
                $in = 'nothing';
                $c = '';
				$i++;
            }
            if ($in != 'val' && $in != 'nothing' && $in != 'doctype') $c = strtolower($c);
            $$in.= $c;
        }
    }

    public function getElementByID($id, $root = null) {
        if ($root == null) return $this->getElementByID($id, $this->documentElement);
        if ($root->ID == $id) return $root;
        foreach ($root->childNodes as $childNode) {
            if ($childNode->ID == $id) return $childNode;
            $x = $this->getElementById($id, $childNode);
            if ($x) return $x;
        }
        return null;
    }

    public function get_documentElement() {
        return $this->documentElement;
    }

    public function createElement($elname) {
        return new Element($elname, $this);
    }

    public function __construct() {
        $this->documentElement = new Element('::root', $this);
    }

    public function str() {
        return $this->documentElement->innerHTML;
    }

    public function fixEncoding($txt) {
        $txt = mb_convert_encoding($txt, 'HTML-ENTITIES', 'UTF-8');
        $txt = mb_convert_encoding($txt, 'UTF-8', 'HTML-ENTITIES');        
        return $txt;
    }
}
?>