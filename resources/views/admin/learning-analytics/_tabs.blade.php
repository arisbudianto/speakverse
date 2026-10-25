@php
    $tabs = [
        'admin.la.engagement' => 'Keterlibatan',
        'admin.la.outcomes' => 'Hasil Belajar',
        'admin.la.items' => 'Kualitas Soal',
    ];
@endphp
<div class="flex flex-wrap gap-2">
    @foreach ($tabs as $r => $label)
        <a href="{{ route($r) }}"
           class="rounded-2xl px-4 py-2 text-sm font-bold {{ request()->routeIs($r . '*') ? 'bg-slate-900 text-white dark:bg-white dark:text-slate-900' : 'border border-slate-300 hover:bg-slate-100 dark:border-white/10 dark:hover:bg-white/5' }}">{{ $label }}</a>
    @endforeach
</div>
