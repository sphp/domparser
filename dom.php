<?php

class dom{
	public $nodeList = null;
    public $doc = null;
	public $content = null;
	public $dom, $that;
	static $data;
	static $attrs   = ['class','id','href','src','data'];
	static $domVars = [
		'tag'	=>'tagName',
		'val'	=>'nodeValue',
		'type'	=>'nodeType',
		'parent'=>'parentNode',
		'child'	=>'childNodes',
		'first'	=>'firstChild',
		'last'	=>'lastChild',
		'attr'	=>'attributes',
		'text'	=>'textContent',
		'prev'  =>'previousSibling'
	];

	function __construct(){
		if($this->doc===null){
			$this->doc = new DOMDocument(); libxml_use_internal_errors(1); //disable libxml errors
		}
		$data = self::$data;
		if(!empty($data)){
			if(is_string($data) && self::isHtml($data)){
				$this->content = $data;
				$this->doc->loadHTML($data);
			}
			else if(is_object($data)) $this->content = self::$data->saveHTML();
		}
		$this->that = $this->nodeList = $this->doc; //copy of start;
	}
	static function __callStatic($name, $args){
		if(filter_var($args[0], FILTER_VALIDATE_URL)) self::$data=self::getContent($args[0]);
		else self::$$name = $args; return new self;
	}
	function __get($var){
		if(array_key_exists($var, self::$domVars)) $var = self::$domVars[$var];//pre($var);
		return property_exists($this->nodeList, $var) ? $this->nodeList->$var : null;
	}
	function __call($name, $args){
		if(method_exists($this->doc, $name)){
			return call_user_func_array(array($this->doc, $name), $args);
		}
		else if(in_array($name, self::$attrs)){
			if(isset($args[0]) && is_string($args[0])){
				if($name=='id') $this->nodeList = $this->doc->getElementById($args[0]);
				else if($name=='class') return $this->find('.'. $args[0]); //pre($this->nodeList,1);
			}else{
				$data = str_replace("'", '"', self::$data);
				return self::inStrs($data, str_replace("_", '-', $name)."=\"", "\"");
			}
		}
		else{
			$e = $this->getElementsByTagName($name);
			if(!empty($e)){
				$this->nodeList = isset($args[0]) && is_integer($args[0]) ? $e[$args[0]] : $e;
			}
			else $this->nodeList = $this->$name;
		}
		return $this;
	}
	function loadHTML($html){
		$this->doc->loadHTML($html);
	}
	function loadURL($url){
		$this->content = $this->getContent($url);
		$this->doc->loadHTML($this->content);
	}
	function nodeHtml(DOMNode $elm){
		$str=''; 
		if($elm) foreach($elm->childNodes as $kid) $str .= trim($elm->ownerDocument->saveHTML($kid));
		return $str;
	}
	function html(){
		if(!isset($this->nodeList->length)) return $this->nodeHtml( $this->nodeList );
		$arr=[]; foreach($this->nodeList as $node) $arr[] = $this->nodeHtml($node); return implode(PHP_EOL, $arr);
	}
	function fileSave($path){
		return $this->doc->saveHTMLFile($path);
	}
	static function getContent($url, $exp=86400/*24hr*/, $dir='./temp/'){
		extract(parse_url($url));
		$path = self::absPath($dir.$host).DIRECTORY_SEPARATOR;
		if(!file_exists($path)) mkdir($path, 0777, true);	//create directory if not exists
		$path .= self::normalizeStr(!empty(basename($url)) ? basename($url) : $host);//create data file name from target url
		$path = strpos($path, '.html') ? $path : $path.'.html';
		if(is_readable($path) && time()-filemtime($path) < $exp){
			//oldRemove();
			return file_get_contents($path);
		}else{
			$curl = self::curlGet($url);
			if($curl['http_code']==200) file_put_contents($path, $curl['data']);
			return $curl;
		}
	}
	static function getContent2($url){
	    return file_get_contents($url, false, stream_context_create(array('http' => array('header'=>'Connection: close\r\n'))));
	}
	static function curlGet($url){
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt($ch, CURLOPT_ENCODING, "");
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
		curl_setopt($ch, CURLOPT_TIMEOUT, 30);
		curl_setopt($ch, CURLOPT_FAILONERROR, 1);
		$data = curl_exec($ch);
		$info = curl_getinfo($ch);
		curl_close($ch);
		if($info['http_code']==200) $info['data'] = $data; 
		return $info;
	}
	static function absPath($path){
		$path = str_replace(['/', '\\'], 'DS', $path);
		$relDirs = array_filter(explode('DS', $path), function($p){return $p!=='.';});
		$absDirs = explode(DIRECTORY_SEPARATOR, getcwd());
		foreach($relDirs as $dir) $dir=='..' ? array_pop($absDirs) : array_push($absDirs, $dir);
		return implode(DIRECTORY_SEPARATOR, $absDirs);
	}
	static function isHtml($str){return $str != strip_tags($str) ? true:false;}

