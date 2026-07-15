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
                    Pengguna
                    <span class="mt-1 block text-xs font-normal text-gray-500">Role & cakupan (lokasi/divisi) tiap pengguna.</span>
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
            <div class="flex flex-col justify-between gap-4 border-b border-gray-200 px-5 py-4 sm:flex-row sm:items-end">
                <div>
                    <h2 class="text-base font-semibold text-gray-950">Role & Permission Menu</h2>
                    <p class="mt-1 text-sm text-gray-500">Centang aksi yang boleh dilakukan tiap role pada tiap menu. Sel yang kosong berarti aksi itu memang tidak ada pada menu tersebut.</p>
                </div>
                @can('access-control.update')
                    <form method="POST" action="{{ route('access-control.roles.store') }}" class="flex items-end gap-2">
                        @csrf
                        <div>
                            <label for="new-role-name" class="block text-xs font-medium text-gray-600">Tambah role baru</label>
                            <input id="new-role-name" name="name" required maxlength="50" placeholder="mis. supervisor-cabang" class="mt-1 w-56 rounded-md border border-gray-300 px-3 py-2 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                        </div>
                        <button type="submit" class="rounded-md bg-primary px-4 py-2 text-sm font-semibold text-white shadow-xs transition hover:bg-primary-hover">Tambah</button>
                    </form>
                @endcan
            </div>

            @error('name')<p class="px-5 pt-3 text-sm text-red-600">{{ $message }}</p>@enderror

            <div class="border-b border-gray-100 px-5 py-3">
                <input type="search" data-list-filter="roles" placeholder="Cari role…" class="block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 sm:max-w-xs">
            </div>

            <div class="divide-y divide-gray-100" data-filter-scope="roles">
                <p data-filter-empty hidden class="px-5 py-8 text-center text-sm text-gray-500">Tidak ada role yang cocok.</p>
                @foreach ($roles as $role)
                    @php
                        $isProtected = in_array($role->name, ['superadmin', 'super-admin'], true);
                        $locked = ! auth()->user()->can('access-control.update') || $isProtected;
                        $held = $role->permissions->pluck('name')->all();
                    @endphp
                    <details class="group" data-filter-item data-filter-text="{{ $role->name }}" @if ($loop->first) open @endif>
                        <summary class="flex cursor-pointer items-center justify-between gap-4 px-5 py-4 hover:bg-gray-50">
                            <div>
                                <p class="font-medium text-gray-950">{{ $role->name }}</p>
                                <p class="mt-0.5 text-xs text-gray-500">
                                    {{ $role->users_count }} user · {{ count($held) }} akses
                                    @if ($isProtected) · <span class="text-amber-700">role sistem, tidak dapat diubah/dihapus</span>@endif
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
                                        Simpan Hak Akses
                                    </button>
                                </div>
                            @endcan
                        </form>

                        {{-- Ubah nama / hapus role (bukan role sistem) --}}
                        @can('access-control.update')
                            @unless ($isProtected)
                                <div class="flex flex-col gap-3 border-t border-gray-100 bg-gray-50/60 px-5 py-4 sm:flex-row sm:items-end sm:justify-between">
                                    <form method="POST" action="{{ route('access-control.roles.rename', $role) }}" class="flex items-end gap-2">
                                        @csrf @method('PATCH')
                                        <div>
                                            <label class="block text-xs font-medium text-gray-600">Ubah nama role</label>
                                            <input name="name" value="{{ $role->name }}" required maxlength="50" class="mt-1 w-56 rounded-md border border-gray-300 px-3 py-2 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                                        </div>
                                        <button type="submit" class="rounded-md border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50">Simpan Nama</button>
                                    </form>
                                    <form method="POST" action="{{ route('access-control.roles.destroy', $role) }}" onsubmit="return confirm('Hapus role {{ $role->name }}? Hanya bisa bila tidak ada pengguna yang memakainya.')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="inline-flex items-center gap-1.5 rounded-md border border-red-200 px-3 py-2 text-sm font-medium text-red-600 transition hover:bg-red-50"><x-icon name="trash"/> Hapus Role</button>
                                    </form>
                                </div>
                            @endunless
                        @endcan
                    </details>
                @endforeach
            </div>
        </section>

        <section data-tab-panel="scopes" class="rounded-lg border border-gray-200 bg-white shadow-sm" hidden>
            <div class="border-b border-gray-200 px-5 py-4">
                <h2 class="text-base font-semibold text-gray-950">Role & Cakupan Pengguna</h2>
                <p class="mt-1 text-sm text-gray-500">
                    <span class="font-medium">Role</span> menentukan menu & hak akses pengguna — centang role untuk menetapkannya ke pengguna yang sudah ada.
                    <span class="font-medium">Cakupan</span> membatasi data yang dilihat: karyawan/absensi/jadwal/cuti/laporan hanya untuk lokasi kerja dan divisi yang dipilih (kosong = semua). Pengguna dengan permission <span class="font-medium">lihat semua</span> tidak dibatasi cakupan.
                </p>

                <form method="GET" action="{{ route('access-control.index') }}" class="mt-4 flex flex-col gap-2 sm:flex-row sm:items-end">
                    <div class="flex-1">
                        <label for="user_search" class="block text-xs font-medium text-gray-600">Cari pengguna</label>
                        <input id="user_search" name="user_search" value="{{ $userFilters['search'] }}" placeholder="Nama atau email" class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                    </div>
                    <div>
                        <label for="user_role" class="block text-xs font-medium text-gray-600">Role</label>
                        <select id="user_role" name="user_role" class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 sm:w-52">
                            <option value="">Semua role</option>
                            @foreach ($roles as $role)
                                <option value="{{ $role->name }}" @selected($userFilters['role'] === $role->name)>{{ $role->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex gap-2">
                        <button type="submit" class="rounded-md bg-primary px-4 py-2 text-sm font-semibold text-white shadow-xs transition hover:bg-primary-hover">Filter</button>
                        @if ($userFilters['search'] !== '' || $userFilters['role'] !== '')
                            <a href="{{ route('access-control.index') }}" class="rounded-md border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50">Reset</a>
                        @endif
                    </div>
                </form>
            </div>
            <div class="divide-y divide-gray-100">
                @forelse ($users as $user)
                    @php
                        $seesAllEmployees = $user->seesAllData(\App\Models\User::SCOPE_BYPASS_EMPLOYEES);
                        $seesAllAttendance = $user->seesAllData(\App\Models\User::SCOPE_BYPASS_ATTENDANCE);
                        $selectedBranches = $user->accessBranches->pluck('id')->all();
                        $selectedDepartments = $user->accessDepartments->pluck('id')->all();
                    @endphp
                    @php $userRoles = $user->roles->pluck('name')->all(); @endphp
                    <form method="POST" action="{{ route('access-control.user-scope.update', $user) }}" class="grid grid-cols-1 gap-5 px-5 py-5 lg:grid-cols-[200px_1fr_1fr_1fr_auto]">
                        @csrf
                        @method('PUT')
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-gray-900">{{ $user->name }}</p>
                            <p class="truncate text-xs text-gray-500">{{ $user->email }}</p>
                            <p class="mt-1 text-xs text-gray-500">{{ $userRoles ? implode(', ', $userRoles) : 'Tanpa role' }}</p>
                            @if ($seesAllEmployees && $seesAllAttendance)
                                <p class="mt-2 inline-flex rounded-md bg-emerald-50 px-2 py-0.5 text-[11px] font-medium text-emerald-700">Lihat semua data</p>
                            @elseif ($user->isLimitedToSubordinates())
                                <p class="mt-2 inline-flex rounded-md bg-sky-50 px-2 py-0.5 text-[11px] font-medium text-sky-700">Hanya bawahannya</p>
                            @elseif ($selectedBranches === [] && $selectedDepartments === [])
                                <p class="mt-2 inline-flex rounded-md bg-amber-50 px-2 py-0.5 text-[11px] font-medium text-amber-800">Belum ada cakupan — tidak melihat data</p>
                            @endif
                            <label class="mt-3 flex items-start gap-2 text-xs text-gray-600">
                                <input type="checkbox" name="limit_to_subordinates" value="1" @checked($user->isLimitedToSubordinates()) class="mt-0.5 size-3.5 rounded border-gray-300 text-primary focus:ring-primary">
                                <span>Batasi ke bawahan saja <span class="block text-gray-400">Hanya melihat karyawan di bawah garis atasannya (mengabaikan lokasi/divisi di atas).</span></span>
                            </label>
                        </div>
                        <div>
                            <p class="text-xs font-medium text-gray-700">Role</p>
                            <div class="mt-2 max-h-40 space-y-1.5 overflow-y-auto rounded-md border border-gray-200 p-2.5">
                                @forelse ($roles as $role)
                                    <label class="flex items-center gap-2 text-xs text-gray-700">
                                        <input type="checkbox" name="roles[]" value="{{ $role->name }}" @checked(in_array($role->name, $userRoles, true)) class="size-3.5 rounded border-gray-300 text-primary focus:ring-primary">
                                        {{ $role->name }}
                                    </label>
                                @empty
                                    <p class="text-xs text-gray-400">Belum ada role.</p>
                                @endforelse
                            </div>
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
                @empty
                    <p class="px-5 py-10 text-center text-sm text-gray-500">Tidak ada pengguna yang cocok dengan filter.</p>
                @endforelse
            </div>
            <div class="border-t border-gray-200 px-5 py-3">
                {{ $users->links() }}
            </div>
        </section>

        <section data-tab-panel="positions" class="rounded-lg border border-gray-200 bg-white shadow-sm" hidden>
            <div class="border-b border-gray-200 px-5 py-4">
                <h2 class="text-base font-semibold text-gray-950">Role Default Per Jabatan</h2>
                <p class="mt-1 text-sm text-gray-500">Akun karyawan baru akan memakai role default dari jabatan bila role tidak dipilih manual.</p>
                <div class="mt-4 flex flex-col gap-2 sm:flex-row">
                    <input type="search" data-list-filter="positions" placeholder="Cari jabatan…" class="block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 sm:max-w-xs">
                    <select data-list-filter-select="positions" class="block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 sm:w-52">
                        <option value="">Semua divisi</option>
                        @foreach ($departments as $department)
                            <option value="{{ $department->id }}">{{ $department->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="divide-y divide-gray-100" data-filter-scope="positions">
                <p data-filter-empty hidden class="px-5 py-8 text-center text-sm text-gray-500">Tidak ada jabatan yang cocok.</p>
                @foreach ($jobPositions as $jobPosition)
                    <form method="POST" action="{{ route('access-control.job-positions.update', $jobPosition) }}" data-filter-item data-filter-text="{{ $jobPosition->name }} {{ $jobPosition->code }}" data-filter-tags="{{ $jobPosition->activeDepartments->pluck('id')->join(',') }}" class="grid grid-cols-1 gap-4 p-5 md:grid-cols-[1fr_220px_auto] md:items-end">
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
                <input type="search" data-list-filter="locations" placeholder="Cari lokasi atau kota…" class="mt-4 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 sm:max-w-xs">
            </div>

            <div class="divide-y divide-gray-100" data-filter-scope="locations">
                <p data-filter-empty hidden class="px-5 py-8 text-center text-sm text-gray-500">Tidak ada lokasi yang cocok.</p>
                @foreach ($branches as $branch)
                    @php
                        $selectedDepartmentIds = $branch->departments->pluck('id');
                        $primaryDepartmentId = $branch->departments->firstWhere('pivot.is_primary', true)?->id;
                    @endphp

                    <form method="POST" action="{{ route('access-control.branches.departments.update', $branch) }}" data-filter-item data-filter-text="{{ $branch->name }} {{ $branch->city }} {{ $branch->code }}" class="grid grid-cols-1 gap-5 p-5 xl:grid-cols-[260px_1fr_auto]">
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
