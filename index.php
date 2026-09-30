<?php
declare(strict_types=1);
const APP_TITLE='Datenight Surprise';
const IDEAS_API='https://midnightduet.com/wp-json/midnightduet/v1/datenight-ideas';
const IDEAS_CACHE=__DIR__.'/data/ideas-cache.json';
const IDEAS_TTL=43200; // 12 hours; add ?refresh to the URL to fetch right away
const IDEAS_RETRY=300; // after a failed fetch, keep serving the cache and try again in 5 minutes
header('X-Robots-Tag: noindex, nofollow, noarchive, nosnippet');
function e(string $s):string{return htmlspecialchars($s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function text(mixed $v):string{return is_string($v)?trim($v):'';}
function url(mixed $v):string{$u=text($v);return preg_match('~^https?://~i',$u)?$u:'';}
function clean(string $h):string{$h=strip_tags($h,'<p><br><strong><b><em><ul><ol><li><a>');$h=preg_replace('/<(p|br|strong|b|em|ul|ol|li)\b[^>]*>/i','<$1>',$h)??'';return preg_replace_callback('/<a\b([^>]*)>/i',function($m){$u=preg_match('/href\s*=\s*(["\'])(.*?)\1/i',$m[1],$h)?url(html_entity_decode($h[2],ENT_QUOTES,'UTF-8')):'';return $u?'<a href="'.e($u).'">':'<a>';},$h)??'';}
function fetchJson(string $url):mixed{
  if(function_exists('curl_init')){$c=curl_init($url);curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>6,CURLOPT_CONNECTTIMEOUT=>4,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_HTTPHEADER=>['Accept: application/json'],CURLOPT_USERAGENT=>APP_TITLE]);$body=curl_exec($c);$ok=curl_getinfo($c,CURLINFO_RESPONSE_CODE)===200;curl_close($c);}
  else{$body=@file_get_contents($url,false,stream_context_create(['http'=>['timeout'=>6,'header'=>"Accept: application/json\r\nUser-Agent: ".APP_TITLE."\r\n"]]));$ok=$body!==false;}
  return $ok&&is_string($body)?json_decode($body,true):null;
}
// Pulls all ideas from midnightduet.com's datenight-ideas endpoint; null means the request failed and the cache should be kept.
function fetchIdeas():?array{
  $data=fetchJson(IDEAS_API);
  if(!is_array($data['ideas']??null)||!array_is_list($data['ideas']))return null;
  $ideas=[];
  foreach($data['ideas'] as $r){
    if(!is_array($r)||!is_int($r['id']??null)||text($r['title']??null)==='')continue;
    $heat=is_array($r['heat']??null)?['level'=>(int)($r['heat']['level']??0),'max'=>(int)($r['heat']['max']??0),'label'=>text($r['heat']['label']??null)]:null;
    $cta=is_array($r['cta']??null)&&url($r['cta']['url']??null)?['label'=>text($r['cta']['label']??null)?:'Let’s go','url'=>url($r['cta']['url'])]:null;
    $ideas[]=['id'=>$r['id'],'title'=>text($r['title']),'tagline'=>text($r['tagline']??null),'description_html'=>clean(text($r['description_html']??null)),'category'=>text($r['category']??null),'tags'=>array_values(array_filter(array_map('text',is_array($r['tags']??null)?$r['tags']:[]))),'heat'=>$heat,'cta'=>$cta,'url'=>url($r['url']??null)];
  }
  return $ideas;
}
// Serves the cached copy for up to 12 hours (?refresh skips that once the cache is 30 seconds old). When WordPress can't be
// reached the last good copy keeps working, and the cache is marked to retry in a few minutes instead of on every visit.
function loadIdeas():array{
  $cached=is_file(IDEAS_CACHE)?json_decode((string)file_get_contents(IDEAS_CACHE),true):null;
  $age=is_array($cached)?time()-(int)filemtime(IDEAS_CACHE):PHP_INT_MAX;
  $maxAge=isset($_GET['refresh'])?30:IDEAS_TTL;
  if(is_array($cached)&&$age<$maxAge)return $cached;
  $fresh=fetchIdeas();
  if($fresh===null){if(is_array($cached)){@touch(IDEAS_CACHE,time()-IDEAS_TTL+IDEAS_RETRY);return $cached;}return [];}
  if(!is_dir(dirname(IDEAS_CACHE)))@mkdir(dirname(IDEAS_CACHE),0775,true);
  $tmp=IDEAS_CACHE.'.'.bin2hex(random_bytes(4));
  if(@file_put_contents($tmp,json_encode($fresh,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES))!==false)@rename($tmp,IDEAS_CACHE);else @unlink($tmp);
  return $fresh;
}
// The back of the card: heat, tagline and one button straight into the game (or its Midnight Duet page if it has no link).
// Built at render time so markup changes don't wait for the cache to expire.
function cardContent(array $i):string{
  $h='';$heat=$i['heat']??null;
  if($heat&&$heat['max']>0){$dots='';for($n=1;$n<=$heat['max'];$n++)$dots.='<i'.($n<=$heat['level']?' class="on"':'').'></i>';$h.='<p class="idea-heat" role="img" aria-label="Heat: '.e($heat['label']).' ('.$heat['level'].' of '.$heat['max'].')"><b aria-hidden="true">'.$dots.'</b>'.e($heat['label']).'</p>';}
  $h.=($i['tagline']??'')!==''?'<p class="idea-tagline">'.e($i['tagline']).'</p>':($i['description_html']??'');
  $link=$i['cta']??(($i['url']??'')!==''?['label'=>'Explore the idea','url'=>$i['url']]:null);
  if($link)$h.='<p class="idea-links"><a class="idea-cta" href="'.e($link['url']).'">'.e($link['label']).' →</a></p>';
  return $h;
}
$ideas=array_map(fn($i)=>['id'=>$i['id'],'title'=>$i['title'],'content'=>cardContent($i)],loadIdeas());
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="robots" content="noindex,nofollow,noarchive,nosnippet"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><meta name="theme-color" content="#130b10"><meta name="application-name" content="<?=APP_TITLE?>"><meta name="apple-mobile-web-app-title" content="<?=APP_TITLE?>"><meta name="description" content="One night. One card. No overthinking."><title><?=APP_TITLE?></title><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600&family=Italiana&display=swap" rel="stylesheet"><link rel="stylesheet" href="assets/style.css?v=4"></head><body>
<main class="app-shell"><header class="masthead"><div class="monogram" aria-hidden="true">A<span>×</span>A</div><p>Our private collection</p></header><section class="experience" aria-labelledby="page-title"><div class="intro"><p class="eyebrow">Tonight’s invitation</p><h1 id="page-title">Leave it<br>to <em>chance.</em></h1><p class="lede">One night. One card.<br>No overthinking.</p></div><div class="stage" id="stage" aria-live="polite"><div class="ambient-glow"></div><div class="deck"><div class="ghost-card ghost-one"></div><div class="ghost-card ghost-two"></div><article class="date-card" id="date-card"><div class="card-face card-front"><div class="card-ornament">✦</div><p class="card-kicker">A date for two</p><h2 id="card-title">Ready to tempt fate?</h2><span class="card-number">№ <b id="card-number">?</b></span></div><div class="card-face card-back"><button class="close-card" id="close-card" aria-label="Turn card over">×</button><p class="card-kicker">Tonight</p><h2 id="result-title"></h2><div class="card-content" id="result-content"></div></div></article></div><p class="shuffle-status" id="shuffle-status"><?=$ideas?'Your evening is waiting':'The deck is resting – try again soon'?></p></div><button class="shuffle-button" id="shuffle-button" <?=$ideas?'':'disabled'?>>✦ <span class="button-label">Shuffle the deck</span> ✦</button><p class="hint">Tap once. Let anticipation do the rest.</p></section><footer><span><?=count($ideas)?> private possibilities</span><span>Made for two</span></footer></main><script>window.DATE_IDEAS=<?=json_encode($ideas,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>;</script><script src="assets/app.js?v=2" defer></script></body></html>
