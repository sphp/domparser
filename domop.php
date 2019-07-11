<?php
/*
$domh->find(".contestant img")->attr("src")[0];
$nodes->item($i);
$domh->find("a")->attr("href");
$domh->GetById("a")->attr("href");
$domh->GetByTagName("a")->attr("href");
$domh->GetByClass("a")->attr("href");
$domh->find("a")->node(0)->parentNode;
$domh->find("a")->innerText();
$domh->find("a")->innerHTML();
$domh->find("a")->node(1)->parentNode->innerHTML()
$elm = $domh->find("a")->node(0)->parentNode;
DomOP::inrHTML($elm);
$elm = $domh->find("a")->node(0)->parentNode; DomOP::inrHTML($elm);
*/
/* DOMDocument Object parser class */
class DomOP{
    public $doc = null;
    public $tempDir = "./temp/";
	public $content = null;
	public $nodeList = null;
	
	function __construct(){
		if( $this->doc === null ){
			$this->doc = new DOMDocument();
			libxml_use_internal_errors(true);
		}
	}
	function __call($func, $args){
		return call_user_func_array(array(&$this->doc, $func), $args);
	}
	function loadHTML($html){
		$this->doc->loadHTML($html);
	}
	function loadURL($url){
		$this->content = $this->get_contents($url);
		$this->doc->loadHTML($this->content);
	}

	function get_contents($url){
	    return file_get_contents($url,false,stream_context_create(array('http' => array('header'=>'Connection: close\r\n'))));
	}
	function curl_get_url($url)
	{
	    $ch = curl_init();
	    curl_setopt($ch, CURLOPT_URL, $url);
	    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
	    $ip=rand(0,255).'.'.rand(0,255).'.'.rand(0,255).'.'.rand(0,255);
	    curl_setopt($ch, CURLOPT_HTTPHEADER, array("REMOTE_ADDR: $ip", "HTTP_X_FORWARDED_FOR: $ip"));
	    curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/".rand(3,5).".".rand(0,3)." (Windows NT ".rand(3,5).".".rand(0,2)."; rv:2.0.1) Gecko/20100101 Firefox/".rand(3,5).".0.1");
	    $html = curl_exec($ch);
	    curl_close($ch);
	    return $html;
	}

	function get_Dir($str=null){
		$ext = file_ext($str); return ($ext!='css' && $ext!='js') ? 'asset/' : $ext.'/';
	}
	function forceGrab($url,$downloadFiles=false){
		$htmlfile = basename($url).".html";
		if(is_readable( $htmlfile))
			$this->content = file_get_contents($htmlfile); 
		else{
			$this->content = $this->get_contents($url);
			$links  = self::in2strs('src="','"',$this->content);
			$real_links  = !empty($links) ? self::relParh($links, $url) : null;

			if($downloadFiles){
				$fileArr = [];
				foreach ($real_links as $src) {
					$file = basename($src);
					$fileArr[] = $file = $this->tempDir . $this->get_Dir($file).$file;
					if(!is_readable( $file))
						file_put_contents($file, $this->get_contents($src));
				}
				$this->content = str_ireplace($links, $fileArr, $this->content);
			}
			else $this->content = str_ireplace($links, $real_links, $this->content);
			$links = DomOP::in2strs('href="','"',$this->content);
			$links = unsetArrItem($links, '#');
			$real_links = !empty($links) ? self::relParh($links, $url) : null;
			
			if($downloadFiles){
				$fileArr = [];
				foreach ($real_links as $href) {
					$file = basename($href);
					$fileArr[] = $file = $this->tempDir . $this->get_Dir($file).$file;
					if(!is_readable( $file))
						file_put_contents( $file, $this->get_contents($href));
				}
				$this->content = str_ireplace($links, $fileArr, $this->content);
			}
			else $this->content = str_ireplace($links, $real_links, $this->content);
			file_put_contents( $htmlfile, $this->content);
		}
		$this->doc->loadHTML($this->content);
	}
	static function relParh(array $arr, $fullUrl){
		$temp = [];
		foreach ($arr as $elm) $temp[] = DomOP::rel2abs($elm, $fullUrl);
		return $temp;
	}
	static function rel2abs($rel, $base){
	    if (parse_url($rel, PHP_URL_SCHEME) != '') return $rel; /* return if already absolute URL */
	    if ($rel[0]=='#' || $rel[0]=='?') return $base.$rel; /* queries and anchors */
	    extract(parse_url($base));/* parse base URL and convert to local variables: $scheme, $host, $path */
	    $path = preg_replace('#/[^/]*$#', '', $path); /* remove non-directory element from path */
	    if ($rel[0] == '/') $path = ''; /* destroy path if relative url points to root */
	    $abs = "$host$path/$rel";   /* dirty absolute URL */
	    $re = array('#(/\.?/)#', '#/(?!\.\.)[^/]+/\.\./#'); /* replace '//' or '/./' or '/foo/../' with '/' */
	    for($n=1; $n>0; $abs = preg_replace($re, '/', $abs, -1, $n)) {}
	    return $scheme.'://'.$abs; /* absolute URL is ready! */
	}
	static function in2str($start,$end,$data){
	    $p = explode($start,$data);
	    $p = isset($p[1]) ? explode($end,$p[1]):'';
	    return isset($p[0]) ? trim($p[0]):'';
	}
	static function in2strs($start,$end,$data){
	    $p = array();
	    $p1 = explode($start,$data);
	    for($i=1;$i<count($p1);$i++){
	        $p2 = explode($end,$p1[$i]);
	        $p[] = $p2[0];
	    }return $p;
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
	function find($selectr){
		$path = $this->toXPath($selectr);
		$xpath = new DOMXpath($this->doc);
		$this->nodeList = $xpath->query($path);
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
	static function inrHTML(DOMNode $element=null){
		$innerHTML = "";
		$children  = $element->childNodes;
		foreach ($children as $child)
			$innerHTML .= $element->ownerDocument->saveHTML($child);
		return trim($innerHTML);
	}
	function innerHTML(){
		$arr = [];
		foreach ($this->nodeList as $node) $arr[] = trim(self::inrHTML($node));
		return !empty($arr) ? $arr : null;
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
}
