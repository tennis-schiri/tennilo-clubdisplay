<?php
declare(strict_types=1);
define('CLUBDISPLAY_NEWS_TEST',true);
require __DIR__.'/../public/news.php';
function expectNews(bool $ok,string $msg):void{if(!$ok)throw new RuntimeException($msg);}
$posts=[];
foreach([1,4,2,3] as $i)$posts[]=['title'=>['rendered'=>'Test &amp; '.$i],'excerpt'=>['rendered'=>'<p>Text <b>hier</b></p><script>bad</script>'],'date'=>'2024-01-0'.$i.'T12:00:00','link'=>'https://ntc93.com/test-'.$i.'/'];
$posts[]=['title'=>['rendered'=>'Wrong host'],'date'=>'2024-01-06T12:00:00','link'=>'https://example.org/'];
$posts[]=['title'=>['rendered'=>'Private'],'excerpt'=>['protected'=>true],'date'=>'2024-01-07T12:00:00','link'=>'https://ntc93.com/private/'];
$r=newsRest(json_encode($posts));
expectNews(count($r)===3&&$r[0]['title']==='Test & 4'&&$r[2]['title']==='Test & 2','order and limit');
expectNews($r[0]['excerpt']==='Text hier','strip markup');
if(function_exists('simplexml_load_string')){
    $r=newsRss('<rss version="2.0"><channel><item><title>News</title><link>https://ntc93.com/news-example/</link><pubDate>Mon, 01 Jan 2024 12:00:00 +0100</pubDate><description><![CDATA[<p>RSS text</p>]]></description></item></channel></rss>');
    expectNews(count($r)===1&&$r[0]['excerpt']==='RSS text','RSS');
}
if(class_exists('DOMDocument')){
    $content=newsContent('<p>Text <a href="https://example.org">Link</a></p><img src="https://ntc93.com/wp-content/uploads/2026/photo.jpg" onerror="bad()"><img src="https://example.org/track.png"><iframe src="https://example.org"></iframe><script>bad()</script>');
    expectNews(str_contains($content,'Text Link')&&str_contains($content,'photo.jpg'),'article text and local images');
    expectNews(!str_contains($content,'href=')&&!str_contains($content,'onerror')&&!str_contains($content,'example.org')&&!str_contains($content,'bad()'),'no links or active content');
}
echo "News tests OK\n";
