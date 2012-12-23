<?php
	
require_once 'Document.php';
	
class HTMLDoc extends Document {
    
	private static $tags = array(
    	'empty' => array('br', 'hr', 'meta', 'link', 'base', 'img', 'embed', 'param', 'area', 'col', 'input'),
		'cdata' => array('script', 'style'),
		'autoclose' => array('li', 'p'),
		'block' => array('::root', 'html', 'head', 'script', 'style', 'body', 'header', 'nav', 'aside', 'article', 'div', 'form', 'select', 'table', 'tr', 'ul', 'ol', 'dl', 'br', 'textarea', 'noscript')
    );

    public function isType($type, $name) {
        return in_array($name, self::$tags[$type]);
    }

}
	
?>