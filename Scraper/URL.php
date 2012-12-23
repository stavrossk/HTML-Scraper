<?php

class URL {
    private $fields = array('scheme', 'domain', 'port', 'path', 'data');
    private $defaults = array('scheme' => 'http', 'port' => array('http' => 80, 'https' => 443), 'path' => '/', 'data' => null);
    public $data = Array();
    public $domain;
    public $path;
    public $port;
    public $scheme;

    public function queryString() {
        return $this->buildQueryString($this->data);
    }

    public function buildQueryString($data) {
        $qStr = '';
        if (!empty($data)) {
            foreach ($data as $k => $v)
                $qStr.= urlencode($k) . '=' . urlencode($v) . '&';
            $qStr = substr($qStr, 0, -1);
        }
        return $qStr;
    }

    public function isRelative() {
        return empty($this->domain);
    }

    public function append($url) {
        if (!$url->isRelative()) return;
        $this->__construct($this->combine($this, $url));
    }

    public function combine($url1, $url2) {
        if (!$url2->isRelative()) return $url2;
        $tUrl = new URL($url1);
        if (substr($url2->path, 0, 1) == '/') $tUrl->path = $url2->path;
        else {
            if (substr($tUrl->path, -1) == '/') $tUrl->path.= $url2->path;
            else $tUrl->path.= '/' . $url2->path;
        }
        $tUrl->data = $url2->data;
        return $tUrl;
    }

    public function relativeTo($url) {
        if ($this->isRelative()) return $this->combine($url, $this);
        else return $this;
    }

    public function str($noQuery = false) {
        return (!$this->isRelative() ? $this->scheme . '://' . $this->domain . ($this->port != $this->defaults['port'][$this->scheme] ? ':' . $this->port : '') : '') . $this->path . (strlen($this->queryString()) == 0 || $noQuery ? '' : '?' . $this->queryString());
    }

    public function __construct($url, $relative = false) {
        if (is_string($url)) {
            if (strpos($url, '://')) {
                $prop = 'scheme';
				$relative = false;
            } else {
				if($relative)
					$prop = 'path';
				else
					$prop = 'domain';
            }
            $this->queryString = '';
            for ($i = 0;$i < strlen($url);++$i) {
                if (substr($url, $i, 3) == '://') {
                    $i+= 2;
                    $prop = 'domain';
                    continue;
                }
                if ($url[$i] == ':' && $prop == 'domain') {
                    $prop = 'port';
                    continue;
                }
                if ($url[$i] == '/' && ($prop == 'domain' || $prop == 'port')) {
                    $prop = 'path';
                }
                if ($url[$i] == '?' && $prop == 'path') {
                    $prop = 'queryString';
                    continue;
                }
                $this->$prop.= $url[$i];
            }
            if (!empty($this->queryString)) foreach (explode('&', $this->queryString) as $p) {
                $i = strpos($p, '=');
                if ($i === false) $this->data[urldecode($p)] = '';
                else $this->data[urldecode(substr($p, 0, $i)) ] = urldecode(substr($p, ++$i));
            }
            unset($this->queryString);
            $this->domain = strtolower($this->domain);
            $this->port = $this->port - 1;
            $this->port++;
        } elseif (is_array($url)) {
            foreach ($this->fields as $f) $this->$f = $url[$f];
        } else {
            foreach ($this->fields as $f) $this->$f = $url->$f;
        }
        if(!$relative) foreach ($this->defaults as $k => $v) if (empty($this->$k)) if (is_array($v)) $this->$k = $v[$this->scheme];
        else $this->$k = $v;
    }
}
?>