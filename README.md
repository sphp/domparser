# domparser
simple lite html dom parser
```
require('dom.php');
$url  = 'https://php.net';
$html = Dom::url($url)->html();
$div  = Dom::data($html)->div();
$href  = Dom::data($html)->href();
$class  = Dom::data($html)->class();
$id  = Dom::data($html)->id();
$find  = Dom::data($html)->find('#layout-content')->html();
$h2  = Dom::data($html)->h2()->contains('PHP 7.3.14 Released')->html();
pre($h2,1);
```