	static function inStrs($str,$s,$e){
		if(is_array($str)) $str = implode(' ', $str);
		$p=explode($s,$str);$m=[];
		for($i=1;$i<count($p);$i++) $m[]=explode($e,$p[$i])[0];
		return $m;
	}
	function between($start, $end){
		$str = explode($start, $this->content,2)[1];
		$this->doc->loadHTML(explode($end, $str,2)[0]);
		return $this;
	}
	function after($str){
		$str = explode($str, $this->content,2)[1];
		$this->doc->loadHTML($str);
		return $this;
	}
	function before($str){
		$str = explode($str, $this->content,2)[0];
		$this->doc->loadHTML($str);
		return $this;
	}
	function nodes(){
		return $this->nodeList;
	}
	function node($indx=0){
		return $this->nodeList->item($indx);
	}
	function attr($val){
		$arr = [];
		foreach ($this->nodeList as $node) $arr[] = $node->getAttribute($val);
		return !empty($arr) ? $arr : null;
	}
	function innerText(){
		$arr = [];
		foreach ($this->nodeList as $node) $arr[] = trim($node->textContent);
		return !empty($arr) ? $arr : null;
	}
	function find($sel){
		if(!ctype_alpha($sel)){
			$path = $this->toXPath($sel);
			$xpath = new DOMXpath($this->doc);
			$this->nodeList = $xpath->query($path);
		}else{
			$this->$sel();
		}
		return $this;
	}
	function GetById($id){
	    $this->nodeList = $this->doc->getElementById($id);
	    return $this;
	}
	function GetByTagName($tag){
	    $this->nodeList = $this->doc->getElementsByTagName($tag);
	    return $this;
	}
	function GetByClass($class){
	    $xpath = new DOMXPath($this->doc);
	    $this->nodeList = $xpath->query( "//*[contains(concat(' ', normalize-space(@class), ' '), ' $class ')]" );
	    return $this;
	}
	function toXPath($selector) { // remove spaces around operators
		$selector = preg_replace('/\s*>\s*/', '>', $selector);
		$selector = preg_replace('/\s*~\s*/', '~', $selector);
		$selector = preg_replace('/\s*\+\s*/', '+', $selector);
		$selector = preg_replace('/\s*,\s*/', ',', $selector);
		$selectors = preg_split('/\s+(?![^\[]+\])/', $selector);
		foreach ($selectors as &$selector) {
			$selector = preg_replace('/,/', '|descendant-or-self::', $selector);// ,
			$selector = preg_replace('/(.+)?:(checked|disabled|required|autofocus)/', '\1[@\2="\2"]', $selector);// input:checked, :disabled, etc.
			$selector = preg_replace('/(.+)?:(autocomplete)/', '\1[@\2="on"]', $selector);// input:autocomplete, :autocomplete
			$selector = preg_replace('/:(text|password|checkbox|radio|button|submit|reset|file|hidden|image|datetime|datetime-local|date|month|time|week|number|range|email|url|search|tel|color)/', 'input[@type="\1"]', $selector);// input:button, input:submit, etc.
			$selector = preg_replace('/(\w+)\[([_\w-]+[_\w\d-]*)\]/', '\1[@\2]', $selector);// foo[id]
			$selector = preg_replace('/\[([_\w-]+[_\w\d-]*)\]/', '*[@\1]', $selector); // [id]
			$selector = preg_replace('/\[([_\w-]+[_\w\d-]*)=[\'"]?(.*?)[\'"]?\]/', '[@\1="\2"]', $selector); // foo[id=foo]
			$selector = preg_replace('/^\[/', '*[', $selector);// [id=foo]
			$selector = preg_replace('/([_\w-]+[_\w\d-]*)\#([_\w-]+[_\w\d-]*)/', '\1[@id="\2"]', $selector);// div#foo
			$selector = preg_replace('/\#([_\w-]+[_\w\d-]*)/', '*[@id="\1"]', $selector);// #foo
			$selector = preg_replace('/([_\w-]+[_\w\d-]*)\.([_\w-]+[_\w\d-]*)/', '\1[contains(concat(" ",@class," ")," \2 ")]', $selector);// div.foo
			$selector = preg_replace('/\.([_\w-]+[_\w\d-]*)/', '*[contains(concat(" ",@class," ")," \1 ")]', $selector);// .foo
			$selector = preg_replace('/([_\w-]+[_\w\d-]*):first-child/', '*/\1[position()=1]', $selector);// div:first-child
			$selector = preg_replace('/([_\w-]+[_\w\d-]*):last-child/', '*/\1[position()=last()]', $selector);// div:last-child
			$selector = str_replace(':first-child', '*/*[position()=1]', $selector);// :first-child
			$selector = str_replace(':last-child', '*/*[position()=last()]', $selector);// :last-child
			$selector = preg_replace('/:nth-last-child\((\d+)\)/', '[position()=(last() - (\1 - 1))]', $selector);// :nth-last-child
			$selector = preg_replace('/([_\w-]+[_\w\d-]*):nth-child\((\d+)\)/', '*/*[position()=\2 and self::\1]', $selector);// div:nth-child
			$selector = preg_replace('/:nth-child\((\d+)\)/', '*/*[position()=\1]', $selector);// :nth-child
			$selector = preg_replace('/([_\w-]+[_\w\d-]*):contains\((.*?)\)/', '\1[contains(string(.),"\2")]', $selector);// :contains(Foo)
			$selector = preg_replace('/>/', '/', $selector);// >
			$selector = preg_replace('/~/', '/following-sibling::', $selector);// ~
			$selector = preg_replace('/\+([_\w-]+[_\w\d-]*)/', '/following-sibling::\1[position()=1]', $selector);// +
			$selector = str_replace(']*', ']', $selector);
			$selector = str_replace(']/*', ']', $selector);
		}
		// ' '
		$selector = implode('/descendant::', $selectors);
		$selector = 'descendant-or-self::' . $selector;
		$selector = preg_replace('/(((\|)?descendant-or-self::):scope)/', '.\3', $selector);// :scope
		$sub_selectors = explode(',', $selector);// $element
		foreach ($sub_selectors as $key => $sub_selector) {
			$parts = explode('$', $sub_selector);
			$sub_selector = array_shift($parts);
			if (count($parts) && preg_match_all('/((?:[^\/]*\/?\/?)|$)/', $parts[0], $matches)) {
				$results = $matches[0];
				$results[] = str_repeat('/..', count($results) - 2);
				$sub_selector .= implode('', $results);
			}
			$sub_selectors[$key] = $sub_selector;
		}
		$selector = implode(',', $sub_selectors);
		return $selector;
	}
	static function normalizeStr($str = ''){
	    $str = strip_tags($str); 
	    $str = preg_replace('/[\r\n\t ]+/', ' ', $str);
	    $str = preg_replace('/[\"\*\/\:\<\>\?\'\|]+/', ' ', $str);
	    $str = strtolower($str);
	    $str = html_entity_decode( $str, ENT_QUOTES, "utf-8" );
	    $str = htmlentities($str, ENT_QUOTES, "utf-8");
	    $str = preg_replace("/(&)([a-z])([a-z]+;)/i", '$2', $str);
	    $str = str_replace(' ', '-', $str);
	    $str = rawurlencode($str);
	    $str = str_replace('%', '-', $str);
	    return $str;
	}
}
