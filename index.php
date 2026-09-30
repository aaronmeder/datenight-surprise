<?php
declare(strict_types=1);
const APP_TITLE='Datenight Surprise';
const IDEAS_API='https://midnightduet.com/wp-json/wp/v2/idea';
const IDEAS_CACHE=__DIR__.'/data/ideas-cache.json';
const IDEAS_TTL=600; // seconds before the ideas are fetched from WordPress again
header('X-Robots-Tag: noindex, nofollow, noarchive, nosnippet');
function e(string $s):string{return htmlspecialchars($s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function plain(string $s):string{return trim(html_entity_decode(strip_tags($s),ENT_QUOTES|ENT_HTML5,'UTF-8'));}
function fetchJson(string $url):mixed{
  if(function_exists('curl_init')){$c=curl_init($url);curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>6,CURLOPT_CONNECTTIMEOUT=>4,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_HTTPHEADER=>['Accept: application/json'],CURLOPT_USERAGENT=>APP_TITLE]);$body=curl_exec($c);curl_close($c);}
  else{$body=@file_get_contents($url,false,stream_context_create(['http'=>['timeout'=>6,'ignore_errors'=>true,'header'=>"Accept: application/json\r\nUser-Agent: ".APP_TITLE."\r\n"]]));}
  return is_string($body)?json_decode($body,true):null;
}
// Pulls every published idea from WordPress; null means the request failed and the cache should be kept.
function fetchIdeas():?array{
  $ideas=[];
  for($page=1;;$page++){
    $rows=fetchJson(IDEAS_API.'?'.http_build_query(['per_page'=>100,'page'=>$page,'orderby'=>'menu_order','order'=>'asc','_embed'=>'wp:term','_fields'=>'id,title,link,_links,_embedded']));
    if(!is_array($rows)||!array_is_list($rows)){if($page>1&&($rows['code']??'')==='rest_post_invalid_page_number')break;return null;}
    foreach($rows as $r){
      $title=plain((string)($r['title']['rendered']??''));if($title==='')continue;
      $terms=['idea_category'=>[],'idea_tag'=>[]];
      foreach($r['_embedded']['wp:term']??[] as $group)foreach($group as $t)if(isset($terms[$t['taxonomy']??'']))$terms[$t['taxonomy']][]=plain((string)$t['name']);
      $meta=implode(' · ',array_map('e',$terms['idea_tag']));
      $content=($terms['idea_category']?'<p><strong>'.e(implode(' · ',$terms['idea_category'])).'</strong></p>':'').($meta?'<p>'.$meta.'</p>':'');
      $link=(string)($r['link']??'');if(preg_match('~^https?://~i',$link))$content.='<p><a href="'.e($link).'">Read more on Midnight Duet</a></p>';
      $ideas[]=['id'=>(int)$r['id'],'title'=>$title,'content'=>$content];
    }
    if(count($rows)<100)break;
  }
  return $ideas;
}
// Serves the cached copy while it is fresh. When WordPress can't be reached the last good copy keeps working,
// and its timestamp is bumped so visitors don't wait on a failing request every time.
function loadIdeas():array{
  $cached=is_file(IDEAS_CACHE)?json_decode((string)file_get_contents(IDEAS_CACHE),true):null;
  $age=is_array($cached)?time()-(int)filemtime(IDEAS_CACHE):PHP_INT_MAX;
  $maxAge=isset($_GET['refresh'])?30:IDEAS_TTL;
  if(is_array($cached)&&$age<$maxAge)return $cached;
  $fresh=fetchIdeas();
  if($fresh===null){if(is_array($cached)){@touch(IDEAS_CACHE);return $cached;}return [];}
  if(!is_dir(dirname(IDEAS_CACHE)))@mkdir(dirname(IDEAS_CACHE),0775,true);
  $tmp=IDEAS_CACHE.'.'.bin2hex(random_bytes(4));
  if(@file_put_contents($tmp,json_encode($fresh,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES))!==false)@rename($tmp,IDEAS_CACHE);else @unlink($tmp);
  return $fresh;
}
$ideas=loadIdeas();
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="robots" content="noindex,nofollow,noarchive,nosnippet"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><meta name="theme-color" content="#130b10"><meta name="application-name" content="<?=APP_TITLE?>"><meta name="apple-mobile-web-app-title" content="<?=APP_TITLE?>"><meta name="description" content="One night. One card. No overthinking."><title><?=APP_TITLE?></title><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600&family=Italiana&display=swap" rel="stylesheet"><link rel="stylesheet" href="assets/style.css?v=2"></head><body>
<main class="app-shell"><header class="masthead"><div class="monogram" aria-hidden="true">A<span>×</span>A</div><p>Our private collection</p></header><section class="experience" aria-labelledby="page-title"><div class="intro"><p class="eyebrow">Tonight’s invitation</p><h1 id="page-title">Leave it<br>to <em>chance.</em></h1><p class="lede">One night. One card.<br>No overthinking.</p></div><div class="stage" id="stage" aria-live="polite"><div class="ambient-glow"></div><div class="deck"><div class="ghost-card ghost-one"></div><div class="ghost-card ghost-two"></div><article class="date-card" id="date-card"><div class="card-face card-front"><div class="card-ornament">✦</div><p class="card-kicker">A date for two</p><h2 id="card-title">Ready to tempt fate?</h2><span class="card-number">№ <b id="card-number">?</b></span></div><div class="card-face card-back"><button class="close-card" id="close-card" aria-label="Turn card over">×</button><p class="card-kicker">Tonight</p><h2 id="result-title"></h2><div class="card-content" id="result-content"></div></div></article></div><p class="shuffle-status" id="shuffle-status"><?=$ideas?'Your evening is waiting':'The deck is resting – try again soon'?></p></div><button class="shuffle-button" id="shuffle-button" <?=$ideas?'':'disabled'?>>✦ <span class="button-label">Shuffle the deck</span> ✦</button><p class="hint">Tap once. Let anticipation do the rest.</p></section><footer><span><?=count($ideas)?> private possibilities</span><span>Made for two</span></footer></main><script>window.DATE_IDEAS=<?=json_encode($ideas,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>;</script><script src="assets/app.js?v=2" defer></script></body></html>
