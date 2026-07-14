<x-layouts.app title="Pengaturan Akses - {{ config('app.name', 'HRIS') }}" heading="Pengaturan Akses">
    <div class="mx-auto max-w-7xl space-y-6" data-tabs data-tabs-storage-key="access-control-tab">
        <section class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <p class="text-sm font-medium text-gray-500">RBAC & employee login</p>
                <h1 class="mt-1 text-2xl font-semibold text-gray-950">Pengaturan Akses</h1>
            </div>
        </section>

        <section class="rounded-lg border border-gray-200 bg-white p-2 shadow-sm">
            <div class="grid grid-cols-1 gap-2 lg:grid-cols-4" role="tablist" aria-label="Pengaturan akses">
                <button type="button" data-tab-button="roles" class="rounded-md px-4 py-3 text-left text-sm font-medium text-gray-600 transition hover:bg-gray-50" role="tab">
                    Role & Permission
                    <span class="mt-1 block text-xs font-normal text-gray-500">Hak akses menu dan aksi.</span>
                </button>
                <button type="button" data-tab-button="scopes" class="rounded-md px-4 py-3 text-left text-sm font-medium text-gray-600 transition hover:bg-gray-50" role="tab">
                    Cakupan Data
                    <span class="mt-1 block text-xs font-normal text-gray-500">Lokasi & divisi yang boleh dilihat tiap pengguna.</span>
                </button>
                <button type="button" data-tab-button="positions" class="rounded-md px-4 py-3 text-left text-sm font-medium text-gray-600 transition hover:bg-gray-50" role="tab">
                    Role Jabatan
                    <span class="mt-1 block text-xs font-normal text-gray-500">Default role per posisi.</span>
                </button>
                <button type="button" data-tab-button="locations" class="rounded-md px-4 py-3 text-left text-sm font-medium text-gray-600 transition hover:bg-gray-50" role="tab">
                    Lokasi & Divisi
                    <span class="mt-1 block text-xs font-normal text-gray-500">Divisi yang tersedia di lokasi kerja.</span>
                </button>
            </div>
        </section>

        <section data-tab-panel="roles" class="rounded-lg border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-200 px-5 py-4">
                <h2 class="text-base font-semibold text-gray-950">Role & Permission Menu</h2>
                <p class="mt-1 text-sm text-gray-500">Centang aksi yang boleh dilakukan tiap role pada tiap menu. Sel yang kosong berarti aksi itu memang tidak ada pada menu tersebut.</p>
            </div>

            <div class="divide-y divide-gray-100">
                @foreach ($roles as $role)
                    @php
                        $locked = ! auth()->user()->can('access-control.update') || $role->name === 'superadmin';
                        $held = $role->permissions->pluck('name')->all();
                    @endphp
                    <details class="group" @if ($loop->first) open @endif>
                        <summary class="flex cursor-pointer items-center justify-between gap-4 px-5 py-4 hover:bg-gray-50">
                            <div>
                                <p class="font-medium text-gray-950">{{ $role->name }}</p>
                                <p class="mt-0.5 text-xs text-gray-500">
                                    {{ $role->users_count }} user · {{ count($held) }} akses
                                    @if ($role->name === 'superadmin') · <span class="text-amber-700">akses penuh, tidak dapat diubah</span>@endif
                                </p>
                            </div>
                            <span class="text-xs text-gray-400 group-open:hidden">Buka</span>
                            <span class="hidden text-xs text-gray-400 group-open:inline">Tutup</span>
                        </summary>

                        <form method="POST" action="{{ route('access-control.roles.update', $role) }}" class="px-5 pb-5" data-role-matrix>
                            @csrf
                            @method('PUT')

                            <div class="overflow-x-auto rounded-lg border border-gray-200">
                                <table class="w-full text-sm">
                                    <thead>
                                        <tr class="bg-gray-50 text-xs text-gray-600">
                                            <th class="px-4 py-2.5 text-left font-semibold">Menu</th>
                                            @foreach ($actions as $action => $actionLabel)
                                                <th class="px-3 py-2.5 text-center font-semibold">{{ $actionLabel }}</th>
                                            @endforeach
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($matrix as $group => $rows)
                                            <tr class="bg-gray-50/60">
                                                <td colspan="{{ count($actions) + 1 }}" class="px-4 py-1.5 text-[11px] font-semibold uppercase tracking-wide text-gray-500">{{ $group }}</td>
                                            </tr>
                                            @foreach ($rows as $row)
                                                <tr class="border-t border-gray-100">
                                                    <td class="px-4 py-2 text-gray-800">
                                                        <label class="inline-flex items-center gap-2">
                                                            <input type="checkbox" data-row-toggle class="size-3.5 rounded border-gray-300 text-primary focus:ring-primary" @disabled($locked)>
                                                            {{ $row['label'] }}
                                                        </label>
                                                    </td>
                                                    @foreach ($actions as $action => $actionLabel)
                                                        @php $permission = $row['cells'][$action] ?? null; @endphp
                                                        <td class="px-3 py-2 text-center">
                                                            @if ($permission)
                                                                <input
                                                                    type="checkbox"
                                                                    name="permissions[]"
                                                                    value="{{ $permission }}"
                                                                    title="{{ $permission }}"
                                                                    @checked(in_array($permission, $held, true))
                                                                    class="size-4 rounded border-gray-300 text-primary focus:ring-primary"
                                                                    @disabled($locked)
                                                                >
                                                            @else
                                                                <span class="text-gray-300">—</span>
                                                            @endif
                                                        </td>
                                                    @endforeach
                                                </tr>
                                            @endforeach
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>

                            @can('access-control.update')
                                <div class="mt-4 flex justify-end">
                                    <button type="submit" @disabled($locked) class="rounded-md bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-xs transition hover:bg-primary-hover disabled:cursor-not-allowed disabled:bg-gray-300">
                                        Simpan
                                    </button>
                                </div>
                            @endcan
                        </form>
                    </details>
                @endforeach
            </div>
        </section>

        <section data-tab-panel="scopes" class="rounded-lg border border-gray-200 bg-white shadow-sm" hidden>
            <div class="border-b border-gray-200 px-5 py-4">
                <h2 class="text-base font-semibold text-gray-950">Cakupan Data Pengguna</h2>
                <p class="mt-1 text-sm text-gray-500">
                    Batasi data yang dilihat seorang pengguna: karyawan, absensi, jadwal, cuti, dan laporan hanya untuk lokasi kerja <span class="font-medium">dan</span> divisi yang dipilih.
                    Lokasi kosong = semua lokasi; divisi kosong = semua divisi. Pengguna yang memegang permission <span class="font-medium">lihat semua</span> (mis. HR pusat) tidak dibatasi.
                </p>
            </div>
            <div class="divide-y divide-gray-100">
                @foreach ($users as $user)
                    @php
                        $seesAllEmployees = $user->seesAllData(\App\Models\User::SCOPE_BYPASS_EMPLOYEES);
                        $seesAllAttendance = $user->seesAllData(\App\Models\User::SCOPE_BYPASS_ATTENDANCE);
                        $selectedBranches = $user->accessBranches->pluck('id')->all();
                        $selectedDepartments = $user->accessDepartments->pluck('id')->all();
                    @endphp
                    <form method="POST" action="{{ route('access-control.user-scope.update', $user) }}" class="grid grid-cols-1 gap-5 px-5 py-5 lg:grid-cols-[220px_1fr_1fr_auto]">
                        @csrf
                        @method('PUT')
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-gray-900">{{ $user->name }}</p>
                            <p class="truncate text-xs text-gray-500">{{ $user->email }}</p>
                            <p class="mt-1 text-xs text-gray-500">{{ $user->roles->pluck('name')->join(', ') ?: 'Tanpa role' }}</p>
                            @if ($seesAllEmployees && $seesAllAttendance)
                                <p class="mt-2 inline-flex rounded-md bg-emerald-50 px-2 py-0.5 text-[11px] font-medium text-emerald-700">Lihat semua data</p>
                            @elseif ($selectedBranches === [] && $selectedDepartments === [])
                                <p class="mt-2 inline-flex rounded-md bg-amber-50 px-2 py-0.5 text-[11px] font-medium text-amber-800">Belum ada cakupan — tidak melihat data</p>
                            @endif
                        </div>
                        <div>
                            <p class="text-xs font-medium text-gray-700">Lokasi Kerja</p>
                            <div class="mt-2 max-h-40 space-y-1.5 overflow-y-auto rounded-md border border-gray-200 p-2.5">
                                @forelse ($branches as $branch)
                                    <label class="flex items-center gap-2 text-xs text-gray-700">
                                        <input type="checkbox" name="branches[]" value="{{ $branch->id }}" @checked(in_array($branch->id, $selectedBranches, true)) class="size-3.5 rounded border-gray-300 text-primary focus:ring-primary">
                                        {{ $branch->name }}
                                    </label>
                                @empty
                                    <p class="text-xs text-gray-400">Belum ada lokasi kerja.</p>
                                @endforelse
                            </div>
                        </div>
                        <div>
                            <p class="text-xs font-medium text-gray-700">Divisi</p>
                            <div class="mt-2 max-h-40 space-y-1.5 overflow-y-auto rounded-md border border-gray-200 p-2.5">
                                @forelse ($departments as $department)
                                    <label class="flex items-center gap-2 text-xs text-gray-700">
                                        <input type="checkbox" name="departments[]" value="{{ $department->id }}" @checked(in_array($department->id, $selectedDepartments, true)) class="size-3.5 rounded border-gray-300 text-primary focus:ring-primary">
                                        {{ $department->name }}
                                    </label>
                                @empty
                                    <p class="text-xs text-gray-400">Belum ada divisi.</p>
                                @endforelse
                            </div>
                        </div>
                        <div class="flex items-start justify-end">
                            @can('access-control.update')
                                <button type="submit" class="rounded-md bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-xs transition hover:bg-primary-hover">Simpan</button>
                            @endcan
                        </div>
                    </form>
                @endforeach
            </div>
            <div class="border-t border-gray-200 px-5 py-3">
                {{ $users->links() }}
            </div>
        </section>

        <section data-tab-panel="positions" class="rounded-lg border border-gray-200 bg-white shadow-sm" hidden>
            <div class="border-b border-gray-200 px-5 py-4">
                <h2 class="text-base font-semibold text-gray-950">Role Default Per Jabatan</h2>
                <p class="mt-1 text-sm text-gray-500">Akun karyawan baru akan memakai role default dari jabatan bila role tidak dipilih manual.</p>
            </div>
            <div class="divide-y divide-gray-100">
                @foreach ($jobPositions as $jobPosition)
                    <form method="POST" action="{{ route('access-control.job-positions.update', $jobPosition) }}" class="grid grid-cols-1 gap-4 p-5 md:grid-cols-[1fr_220px_auto] md:items-end">
                        @csrf
                        @method('PUT')

                        <div>
                            <p class="font-medium text-gray-950">{{ $jobPosition->name }}</p>
                            <p class="mt-1 text-xs text-gray-500">{{ $jobPosition->activeDepartments->pluck('name')->join(', ') ?: 'Belum dipetakan ke divisi' }}</p>
                        </div>
                        <div>
                            <label for="default_role_id_{{ $jobPosition->id }}" class="block text-sm font-medium text-gray-700">Role Default</label>
                            <select id="default_role_id_{{ $jobPosition->id }}" name="default_role_id" class="mt-2 rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20" @disabled(! auth()->user()->can('access-control.update'))>
                                <option value="">Tidak ada default</option>
                                @foreach ($roles as $role)
                                    <option value="{{ $role->id }}" @selected($jobPosition->default_role_id === $role->id)>{{ $role->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        @can('access-control.update')
                            <button type="submit" class="rounded-md bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-xs transition hover:bg-primary-hover">Simpan</button>
                        @endcan
                    </form>
                @endforeach
            </div>
        </section>

        <section data-tab-panel="locations" class="rounded-lg border border-gray-200 bg-white shadow-sm" hidden>
            <div class="border-b border-gray-200 px-5 py-4">
                <h2 class="text-base font-semibold text-gray-950">Klasifikasi Lokasi & Divisi</h2>
                <p class="mt-1 text-sm text-gray-500">Atur divisi mana saja yang tersedia di setiap lokasi kerja. Pilihan ini dipakai di form karyawan.</p>
            </div>

            <div class="divide-y divide-gray-100">
                @foreach ($branches as $branch)
                    @php
                        $selectedDepartmentIds = $branch->departments->pluck('id');
                        $primaryDepartmentId = $branch->departments->firstWhere('pivot.is_primary', true)?->id;
                    @endphp

                    <form method="POST" action="{{ route('access-control.branches.departments.update', $branch) }}" class="grid grid-cols-1 gap-5 p-5 xl:grid-cols-[260px_1fr_auto]">
                        @csrf
                        @method('PUT')

                        <div>
                            <p class="font-medium text-gray-950">{{ $branch->name }}</p>
                            <p class="mt-1 text-xs text-gray-500">{{ $branch->city ?? '-' }} · {{ str($branch->type)->headline() }}</p>
                        </div>

                        <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
                            @foreach ($departments as $department)
                                <div class="rounded-lg border border-gray-200 p-4">
                                    <label class="flex items-start gap-2 text-sm text-gray-700">
                                        <input
                                            type="checkbox"
                                            name="departments[]"
                                            value="{{ $department->id }}"
                                            @checked($selectedDepartmentIds->contains($department->id))
                                            class="mt-0.5 size-4 rounded border-gray-300 text-primary focus:ring-primary"
                                            @disabled(! auth()->user()->can('access-control.update'))
                                        >
                                        <span>
                                            <span class="block font-medium text-gray-950">{{ $department->name }}</span>
                                            <span class="mt-1 block text-xs text-gray-500">{{ $department->code ?? '-' }}</span>
                                        </span>
                                    </label>
                                    <label class="mt-3 flex items-center gap-2 text-xs text-gray-500">
                                        <input
                                            type="radio"
                                            name="primary_department_id"
                                            value="{{ $department->id }}"
                                            @checked($primaryDepartmentId === $department->id)
                                            class="size-4 border-gray-300 text-primary focus:ring-primary"
                                            @disabled(! auth()->user()->can('access-control.update'))
                                        >
                                        Divisi utama
                                    </label>
                                </div>
                            @endforeach
                        </div>

                        <div class="flex items-start justify-end">
                            @can('access-control.update')
                                <button type="submit" class="rounded-md bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-xs transition hover:bg-primary-hover">Simpan</button>
                            @endcan
                        </div>
                    </form>
                @endforeach
            </div>
        </section>
    </div>
</x-layouts.app>
