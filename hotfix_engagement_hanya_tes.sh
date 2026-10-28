#!/bin/bash
# Perbaikan cepat (commit 3374283): error "Undefined array key hanya_tes" di tab Keterlibatan
# (muncul bila ada siswa dengan pola "hanya ikut tes", ditandai Tahap 2).
# Jalankan dari /home/speakver/laravel:
#   sed -i 's/\r$//' hotfix_engagement_hanya_tes.sh && bash hotfix_engagement_hanya_tes.sh
SHA=3374283c93d0b1003c0cdfa40db768cd592dac3d
RAW="https://raw.githubusercontent.com/arisbudianto/speakverse/$SHA"
P=resources/views/admin/learning-analytics/engagement.blade.php
POST=965093058a0bce9981283e918ec6883cbd6da52aaee3615fc193bebe908ba165
PRE=699694d7142e20e9f027226357c252414c2db03e6bd37947fd5c3e478bd50660
h() { sha256sum "$1" | cut -d' ' -f1; }
[ -f artisan ] || { echo "Jalankan dari folder Laravel."; exit 1; }
c=$(h "$P")
if [ "$c" = "$POST" ]; then echo "Sudah terpasang."; exit 0; fi
if [ "$c" != "$PRE" ]; then echo "BERHENTI: $P di server berbeda dari yang diharapkan ($c). Tidak ada yang diubah."; exit 2; fi
T=$(mktemp)
curl -fsSL "$RAW/$P" -o "$T" || { echo "GAGAL mengunduh."; exit 3; }
[ "$(h $T)" = "$POST" ] || { echo "GAGAL: checksum tidak cocok. Tidak ada yang diubah."; exit 4; }
BK="storage/backup-hotfix-engagement-$(date +%Y%m%d-%H%M%S).blade.php"
cp "$P" "$BK"
cp "$T" "$P"
php artisan optimize:clear
echo "Selesai. Cadangan: $BK"
