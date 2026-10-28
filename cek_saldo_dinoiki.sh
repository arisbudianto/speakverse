#!/bin/bash
# Jalankan dari /home/speakver/laravel
#   sed -i 's/\r$//' cek_saldo_dinoiki.sh && bash cek_saldo_dinoiki.sh
php artisan config:clear >/dev/null 2>&1
cat > /tmp/cek_saldo.php << 'PHP_EOF'
<?php
require getcwd().'/vendor/autoload.php';
$app = require_once getcwd().'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$base = rtrim(config('services.dinoiki.base_url'),'/');
$model = config('services.dinoiki.chat_model');
echo "Model: $model\nBase : $base\n\n";
foreach (config('services.dinoiki.keys') as $i => $k) {
  foreach ([20, 1400] as $max) {
    $r = Illuminate\Support\Facades\Http::withToken($k)->timeout(60)->post("$base/chat/completions", [
      'model'=>$model,'messages'=>[['role'=>'user','content'=>'hi']],'max_completion_tokens'=>$max]);
    echo "Key #$i (".substr($k,0,6)."..".substr($k,-4).") max_tokens=$max -> HTTP ".$r->status()."\n";
    if (!$r->successful()) echo "   ".substr($r->body(),0,500)."\n";
  }
}
PHP_EOF
php /tmp/cek_saldo.php 2>&1 | grep -v "Unsuccessful stat"; rm /tmp/cek_saldo.php
