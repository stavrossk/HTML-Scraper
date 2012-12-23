<?php

require_once 'CDataNode.php';
class CommentNode extends CDataNode {

    public function __construct($cmnt, $doc) {
        parent::__construct($cmnt, $doc);
		$this->name = '::comment';
		$this->start = '<!--';
		$this->end = '-->';
    }
}

?>