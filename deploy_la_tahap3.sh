#!/bin/bash
# Learning Analytics TAHAP 3 (commit f49576c): filter kohort global, gabung akun ganda,
# pencatatan kunjungan halaman siswa, model multilevel, laporan cetak/PDF.
# SYARAT: deploy_la_tahap2.sh sudah dijalankan.
#   bash deploy_la_tahap3.sh        -> pasang (cadangan + migrasi)
#   bash deploy_la_tahap3.sh lepas  -> kembalikan berkas dan batalkan migrasi Tahap 3
# Jalankan dari /home/speakver/laravel.
SHA=f49576c7ca68e030a0569b36fe072edcabef5ee2
RAW="https://raw.githubusercontent.com/arisbudianto/speakverse/$SHA"
MIG=database/migrations/2026_11_10_000001_la_tahap3_log_halaman_gabung_akun.php
MOD="app/Http/Controllers/Admin/LearningAnalyticsCohortController.php:cb0aa1381d68b801ec27cd24474f5e58498882d19a8a0099a6e86dcef8ab2986:602c2b7043515141014470b33844ca6b5444de5ad8b81f8766ff7dfe2578a4bd
app/Http/Controllers/Admin/LearningAnalyticsOutcomeController.php:c566ad6d9f602cbb2f5cd45f83e7a6f5c741c60a37ec4366b494e342bf0cfded:43c04e0a7d6461f75e6cf25e3a24c0ab28d6ae100adee1feca04f654dce03f9a
app/Http/Controllers/Admin/LearningAnalyticsResearchController.php:3b10278bfd883432b9e6dc1be16f0c7fac99a48baf5d695a9808e40fbdce8b01:ae9f166a028bb11d17085b6c4e08517598a9223a7fca5d9fc6eaafdaaa65e680
app/Http/Controllers/StudentProgressController.php:a1373e6d6085078f8ab5d4f6d92e743dec111246fe1cb23e7dc1f9cb5d20cb5d:67087730a1fa534395cbcfe35f262f806bc10f864e7a42a1e33c825e5b08ca2e
app/Services/LearningAnalytics/Cohort.php:aaddc26d300de7a8ed62594243137c8e730a22e924b8a67e6a2b1d24a93efbde:9cb2233966a88b4e99a95ada40cfa6c2d4deb16c3d18282506896c891f7a2885
app/Services/LearningAnalytics/Engagement/ActivityAggregateService.php:5f068b3273ea51a1aa5169c6799be11623bc822025f3ea798b2f464b2561d4c1:6f4bb199b8467df966d700da861e0603eaccfd7602bf23dde95c37f397bdbf2d
app/Services/LearningAnalytics/Engagement/DaysLoader.php:ffe29ceca1df04f9597a836355a28ee596abd60c87e7a5c19d7131e06d55fe39:6093820a5828cc84d3a6ff4f250dac500f1fb0c34fd4fafe580f935fc3d061d2
app/Services/LearningAnalytics/Engagement/EngagementMetrics.php:ead1298c91234dd67d6f7764d2c744bbb5e4adb2f31f5bcf3edb1a0afa753f90:91b43d363ca8422edfe7232b41dec0ba5fbce024e755374e75f620c48fa9056d
app/Services/LearningAnalytics/Intervention/InterventionService.php:4818727ca802a55910e33dc84f87efc3cbd7ba91ce5a646ce351a0cbe38d282a:ccca35ae2a74e67e9c093e9a06199adbb2b531c06e504b1f5810897a0f7d9ef4
bootstrap/app.php:e8a2d3613e03679473654343e626469179d78336a3e442eb3bd8f954052b7463:9f7de9407fbc3a68654e5dec4b33a42b8dce30d3ff01b60d8ee84dce1715ede3
config/learning_analytics.php:f9cf99053c9463319c4ee4341dd7565ed74a95bad103d38a34be7bfd9fe0bdf3:34acbb17cb5116355c2a3f4724f90569206f5e7ab7d9a760350671f91c5a1a24
resources/views/admin/learning-analytics/_tabs.blade.php:735325e080aa89cbf7960c4c15522121e0074db35b5c322fa7108fe0cc3ab5aa:3b035bd57fa672ef7a128e04531548e2d2d807e4468f17a6a63ae521a9f8ac02
resources/views/admin/learning-analytics/cohort.blade.php:8efcf2c54e740b7ebc61fe46f604525b238b3466a9163e43dc85066f9ff11d26:c7b7d259e66d8e9c5542c24d09679f8157eec3ffca918584d6bcdb29e4b96eca
resources/views/admin/learning-analytics/engagement.blade.php:699694d7142e20e9f027226357c252414c2db03e6bd37947fd5c3e478bd50660:c16bcfc61bbeb0cdab09a7bf85d204074bcf53b494d75a3f951760d51f22270b
resources/views/admin/learning-analytics/method.blade.php:daa25eacc661ee4c96493c6615a7a8a1d46be64382011f023829d04beb5535c3:d39b78a3398b02417ef5af63c75b3c6e773ab4350352df1332be8a1de540614c
resources/views/admin/learning-analytics/outcomes.blade.php:cdac3167d673edfec4388ec226991741ef204a530d30946bf18eaa8a23e6f76c:6e35391e3f362e493c1518e061a8695c540caf9bd049eca72bbf82700c7f4e05
resources/views/partials/consent-modal.blade.php:53390a434bd242595b3ab0824a17bd45b31cac027ef247feb11dff9f9cfa0c76:f23dc0a736b3f3d1bd2e12bfcbcd2477f38dc28d1281d3a97a03041469a32db0
routes/web.php:541cb049637f50b93627891819f137831481c4cb228cfda77ab0322a4095d5fa:776069ab2b8a610de1798372f8f6e697a716abfda6a159b277a0259da41cfb9f"
NEW="app/Http/Controllers/Admin/LearningAnalyticsReportController.php:b3648923f49872a687ddf73cf711473a946805305aba819a9f934771fb962eb1
app/Http/Middleware/LogStudentPageView.php:4b765849dcd8f10caea60a4add4d7ef536922de43889bf6facb99c571f16ec81
app/Services/LearningAnalytics/AccountMerger.php:4ea902bc05466b8286fe32b9db121ad1fec2f845b0b47a34e405040a405a4f6c
app/Services/LearningAnalytics/Outcome/MultilevelOutcome.php:21b0c18dd01683d4124b7b4f2b932a4b86e059f3bf613895cdabd4c6e5262f3b
app/Services/LearningAnalytics/Stats/MixedModel.php:2d4e37c98820d3fd287b94459eb20ab473f65d19d11052c61b778b2b4264fef6
database/migrations/2026_11_10_000001_la_tahap3_log_halaman_gabung_akun.php:446b7967131d080ee5ba8771baaef823239789c2543143d006cf137ed4725bbc
resources/views/admin/learning-analytics/merge.blade.php:f18f073232482422cf300bad8e06c2376514f467023c17a1eb8b5f907be3d319
resources/views/admin/learning-analytics/report.blade.php:b5f5756c24b5767a55f6764f5d9af1e3b8cea30b4f4bdaa378e32ce889f00e19"
h() { sha256sum "$1" | cut -d' ' -f1; }
[ -f artisan ] || { echo "Jalankan dari folder Laravel."; exit 1; }

