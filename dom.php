<?php

class dom{
	public $elms, $doc, $content, $that;
	static $method, $args, $attrs = ['class','id','href','src','alt','title','style','name','data'];
	static $vars = [
		'tag'	 =>'tagName',
		'val'	 =>'nodeValue',
		'type'	 =>'nodeType',
		'parent' =>'parentNode',
		'child'	 =>'childNodes',
		'first'	 =>'firstChild',
		'last' 	 =>'lastChild',
		'attr'	 =>'attributes',
		'prev'   =>'previousSibling',
		'save'   =>'saveHTMLFile'
	];
	function __construct(){
		$args = isone(self::$args);
		if($this->doc === null){
			$this->doc = new DOMDocument(); libxml_use_internal_errors(1); //disable libxml errors
			$this->that = $this->elms = $this->doc; //copy of start;
		}
		if(is_object($args)){
			$this->content = $args->saveHTML();
		}else{
			switch (self::$method){
				case 'url'  : if(filter_var($args, FILTER_VALIDATE_URL)) $this->content = self::getContent($args); break;
				case 'data' : if(isHtml($args)){ $this->content=$args; } break;
				default 	: if(method_exists($this->doc, $name)){return call_user_func_array(array($this->doc, $name), $args);}
			}
		}
		if($this->content!==null) $this->loadHTML($this->content);
	}
	static function __callStatic($name, $args){
		self::$method = $name;
		self::$args = $args;
		return new self;
	}
	function __get($name){
		if(array_key_exists($name, self::$vars)) $name = self::$vars[$name];
		return property_exists($this->elms, $name) ? $this->elms->$name : null;
	}
	function __call($name, $args){
		if(method_exists($this->doc, $name)){
			return call_user_func_array(array($this->doc, $name), $args);
		}
		else{
			$args = isone($args);
			$e = $this->getElementsByTagName($name);
			/** get tag elements. integer args for the specific element**/
			if( isDomList($e) && $e->length>0 ) $this->elms = isset($args) && is_integer($args) ? $e[$args] : $e;
			/** direct return dom object properties **/
			else if( in_array($name, self::$vars) ) $this->elms = $this->$name;
			else if( in_array($name, self::$attrs))
				return self::inStrs(str_replace("'", '"', $this->content), str_replace("_", '-', $name)."=\"", "\"");
			/** use id or class name in args to get element **/
			else if( is_string($args) ){
				if($name=='id') $this->elms = $this->getElementById($args);
				else if($name=='class') $this->find('.'. $args);
				else if($name=='attr') return self::inStrs(str_replace("'", '"', $this->content), str_replace("_", '-', $name)."=\"", "\"");
			}
		}
		return $this;
	}
	function nodeHtml(DOMNode $elm){
		$str='';if($elm) foreach($elm->childNodes as $kid) $str .= trim($elm->ownerDocument->saveHTML($kid)); return $str;
	}
	function html(){
		$html = [];
		if(isDomDoc($this->elms)) $html[] = $this->content;
		if(isDomElm($this->elms)) $html[] = $this->nodeHtml( $this->elms );
		else if(isDomList($this->elms)){
			foreach($this->elms as $elm) $html[] = $this->nodeHtml($elm); 
		}
		return isone($html);
	}
	function contains($str){
		$elms = [];
		if(isDomList($this->elms)){
			foreach($this->elms as $elm)
				if($elm->nodeValue !== str_ireplace($str,'',$elm->nodeValue)) $elms[]=$elm; //OR if(mb_stripos($elm->nodeValue, $str)!==false)
			if(count($elms)>1) array_shift($elms);
			$this->elms = isone($elms);
		}
		return $this;
	}
	function get(){
		$args = arr(isone(func_get_args()));
		$items=[]; foreach ($args as $arg) $items[$arg] = arrFilter($this->$arg());
		return arrayCombiner($items);
	}
	function text(){
		$text = [];
		if(isset($this->elms->textContent)) return $this->elms->textContent;
		else if(isDomList($this->elms)){
			foreach($this->elms as $node) $text[] = trim($node->textContent);
		}
		return isone($text);
	}
	static function getContent($url, $exp=86400/*24hr*/, $dir='./temp/'){
		extract(parse_url($url));
		$path = absPath($dir.$host).DIRECTORY_SEPARATOR;
		if(!file_exists($path)) mkdir($path, 0777, true);	//create directory if not exists
		$path .= self::normalizeStr( !empty(basename($url)) ? basename($url) : $host);//create data file name from target url
		$path = strpos($path, '.html') ? $path : $path.'.html';
		if(is_readable($path) && time()-filemtime($path) < $exp){
			//self::cacheClean($exp);
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
	function node($indx=0){
		return $this->elms->item($indx);
	}
	function attr($val){
		$arr = [];
		foreach ($this->elms as $node) $arr[] = $node->getAttribute($val);
		return !empty($arr) ? $arr : null;
	}
	function find($sel){
		if(!ctype_alpha($sel)){
			$path = $this->toXPath($sel);
			$xpath = new DOMXpath($this->doc);
			$this->elms = $xpath->query($path);
			//pre($this->elms);
		}
		else $this->$sel();
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
function arrayCombiner($arr){
	$result = [];
	for ($i=0; $i <count(reset($arr)) ; $i++) { 
		foreach($arr as $key => $v){
			$result[$i][$key] = $arr[$key][$i];
		}
	}
	return $result;
} 
function isHtml($str){return $str != strip_tags($str) ? true:false;}
function isDomElm($obj){return is_object($obj) && $obj instanceof DOMElement;}
function isDomList($obj){return is_array($obj) || (is_object($obj) && $obj instanceof DOMNodeList);}
function isDomDoc($obj){return is_object($obj) && $obj instanceof DOMDocument;}

function strBetween($str, $bgn, $end){
	$str = substr($str, mb_strpos($str, $bgn) + strlen($bgn));
	if(strstr($str, $end, true)!=false) $str=strstr($str,$end,true);
	return $str;
}
if(!function_exists('absPath')){
	function absPath($path){
		$path = str_replace(['/', '\\'], 'DS', $path);
		$relDirs = array_filter(explode('DS', $path), function($p){return $p!=='.';});
		$absDirs = explode(DIRECTORY_SEPARATOR, getcwd());
		foreach($relDirs as $dir) $dir=='..' ? array_pop($absDirs) : array_push($absDirs, $dir);
		return implode(DIRECTORY_SEPARATOR, $absDirs);
	}
}
if(!function_exists('dirIterator')){
	function dirIterator($path, $dots=RecursiveDirectoryIterator::SKIP_DOTS){
		return new RecursiveIteratorIterator(new RecursiveDirectoryIterator(absPath($path), $dots), RecursiveIteratorIterator::SELF_FIRST);
	}
}
if(!function_exists('arrFilter')){function arrFilter($v){return isIndex($v)?array_values(array_filter(array_unique($v))):$v;}}
if(!function_exists('varName')){function varName($val){foreach($GLOBALS as $k=>$v) if($v===$val)return $k;return false;}}
if(!function_exists('isone')){function isone($v){return (is_array($v)&&isIndex($v)&&count($v)===1)?isone($v[0]):$v;}}
if(!function_exists('uKey')){function uKey($key){$p=arr($key,'_');$i=(int)end($p); return $p[0].'_'.++$i;}}
if(!function_exists('matches')){function matches($p,$s){return preg_match_all($p,$s,$match)?$match:false;}}
if(!function_exists('isIndex')){function isIndex($v){return array_values($v)===$v;}}
if(!function_exists('isAssoc')){function isAssoc($v){return !empty($v)?is_string(array_keys($v)[0]):$v;}}
if(!function_exists('pre')){function pre($v,$x=0){echo '<pre>';print_r($v);echo'</pre>';if($x)exit;}}
if(!function_exists('arr')){function arr($v, $d=','){return !is_array($v)?explode($d,$v):$v;}}
if(!function_exists('str')){function str($v, $d=','){return  is_array($v)?implode($d,$v):$v;}}
if(!function_exists('cut')){function cut(&$arr,$k){$v=$arr[$k];unset($arr[$k]);return $v;}}
