<x-layouts.app title="Pengaturan Akses - {{ config('app.name', 'HRIS') }}" heading="Pengaturan Akses">
    <div class="mx-auto max-w-7xl space-y-6" data-tabs data-tabs-storage-key="access-control-tab">
        <section class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <p class="text-sm font-medium text-gray-500">RBAC & employee login</p>
                <h1 class="mt-1 text-2xl font-semibold text-gray-950">Pengaturan Akses</h1>
            </div>
        </section>

        <section class="rounded-lg border border-gray-200 bg-white p-2 shadow-sm">
            <div class="grid grid-cols-1 gap-2 lg:grid-cols-3" role="tablist" aria-label="Pengaturan akses">
                <button type="button" data-tab-button="roles" class="rounded-md px-4 py-3 text-left text-sm font-medium text-gray-600 transition hover:bg-gray-50" role="tab">
                    Role & Permission
                    <span class="mt-1 block text-xs font-normal text-gray-500">Hak akses menu dan aksi.</span>
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
                <p class="mt-1 text-sm text-gray-500">Atur menu dan aksi yang boleh diakses oleh setiap role.</p>
            </div>

            <div class="divide-y divide-gray-100">
                @foreach ($roles as $role)
                    <form method="POST" action="{{ route('access-control.roles.update', $role) }}" class="grid grid-cols-1 gap-5 p-5 xl:grid-cols-[220px_1fr_auto]">
                        @csrf
                        @method('PUT')

                        <div>
                            <p class="font-medium text-gray-950">{{ $role->name }}</p>
                            <p class="mt-1 text-xs text-gray-500">{{ $role->users_count }} user</p>
                        </div>

                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                            @foreach ($permissions as $group => $items)
                                <div class="rounded-lg border border-gray-200 p-4">
                                    <p class="text-sm font-semibold text-gray-950">{{ str($group)->headline() }}</p>
                                    <div class="mt-3 space-y-2">
                                        @foreach ($items as $permission)
                                            <label class="flex items-center gap-2 text-sm text-gray-600">
                                                <input
                                                    type="checkbox"
                                                    name="permissions[]"
                                                    value="{{ $permission->name }}"
                                                    @checked($role->hasPermissionTo($permission->name))
                                                    class="size-4 rounded border-gray-300 text-primary focus:ring-primary"
                                                    @disabled(! auth()->user()->can('access-control.update') || $role->name === 'superadmin')
                                                >
                                                <span>{{ $permission->name }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div class="flex items-start justify-end">
                            @can('access-control.update')
                                <button type="submit" @disabled($role->name === 'superadmin') class="rounded-md bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-xs transition hover:bg-primary-hover disabled:cursor-not-allowed disabled:bg-gray-300">
                                    Simpan
                                </button>
                            @endcan
                        </div>
                    </form>
                @endforeach
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