if [ "$1" = "lepas" ]; then
  B=$(ls -d storage/backup-la3-* 2>/dev/null | tail -n1)
  [ -z "$B" ] && { echo "Tidak ada cadangan."; exit 1; }
  php artisan migrate:rollback --force --path=$MIG
  for e in $MOD; do p=${e%%:*}; cp "$B/$(echo $p | tr / _)" "$p"; done
  for e in $NEW; do rm -f "${e%%:*}"; done
  rmdir app/Services/LearningAnalytics/Research 2>/dev/null
  php artisan optimize:clear; echo "Dikembalikan dari $B"; exit 0
fi

[ -f resources/views/partials/consent-modal.blade.php ] || { echo "BERHENTI: jalankan deploy_la_tahap2.sh lebih dulu."; exit 2; }
ok=1; for e in $MOD; do IFS=: read p post pre <<<"$e"; [ "$(h $p)" = "$post" ] || ok=0; done
[ $ok = 1 ] && [ -f resources/views/admin/learning-analytics/report.blade.php ] && { echo "Sudah terpasang."; exit 0; }
for e in $MOD; do IFS=: read p post pre <<<"$e"; c=$(h $p)
  [ "$c" = "$pre" ] || { echo "BERHENTI: $p di server berbeda dari yang diharapkan ($c). Tidak ada yang diubah."; exit 2; }; done

T=$(mktemp -d)
for e in $MOD; do IFS=: read p post pre <<<"$e"; f="$T/$(echo $p | tr / _)"
  curl -fsSL "$RAW/$p" -o "$f" || { echo "GAGAL mengunduh $p"; exit 3; }
  [ "$(h $f)" = "$post" ] || { echo "GAGAL: checksum $p. Tidak ada yang diubah."; exit 4; }; done
for e in $NEW; do p=${e%%:*}; f="$T/n_$(echo $p | tr / _)"
  curl -fsSL "$RAW/$p" -o "$f" || { echo "GAGAL mengunduh $p"; exit 3; }
  [ "$(h $f)" = "${e##*:}" ] || { echo "GAGAL: checksum $p. Tidak ada yang diubah."; exit 4; }; done

BK=storage/backup-la3-$(date +%Y%m%d-%H%M%S); mkdir -p "$BK"
for e in $MOD; do p=${e%%:*}; cp "$p" "$BK/$(echo $p | tr / _)"; done
for e in $NEW; do p=${e%%:*}; mkdir -p "$(dirname $p)"; cp "$T/n_$(echo $p | tr / _)" "$p"; done
for e in $MOD; do p=${e%%:*}; cp "$T/$(echo $p | tr / _)" "$p"; done
php artisan optimize:clear
php artisan migrate --force --path=$MIG || { echo "Migrasi gagal. Jalankan: bash deploy_la_tahap3.sh lepas"; exit 5; }
echo "Selesai. Cadangan: $BK  (batalkan: bash deploy_la_tahap3.sh lepas)"
echo "Baru: Analitik Lanjutan > Laporan (cetak/PDF), filter Kohort di bar atas tiap tab, Kohort > Gabungkan akun ganda."
echo "Pencatatan kunjungan halaman aktif otomatis (nonaktifkan dengan LA_PAGE_LOG=false di .env bila tidak diinginkan)."
echo "Model multilevel tampil di tab Hasil Belajar bila data memenuhi syarat (>=3 kelas, >=5 siswa berpasangan/kelas)."
