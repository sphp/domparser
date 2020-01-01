<?php
class Dom{
	public $doc;
	static $parsedUrl;
	static $data;
	static $temp='./temp/';
	function __construct(){
		if($this->doc === null){
			$this->doc = new DOMDocument();
			libxml_use_internal_errors(true);
		}
		if(is_object(self::$data)) self::$data = self::$data->saveHTML();
		if(self::$data) $this->doc->loadHTML(self::$data);
		pre(self::$data);
	}
	static function __callStatic($func, $args){
		if(filter_var($args[0], FILTER_VALIDATE_URL)){
			self::$parsedUrl = parse_url($args[0]);
			self::getData($args[0]);
		}
		else self::$data = is_array($args[0]) ? implode(PHP_EOL, $args[0]): $args[0];
		return new self;
	}
	static function getData($url, $expire=86400/*24hr*/){
		extract(self::$parsedUrl);
		$path = self::absPath(self::$temp . $host) . DIRECTORY_SEPARATOR;
		if (!file_exists($path)) mkdir($path, 0777, true);//create directory if not exists
		$path .= !empty(basename($url)) ? basename($url) : $host.'.html';//create data file name from target url
		if(is_readable($path) && time()-filemtime($path) < $expire){
			self::$data = file_get_contents($path);
		}else{
			$output = self::curlGet($url, 1);
			if($output['info']['http_code']==200){
				self::$data = $output['data'];
				file_put_contents($path, $output['data']);
			}
		}
		return !empty(self::$data) ? self::$data : false;
	}
	static function absPath($path){
		$path = str_replace(['/', '\\'], 'DS', $path);
		$relDirs = array_filter(explode('DS', $path), function($p){return $p!=='.';});
		$absDirs = explode(DIRECTORY_SEPARATOR, getcwd());
		foreach($relDirs as $dir) $dir=='..' ? array_pop($absDirs) : array_push($absDirs, $dir);
		return implode(DIRECTORY_SEPARATOR, $absDirs);
	}
	static function curlGet($url, $info=0){
		$result = [];
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt($ch, CURLOPT_ENCODING, "");
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
		curl_setopt($ch, CURLOPT_TIMEOUT, 30);
		curl_setopt($ch, CURLOPT_FAILONERROR, 1);
		$result = curl_exec($ch);
		$cinfo  = curl_getinfo($ch);
		curl_close($ch);
		if($cinfo['http_code']!=200) return $cinfo;
		return $info ? ['info' => $cinfo, 'data' => $result] : $result;
	}
	static function in2strs($data, $start, $end){ //to do callback
		$p=array();
		$p1=explode($start,$data);
		for($i=1,$c=count($p1) ; $i<$c ; $i++){
			$p2 = explode($end,$p1[$i])[0];
			//if($callback) call_user_func($callback, $p2); //to do
			$p[] = $p2;
		}
		return $p;
	}
	static function strBetween($str, $from, $to){
		$string = substr($str, strpos($str, $from) + strlen($from));
		if(strstr($string, $to,true)!=false) $string = strstr ($string,$to,true);
		return $string;
	}
	static function inrHtml(DOMNode $elm=null){
		$htm  = '';
		$cels = $elm->childNodes;foreach($cels as $cel)$htm.=trim($elm->ownerDocument->saveHTML($cel));
		return $htm;
	}
	static function getUrls($str){
		return matches('#\bhttps?://[^,\s()<>]+(?:\([\w\d]+\)|([^,[:punct:]\s]|/))#', $str)[0];
	}
	function __call($name, $args){
		if($name=='urls') return self::getUrls(self::$data);
		else if(method_exists($this->doc, $name))
			return call_user_func_array(array($this->doc, $name), $args);
		else{
			if(strpos($name, '_')!==false) $name = str_replace("_", '-', $name);
			return array_filter(self::in2strs(str_replace("'", '"', self::$data), $name."='" ,"'"));
		}
	}
	function find($selectr){
		$xp = $this->xPath($selectr);
		$xh = new DOMXpath($this->doc);
		$this->nodeList = $xh->query($xp);
		return $this;
	}
	function attr($val){
		$arr=[];
		foreach($this->nodeList as $node) $arr[]=$node->getAttribute($val);
		return array_filter($arr);
	}
	function loadHTML($htm){$this->doc->loadHTML($htm);}
	function loadURL($url){self::$data = $this->curlGet($url);$this->doc->loadHTML(self::$data);}
	function nodes(){return $this->nodeList;}
	function node($n=0){return $this->nodeList->item($n);}
	function text(){$arr=[];foreach($this->nodeList as $node)$arr[]=trim($node->textContent);return $arr;}
	function GetById($id){$this->nodeList=$this->doc->getElementById($id);return $this;}
	function GetByTagName($tag){$this->nodeList=$this->doc->getElementsByTagName($tag);return $this;}
	function GetByClass($class){
		$xph = new DOMXPath($this->doc);
		$this->nodeList = $xph->query( "//*[contains(concat(' ',normalize-space(@class),' '),' $class ')]" );
		return $this;
	}
	function innerHtml(){ $arr=[]; foreach($this->nodeList as $node) $arr[]=trim(self::inrHtml($node));return $arr;}
	function htmlfile($fp){ return $this->doc->saveHTMLFile($fp);}
	function html($fp){ return $this->doc->saveHTML();}
	function xPath($sel){
		$sel=preg_replace('/\s*>\s*/', '>', $sel);	//remove spaces around operators
		$sel=preg_replace('/\s*~\s*/', '~', $sel);
		$sel=preg_replace('/\s*\+\s*/','+', $sel);
		$sel=preg_replace('/\s*,\s*/', ',', $sel);
		$sls=preg_split('/\s+(?![^\[]+\])/',$sel);
		foreach($sls as &$sl){
			$sl=preg_replace('/,/','|descendant-or-self::',$sl);// ,
			$sl=preg_replace('/(.+)?:(checked|disabled|required|autofocus)/','\1[@\2="\2"]',$sl);//input:checked, :disabled, etc.
			$sl=preg_replace('/(.+)?:(autocomplete)/','\1[@\2="on"]',$sl);//input:autocomplete, :autocomplete
			$sl=preg_replace('/:(text|password|checkbox|radio|button|submit|reset|file|hidden|image|datetime|datetime-local|date|month|time|week|number|range|email|url|search|tel|color)/','input[@type="\1"]',$sl);//input:button, input:submit, etc.
			$sl=preg_replace('/(\w+)\[([_\w-]+[_\w\d-]*)\]/','\1[@\2]',$sl);// foo[id]
			$sl=preg_replace('/\[([_\w-]+[_\w\d-]*)\]/','*[@\1]',$sl); // [id]
			$sl=preg_replace('/\[([_\w-]+[_\w\d-]*)=[\'"]?(.*?)[\'"]?\]/','[@\1="\2"]',$sl);// foo[id=foo]
			$sl=preg_replace('/^\[/','*[',$sl);// [id=foo]
			$sl=preg_replace('/([_\w-]+[_\w\d-]*)\#([_\w-]+[_\w\d-]*)/','\1[@id="\2"]',$sl);//div#foo
			$sl=preg_replace('/\#([_\w-]+[_\w\d-]*)/','*[@id="\1"]',$sl);// #foo
			$sl=preg_replace('/([_\w-]+[_\w\d-]*)\.([_\w-]+[_\w\d-]*)/','\1[contains(concat("",@class," ")," \2 ")]',$sl);// div.foo
			$sl=preg_replace('/\.([_\w-]+[_\w\d-]*)/','*[contains(concat("",@class," ")," \1 ")]',$sl);// .foo
			$sl=preg_replace('/([_\w-]+[_\w\d-]*):first-child/','*/\1[position()=1]',$sl);//div:first-child
			$sl=preg_replace('/([_\w-]+[_\w\d-]*):last-child/','*/\1[position()=last()]',$sl);//div:last-child
			$sl=str_replace(':first-child','*/*[position()=1]',$sl);// :first-child
			$sl=str_replace(':last-child','*/*[position()=last()]',$sl);// :last-child
			$sl=preg_replace('/:nth-last-child\((\d+)\)/','[position()=(last()-(\1 - 1))]',$sl);// :nth-last-child
			$sl=preg_replace('/([_\w-]+[_\w\d-]*):nth-child\((\d+)\)/','*/*[position()=\2and self::\1]',$sl);// div:nth-child
			$sl=preg_replace('/:nth-child\((\d+)\)/','*/*[position()=\1]',$sl);//:nth-child
			$sl=preg_replace('/([_\w-]+[_\w\d-]*):contains\((.*?)\)/','\1[contains(string(.),"\2")]',$sl);//:contains(Foo)
			$sl=preg_replace('/>/','/',$sl);// >
			$sl=preg_replace('/~/','/following-sibling::',$sl);// ~
			$sl=preg_replace('/\+([_\w-]+[_\w\d-]*)/','/following-sibling::\1[position()=1]',$sl);//+
			$sl=str_replace(']*',']',$sl);
			$sl=str_replace(']/*',']',$sl);
		}
		$sl=implode('/descendant::',$sls);
		$sl='descendant-or-self::' . $sl;
		$sl=preg_replace('/(((\|)?descendant-or-self::):scope)/','.\3',$sl);// :scope
		$sub_sls=explode(',',$sl);// $element
		foreach($sub_sls as $key => $sub_sl){
			$parts=explode('$',$sub_sl);
			$sub_sl=array_shift($parts);
			$mach = matches('/((?:[^\/]*\/?\/?)|$)/', $parts[0]);
			if(count($parts) && $mach){
				$results[]=str_repeat('/..', count($mach[0]) - 2);
				$sub_sl .= implode('',$results);
			}
			$sub_sls[$key]=$sub_sl;
		}
		$sl=implode(',',$sub_sls);
		return $sl;
	}
}
function matches($ptrn, $str){return preg_match_all($ptrn, $str, $mach) ? $mach : false;}
