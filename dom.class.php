<?php
class dom{
	public $elms, $doc, $content, $that;
	static $data, $attrs = ['class','id','href','src','data'];
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
			if(is_string($data)){
				$this->content = $data;
				$this->doc->loadHTML($data);
			}
			else if(is_object($data)) $this->content = self::$data->saveHTML();
		}
		$this->that = $this->elms = $this->doc; //copy of start;
	}
	static function __callStatic($name, $args){
		if(filter_var($args[0], FILTER_VALIDATE_URL)) self::$data=self::getContent($args[0]);
		else self::$$name = $args; return new self;
	}
	function __get($name){
		if(array_key_exists($name, self::$domVars)) $name = self::$domVars[$name];
		return property_exists($this->elms, $name) ? $this->elms->$name : null;
	}
	function __call($name, $args){
		if(method_exists($this->doc, $name)){
			return call_user_func_array(array($this->doc, $name), $args);
		}
		else if(in_array($name, self::$attrs)){
			if(isset($args[0]) && is_string($args[0])){
				if($name=='id') $this->elms = $this->doc->getElementById($args[0]);
				else if($name=='class') $this->elms = $this->find('.'. $args[0]); //pre($this->elms,1);
			}else{
				$data = str_replace("'", '"', $this->content);
				return self::inStrs($data, str_replace("_", '-', $name)."=\"", "\"");
			}
		}
		else{
			$e = $this->getElementsByTagName($name);
			if(!empty($e->length)){
				$this->elms = isset($args[0]) && is_integer($args[0]) ? $e[$args[0]] : $e;
			}
			else{
				$this->elms = $this->$name;
			}
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
		if(!isset($this->elms->length)) return $this->nodeHtml( $this->elms );
		$arr=[]; foreach($this->elms as $node) $arr[] = $this->nodeHtml($node); return implode(PHP_EOL, $arr);
	}
	function save($path){
		return $this->doc->saveHTMLFile($path);
	}
	static function getContent($url, $exp=86400/*24hr*/, $dir='./temp/'){
		extract(parse_url($url));
		$path = absPath($dir.$host).DIRECTORY_SEPARATOR;
		if(!file_exists($path)) mkdir($path, 0777, true);	//create directory if not exists
		$path .= self::normalizeStr( !empty(basename($url)) ? basename($url) : $host);//create data file name from target url
		$path = strpos($path, '.html') ? $path : $path.'.html';
		if(is_readable($path) && time()-filemtime($path) < $exp){
			self::cacheClean($exp);
			return file_get_contents($path);
		}else{
			$curl = self::curlGet($url);
			if($curl['http_code']==200) file_put_contents($path, $curl['contents']);
			return $curl['contents'];
		}
	}
	static function getContent2($url){
	    return file_get_contents($url, false, stream_context_create(array('http' => array('header'=>'Connection: close\r\n'))));
	}
	static function curlGet($url, $dataOnly=false, $infoOnly=false, $noCache=false){
		$ch = curl_init($url);
		if(strpos($url, 'https:')!==false){
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
		}
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt($ch, CURLOPT_ENCODING, "");
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
		curl_setopt($ch, CURLOPT_TIMEOUT, 30);
		curl_setopt($ch, CURLOPT_FAILONERROR, 1);
		curl_setopt($ch, CURLOPT_HEADER, 1);
		if($infoOnly){
			curl_setopt($ch, CURLOPT_NOBODY, 1);
		}
		if($noCache){
			curl_setopt($ch, CURLOPT_FORBID_REUSE, 1);
			curl_setopt($ch, CURLOPT_FRESH_CONNECT, 1);
		}
		$data   = curl_exec($ch);
		$info   = curl_getinfo($ch);
		$header = substr($data, 0, $info['header_size']);
		$data   = substr($data, $info['header_size']);
		$info['header'] = self::parseHeader($header);
		if($info['http_code']==200){
			$info['contents'] = preg_replace('/\s+/', ' ', $data);
		}
		curl_close($ch);
		return $dataOnly ? $info['contents'] : $info;
	}
	static function parseHeader($header){
		$headers = [];
		$reqArr  = explode("\r\n\r\n", $header);// Split the string on every "double" new line.
		for ($i=0,$c=count($reqArr)-1; $i<$c ; $i++){
			foreach (explode("\r\n", $reqArr[$i]) as $i => $line){
				if($i===0) $headers['http_code'] = $line;
				else{
					list($key, $value) = explode(': ', $line);
					if(strpos($value, 'filename')) $headers['filename'] = strBetween($value,'filename="', '"');
					if(!array_key_exists($key, $headers)) $headers[$key] = $value;
					else{
						if(is_array($headers[$key])) $headers[$key][] = $value;
						else $headers[$key] = [$headers[$key], $value];
					}
				}
			}
		}
		return $headers;
	}
	static function inStrs($str,$s,$e){
		if(is_array($str)) $str = implode(' ', $str);
		$p=explode($s,$str);$m=[];
		for($i=1;$i<count($p);$i++) $m[]=explode($e,$p[$i])[0];
		return $m;
	}
	
	function between($start, $end){
		$str = explode($start, $this->content,2)[1];
		$this->content = explode($end, $str,2)[0];
		$this->doc->loadHTML($this->content);
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
		return $this->elms;
	}
	function node($indx=0){
		return $this->elms->item($indx);
	}
	function attr($val){
		$arr = [];
		foreach ($this->elms as $node) $arr[] = $node->getAttribute($val);
		return !empty($arr) ? $arr : null;
	}
	function innerText(){
		$arr = [];
		foreach ($this->elms as $node) $arr[] = trim($node->textContent);
		return !empty($arr) ? $arr : null;
	}
	function find($sel){
		if(!ctype_alpha($sel)){
			$path = $this->toXPath($sel);
			$xpath = new DOMXpath($this->doc);
			$this->elms = $xpath->query($path);
		}
		else $this->$sel();
		return $this;
	}
	function GetById($id){
	    $this->elms = $this->doc->getElementById($id);
	    return $this;
	}
	function GetByTagName($tag){
	    $this->elms = $this->doc->getElementsByTagName($tag);
	    return $this;
	}
	function GetByClass($class){
	    $xpath = new DOMXPath($this->doc);
	    $this->elms = $xpath->query( "//*[contains(concat(' ', normalize-space(@class), ' '), ' $class ')]" );
	    return $this;
	}
	function toXPath($sel) { // remove spaces around operators
		$sel = preg_replace('/\s*>\s*/', '>', $sel);
		$sel = preg_replace('/\s*~\s*/', '~', $sel);
		$sel = preg_replace('/\s*\+\s*/', '+', $sel);
		$sel = preg_replace('/\s*,\s*/', ',', $sel);
		$sels = preg_split('/\s+(?![^\[]+\])/', $sel);
		foreach ($sels as &$sel) {
			$sel = preg_replace('/,/', '|descendant-or-self::', $sel);// ,
			$sel = preg_replace('/(.+)?:(checked|disabled|required|autofocus)/', '\1[@\2="\2"]', $sel);// input:checked, :disabled, etc.
			$sel = preg_replace('/(.+)?:(autocomplete)/', '\1[@\2="on"]', $sel);// input:autocomplete, :autocomplete
			$sel = preg_replace('/:(text|password|checkbox|radio|button|submit|reset|file|hidden|image|datetime|datetime-local|date|month|time|week|number|range|email|url|search|tel|color)/', 'input[@type="\1"]', $sel);// input:button, input:submit, etc.
			$sel = preg_replace('/(\w+)\[([_\w-]+[_\w\d-]*)\]/', '\1[@\2]', $sel);// foo[id]
			$sel = preg_replace('/\[([_\w-]+[_\w\d-]*)\]/', '*[@\1]', $sel); // [id]
			$sel = preg_replace('/\[([_\w-]+[_\w\d-]*)=[\'"]?(.*?)[\'"]?\]/', '[@\1="\2"]', $sel); // foo[id=foo]
			$sel = preg_replace('/^\[/', '*[', $sel);// [id=foo]
			$sel = preg_replace('/([_\w-]+[_\w\d-]*)\#([_\w-]+[_\w\d-]*)/', '\1[@id="\2"]', $sel);// div#foo
			$sel = preg_replace('/\#([_\w-]+[_\w\d-]*)/', '*[@id="\1"]', $sel);// #foo
			$sel = preg_replace('/([_\w-]+[_\w\d-]*)\.([_\w-]+[_\w\d-]*)/', '\1[contains(concat(" ",@class," ")," \2 ")]', $sel);// div.foo
			$sel = preg_replace('/\.([_\w-]+[_\w\d-]*)/', '*[contains(concat(" ",@class," ")," \1 ")]', $sel);// .foo
			$sel = preg_replace('/([_\w-]+[_\w\d-]*):first-child/', '*/\1[position()=1]', $sel);// div:first-child
			$sel = preg_replace('/([_\w-]+[_\w\d-]*):last-child/', '*/\1[position()=last()]', $sel);// div:last-child
			$sel = str_replace(':first-child', '*/*[position()=1]', $sel);// :first-child
			$sel = str_replace(':last-child', '*/*[position()=last()]', $sel);// :last-child
			$sel = preg_replace('/:nth-last-child\((\d+)\)/', '[position()=(last() - (\1 - 1))]', $sel);// :nth-last-child
			$sel = preg_replace('/([_\w-]+[_\w\d-]*):nth-child\((\d+)\)/', '*/*[position()=\2 and self::\1]', $sel);// div:nth-child
			$sel = preg_replace('/:nth-child\((\d+)\)/', '*/*[position()=\1]', $sel);// :nth-child
			$sel = preg_replace('/([_\w-]+[_\w\d-]*):contains\((.*?)\)/', '\1[contains(string(.),"\2")]', $sel);// :contains(Foo)
			$sel = preg_replace('/>/', '/', $sel);// >
			$sel = preg_replace('/~/', '/following-sibling::', $sel);// ~
			$sel = preg_replace('/\+([_\w-]+[_\w\d-]*)/', '/following-sibling::\1[position()=1]', $sel);// +
			$sel = str_replace(']*', ']', $sel);
			$sel = str_replace(']/*', ']', $sel);
		}
		// ' '
		$sel = implode('/descendant::', $sels);
		$sel = 'descendant-or-self::' . $sel;
		$sel = preg_replace('/(((\|)?descendant-or-self::):scope)/', '.\3', $sel);// :scope
		$sub_sel = explode(',', $sel);// $element
		foreach ($sub_sel as $key => $sub_selector) {
			$parts = explode('$', $sub_selector);
			$sub_selector = array_shift($parts);
			if (count($parts) && preg_match_all('/((?:[^\/]*\/?\/?)|$)/', $parts[0], $matches)) {
				$results = $matches[0];
				$results[] = str_repeat('/..', count($results) - 2);
				$sub_selector .= implode('', $results);
			}
			$sub_sel[$key] = $sub_selector;
		}
		$sel = implode(',', $sub_sel);
		return $sel;
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
	static function cacheClean($exp=60*60*24, $force=false, $dir='./temp/'){
		ignore_user_abort(true);
		set_time_limit(0);
		ob_start();
		$dirs = [];
		foreach(dirIterator('./temp/') as $path=>$obj){
			if(is_dir($path)) $dirs[] = $path;
			else if($force || is_file($path) && time()-filemtime($path) >= $exp) @unlink($path);
		}
		foreach($dirs as $dir) @rmdir($dir);
		ob_flush();
		flush(); 
	}
}
function absPath($path){
	$path = str_replace(['/', '\\'], 'DS', $path);
	$relDirs = array_filter(explode('DS', $path), function($p){return $p!=='.';});
	$absDirs = explode(DIRECTORY_SEPARATOR, getcwd());
	foreach($relDirs as $dir) $dir=='..' ? array_pop($absDirs) : array_push($absDirs, $dir);
	return implode(DIRECTORY_SEPARATOR, $absDirs);
}
function dirIterator($path, $dots=RecursiveDirectoryIterator::SKIP_DOTS){
	return new RecursiveIteratorIterator(new RecursiveDirectoryIterator(absPath($path), $dots), RecursiveIteratorIterator::SELF_FIRST);
}
