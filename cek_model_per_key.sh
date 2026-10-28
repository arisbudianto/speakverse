#!/bin/bash
# Jalankan dari /home/speakver/laravel
#   sed -i 's/\r$//' cek_model_per_key.sh && bash cek_model_per_key.sh
php artisan optimize:clear >/dev/null 2>&1
cat > /tmp/cek_model.php << 'PHP_EOF'
<?php
require getcwd().'/vendor/autoload.php';
$app = require_once getcwd().'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\Http;
$base = rtrim(config('services.dinoiki.base_url'),'/');
echo "Mode rotasi: ".config('services.dinoiki.rotation_mode')."\n\n";
foreach (config('services.dinoiki.pool') as $i => $e) {
  echo "Entri #$i  key=".substr($e['key'],0,6)."..".substr($e['key'],-4)."  model={$e['model']}\n";
  $t = microtime(true);
  // Uji seperti pemakaian nyata: minta JSON, batas token 1400.
  $r = Http::withToken($e['key'])->timeout(90)->post("$base/chat/completions", [
    'model'=>$e['model'],
    'messages'=>[['role'=>'system','content'=>'Return only valid JSON.'],['role'=>'user','content'=>'Return JSON {"ok":true,"score":3}']],
    'max_completion_tokens'=>1400]);
  $ms = round((microtime(true)-$t)*1000);
  echo "   HTTP ".$r->status()." ({$ms} ms)\n";
  if ($r->successful()) {
    $c = data_get($r->json(),'choices.0.message.content');
    echo "   isi    : ".substr(json_encode($c),0,160)."\n";
    echo "   usage  : ".json_encode(data_get($r->json(),'usage.completion_tokens_details'))."\n";
  } else { echo "   ".substr($r->body(),0,300)."\n"; }
}
PHP_EOF
php /tmp/cek_model.php 2>&1 | grep -v "Unsuccessful stat"; rm /tmp/cek_model.php
