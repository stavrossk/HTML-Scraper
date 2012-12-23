<?php

require_once 'URL.php';
require_once 'Document.php';
require_once 'HTMLDoc.php';

class Scraper {

	private $cookieTemp;
	private $cURL;
	private $lastError;
	private $lastResponse;
	private $currLocation;
	private $info;
	private $lastAttemptedAddress;

	function __construct($ckFile=null) {
		if(isset($ckFile) && !empty($ckFile))
			$this->cookieTemp = $ckFile;
		else
			$this->cookieTemp = tempnam(sys_get_temp_dir(),'Scraper');

		$this->cURL = curl_init();
		$this->opt(CURLOPT_RETURNTRANSFER, 1);
		$this->opt(CURLOPT_FOLLOWLOCATION, 1);
		$this->opt(CURLOPT_AUTOREFERER, TRUE);
		$this->opt(CURLOPT_FOLLOWLOCATION, TRUE);
		$this->opt(CURLOPT_COOKIEFILE, $this->cookieTemp);
		$this->opt(CURLOPT_COOKIEJAR, $this->cookieTemp);
		$this->opt(CURLOPT_MAXREDIRS, 10);
		$this->opt(CURLOPT_FRESH_CONNECT, TRUE);
		$this->opt(CURLOPT_FORBID_REUSE, TRUE);
		$this->opt(CURLOPT_USERAGENT, "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_7_3) AppleWebKit/534.55.3 (KHTML, like Gecko) Version/5.1.5 Safari/534.55.3");
	}
	
	function error() {
		return $this->lastError;
	}
	
	function failingURL() {
		return $this->lastAttemptedAddress;
	}

	private function makeRequest($url, $method = 'GET') {
		$target = $url->relativeTo($this->currLocation);
		$curl = '';
		
		if($method == 'POST') {
			$curl = $target->str(true);
			$this->opt(CURLOPT_POST, true);
			$this->opt(CURLOPT_POSTFIELDS, $target->queryString());
		} else {
			$curl = $target->str();
			$this->opt(CURLOPT_HTTPGET, true);
		}
		if($this->currLocation)
			$this->opt(CURLOPT_REFERER, $this->currLocation->str());

		$this->opt(CURLOPT_URL, $curl);
		$result = curl_exec($this->cURL);
		if($result === false) {
			$this->lastAttemptedAddress = $curl;
			$this->lastError = curl_error($this->cURL);
			return false;
		}
		
		$this->lastAttemptedAddress = false;
		$this->lastResponse = $result;
		$this->info = curl_getinfo($this->cURL);
		$this->currLocation = new URL($this->info['url']);
		return true;
	}
	
	private function opt($flag, $val) {
		curl_setopt($this->cURL, $flag, $val);
	}
	
	public function GET($url) {
		return $this->makeRequest($url, 'GET');
	}
	
	public function POST($url) {
		return $this->makeRequest($url, 'POST');
	}

	function getHTML() {
		$H = new HTMLDoc();
		$H->load($this->lastResponse);
		return $H;
	}

	function getXML() {
		$H = new Document();
		$H->load($this->lastResponse);
		return $H;
	}

	function getXMLObject() {
		$H = new Document();
		$H->load($this->lastResponse);
		return $this->BuildXMLObject($H->documentElement);
	}
	
	function getJSON() {
		return json_decode($this->lastResponse);
	}
	
	private function BuildXMLObject($node) {
		$attrs = $node->attributes;
		$childs = $node->childNodes;
		
		if(count($attrs)==0 && count($childs)==1 && $childs[0]->name=='::text')
			return $childs[0]->str();
		
		$obj = array();
		foreach($attrs as $k=>$v)
			$obj["@$k"] = $v;
		foreach($childs as $cnode) {
			$nm = $cnode->name;
			if($nm == ':?xml' || $nm == '::comment')
				continue;
			$xmlo = $this->BuildXMLObject($cnode);
			if(isset($obj[$nm]))
				if(is_array($obj[$nm]))
					$obj[$nm][] = $xmlo;
				else
					$obj[$nm] = array($obj[$nm], $xmlo);
			else
				$obj[$nm] = $xmlo;
		}
		
		return (object) $obj;
	}
	
	function submitForm($formName, $data) {
		$html = $this->getHTML();
        $k = $html->documentElement->getElementsByTagName('form');
		$form = null;
        foreach($k as $f)
        	if($f->attributes['id'] == $formName || $f->attributes['name'] == $formName)
				$form = $f;
		if(!$form) { 
			$this->lastError = "Cannot find a form with id/name '$formName' in the page";
			return false;
		}
		return $this->submitFormByElement($form, $data);
	}

	function submitFormByElement($form, $data) {
		$inputs = $form->getElementsByTagName('input');
		foreach($inputs as $input) {
			
			$k = $input->attributes['name'];
			if(!$k) $k = $input->attributes['id'];
			if(!$k) continue;
			
			if(isset($data[$k])) continue;
			
			$v = $input->attributes['value'];
			if(!$v) $v = '';
			
			if($input->attributes['type'] == 'radio') {
				$radios = $input->document->querySelectorAll('input[type="radio"][name="'.$k.'"]');
				if(count($radios) > 1)
					$selRadio = $input->document->querySelector('input[type="radio"][name="'.$k.'"][checked]');
				else					
					$selRadio = $input->parentNode->querySelector('input[type="radio"][selected]');
				if(!$selRadio) continue;
				$v = $selRadio->attributes['value'];
				if(!$v) $v=1;
			}

			if($input->attributes['type'] == 'checkbox') {
				if(!$input->attributes['checked']) continue;
				if($v=='') $v=1;
			}
			
			$data[$k] = $v;
		}
		
		$selects = $form->getElementsByTagName('select');
		foreach($selects as $select) {
			$k = $select->attributes['name'];
			if(!$k) $k = $select->attributes['id'];
			if(!$k) continue;
			
			if(isset($data[$k])) continue;

			$selOption = $select->querySelector('option[selected]');
			$v = $selOption->attribute['value'];
			if(!$v) $v = $selOption->innerText;
			
			if($selOption) $data[$k] = $v;
		}
		
		$to = $form->attributes['action'];
		$meth = $form->attributes['method'];
		if(!$meth) $meth='GET';
		
		$url = new URL($to, true);
		$url->data = $data;
		
		if($meth=='GET')
			return $this->GET($url);
		else
			return $this->POST($url);
		
	}
	
	function getRaw() {
		return $lastResponse;
	}
	
	function currentURL() {
		return $this->currLocation;
	}

	function __destruct() {
		curl_close($this->cURL);
	}

}
?>
