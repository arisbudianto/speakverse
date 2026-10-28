#!/bin/bash
# Jalankan dari /home/speakver/laravel
#   sed -i 's/\r$//' cek_error_terbaru.sh && bash cek_error_terbaru.sh
cat > /tmp/cek_err.php << 'PHP_EOF'
<?php
$f = getcwd().'/storage/logs/laravel.log';
if (!is_file($f)) { echo "laravel.log tidak ditemukan\n"; exit; }
$size = filesize($f); $h = fopen($f,'r'); fseek($h, max(0,$size-6000000)); $t = stream_get_contents($h); fclose($h);
$parts = preg_split('/(?=^\[\d{4}-\d\d-\d\d \d\d:\d\d:\d\d\] )/m', $t, -1, PREG_SPLIT_NO_EMPTY);
$errs = [];
foreach ($parts as $p) {
  if (!preg_match('/^\[(\S+ \S+)\] \w+\.(ERROR|CRITICAL): (.*)/', $p, $m)) continue;
  $first = substr($m[3], 0, 260);
  // baris stack pertama milik aplikasi
  $app = '';
  if (preg_match('/(\/home\/speakver\/laravel\/app\/[^\s:()]+)[:(](\d+)/', $p, $a)) $app = basename($a[1]).':'.$a[2];
  $errs[] = [$m[1], $first, $app];
}
echo "Total error di 6MB log terakhir: ".count($errs)."\n\n== 8 terakhir ==\n";
foreach (array_slice($errs, -8) as $e) echo "{$e[0]}\n  {$e[1]}\n  lokasi di aplikasi: ".($e[2] ?: '-')."\n\n";
PHP_EOF
php /tmp/cek_err.php 2>&1 | grep -v "Unsuccessful stat"; rm /tmp/cek_err.php
