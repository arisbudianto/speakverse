@extends('layouts.admin')

@section('content')
    @if (session('success'))
        <div class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 font-semibold text-emerald-700">
            {{ session('success') }}
        </div>
    @endif
    @if (session('error') || $errors->any())
        <div class="mb-6 rounded-2xl border border-red-200 bg-red-50 px-5 py-4 font-semibold text-red-700">
            {{ session('error') ?: $errors->first() }}
        </div>
    @endif

    <div class="mb-8">
        <a href="{{ route('staff.classes.index') }}" class="text-sm font-semibold text-slate-500">← Semua kelas</a>
        <h1 class="mt-4 text-3xl font-black">{{ $class['grade'] }} {{ $class['parallel'] }}</h1>
        <p class="mt-2 text-slate-500">{{ $class['school'] }} · {{ $class['major'] }}</p>
    </div>

    <div class="mb-8 grid gap-6 lg:grid-cols-2">
        <div class="rounded-[28px] border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="mb-4 text-lg font-black">Unggah Excel</h2>
            <p class="mb-4 text-sm text-slate-500">
                Gunakan template resmi. Kolom sekolah/jurusan/kelas/paralel boleh dikosongkan; akan diisi sesuai kelas ini.
            </p>
            <div class="mb-4">
                <a href="{{ route('staff.classes.template') }}"
                    style="display:inline-flex;align-items:center;background:#d97706;color:#fff;font-weight:700;font-size:13px;padding:8px 14px;border-radius:12px;text-decoration:none;">
                    Unduh template .xlsx
                </a>
            </div>
            <form method="POST" action="{{ route('staff.classes.import') }}" enctype="multipart/form-data" class="space-y-4">
                @csrf
                <input type="hidden" name="school" value="{{ $class['school'] }}">
                <input type="hidden" name="major" value="{{ $class['major'] }}">
                <input type="hidden" name="grade" value="{{ $class['grade'] }}">
                <input type="hidden" name="parallel" value="{{ $class['parallel'] }}">
                <input type="file" name="file" accept=".xlsx" required class="block w-full text-sm">
                <button class="rounded-2xl bg-gradient-to-r from-cyan-500 to-blue-600 px-6 py-3 font-bold text-white">Unggah</button>
            </form>
        </div>

        <div class="rounded-[28px] border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="mb-4 text-lg font-black">Tambah siswa manual</h2>
            <form method="POST" action="{{ route('staff.classes.students.store') }}" class="space-y-3">
                @csrf
                <input type="hidden" name="school" value="{{ $class['school'] }}">
                <input type="hidden" name="major" value="{{ $class['major'] }}">
                <input type="hidden" name="grade" value="{{ $class['grade'] }}">
                <input type="hidden" name="parallel" value="{{ $class['parallel'] }}">
                <input name="name" required placeholder="Nama lengkap" class="w-full rounded-2xl border-slate-200 px-4 py-3">
                <input name="email" type="email" required placeholder="Email" class="w-full rounded-2xl border-slate-200 px-4 py-3">
                <input name="password" placeholder="Password (opsional)" class="w-full rounded-2xl border-slate-200 px-4 py-3">
                <button type="submit"
                    style="display:inline-flex;align-items:center;background:#0f172a;color:#fff;font-weight:700;font-size:14px;padding:10px 20px;border-radius:16px;border:none;cursor:pointer;">
                    Tambah
                </button>
            </form>
        </div>
    </div>

    <div class="overflow-x-auto rounded-[28px] border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full text-left">
            <thead class="bg-slate-50 text-sm font-bold text-slate-500">
                <tr>
                    <th class="whitespace-nowrap px-4 py-3">Nama</th>
                    <th class="whitespace-nowrap px-4 py-3">Email</th>
                    <th class="whitespace-nowrap px-4 py-3">Kelas</th>
                    <th class="whitespace-nowrap px-4 py-3">Password</th>
                    <th class="whitespace-nowrap px-4 py-3">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($students as $student)
                    <tr x-data="{ editing: false }" class="border-t border-slate-100 align-middle">
                        <td class="hidden">
                            <form id="update-form-{{ $student->id }}" method="POST" action="{{ route('staff.classes.students.update', $student) }}">
                                @csrf
                                @method('PUT')
                                <input type="hidden" name="school" value="{{ $student->school }}">
                                <input type="hidden" name="major" value="{{ $student->major }}">
                            </form>
                            <form id="delete-form-{{ $student->id }}" method="POST" action="{{ route('staff.classes.students.destroy', $student) }}" onsubmit="return confirm('Hapus siswa ini?')">
                                @csrf
                                @method('DELETE')
                            </form>
                        </td>

                        <td class="whitespace-nowrap px-4 py-2.5">
                            <span x-show="!editing">{{ $student->name }}</span>
                            <input x-show="editing" form="update-form-{{ $student->id }}" name="name" value="{{ $student->name }}"
                                class="w-full min-w-[180px] rounded-xl border-slate-200 px-3 py-2">
                        </td>

                        <td class="whitespace-nowrap px-4 py-2.5">
                            <span x-show="!editing">{{ $student->email }}</span>
                            <input x-show="editing" form="update-form-{{ $student->id }}" name="email" value="{{ $student->email }}"
                                class="w-full min-w-[220px] rounded-xl border-slate-200 px-3 py-2">
                        </td>

                        <td class="whitespace-nowrap px-4 py-2.5">
                            <span x-show="!editing">{{ $student->grade }} {{ $student->parallel }}</span>
                            <div x-show="editing" class="flex gap-1.5">
                                <select form="update-form-{{ $student->id }}" name="grade" class="rounded-xl border-slate-200 px-2 py-2">
                                    @foreach (['X','XI','XII'] as $g)
                                        <option value="{{ $g }}" @selected($student->grade === $g)>{{ $g }}</option>
                                    @endforeach
                                </select>
                                <select form="update-form-{{ $student->id }}" name="parallel" class="rounded-xl border-slate-200 px-2 py-2">
                                    @foreach (['A','B','C','D'] as $p)
                                        <option value="{{ $p }}" @selected($student->parallel === $p)>{{ $p }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </td>

                        <td class="whitespace-nowrap px-4 py-2.5">
                            <span x-show="!editing" class="text-slate-400">••••••••</span>
                            <input x-show="editing" form="update-form-{{ $student->id }}" name="password" placeholder="Password baru (opsional)"
                                class="w-full min-w-[180px] rounded-xl border-slate-200 px-3 py-2">
                        </td>

                        <td class="whitespace-nowrap px-4 py-2.5">
                            <div x-show="!editing" class="flex gap-1.5">
                                <button type="button" @click="editing = true"
                                    class="rounded-lg bg-cyan-100 px-4 py-2 font-bold text-cyan-800 hover:bg-cyan-200">Edit</button>
                                <button type="submit" form="delete-form-{{ $student->id }}"
                                    class="rounded-lg bg-red-500 px-4 py-2 font-bold text-white hover:bg-red-600">Hapus</button>
                            </div>
                            <div x-show="editing" class="flex gap-1.5">
                                <button type="submit" form="update-form-{{ $student->id }}"
                                    class="rounded-lg bg-emerald-200 px-4 py-2 font-bold text-emerald-800 hover:bg-emerald-300">Simpan</button>
                                <button type="button" @click="editing = false"
                                    class="rounded-lg bg-slate-200 px-4 py-2 font-bold text-slate-700 hover:bg-slate-300">Batal</button>
                                <button type="submit" form="delete-form-{{ $student->id }}"
                                    class="rounded-lg bg-red-500 px-4 py-2 font-bold text-white hover:bg-red-600">Hapus</button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-5 py-10 text-center text-slate-500">Belum ada siswa di kelas ini.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
