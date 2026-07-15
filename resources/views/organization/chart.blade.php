<x-layouts.app title="Bagan Organisasi - {{ config('app.name', 'HRIS') }}" heading="Bagan Organisasi">
    <div class="mx-auto max-w-6xl space-y-6">
        <section class="flex flex-col justify-between gap-4 lg:flex-row lg:items-end">
            <div>
                <p class="text-sm font-medium text-gray-500">Struktur pelaporan karyawan</p>
                <h1 class="mt-1 text-2xl font-semibold text-gray-950">Bagan Organisasi</h1>
                <p class="mt-1 text-sm text-gray-500">Disusun otomatis dari atasan langsung (manager) tiap karyawan aktif. Klik nama untuk membuka detailnya.</p>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" data-org-expand-all class="rounded-md border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50">Buka semua</button>
                <button type="button" data-org-collapse-all class="rounded-md border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50">Tutup semua</button>
            </div>
        </section>

        @if ($hasNoScope)
            <div class="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                Cakupan akses Anda belum diatur, jadi belum ada data yang bisa ditampilkan. Minta admin menetapkan lokasi kerja / divisi Anda di menu <span class="font-medium">Kontrol Akses</span>.
            </div>
        @endif

        <section class="grid grid-cols-3 gap-3">
            <x-stat-card label="Karyawan Ditampilkan" :value="$summary['shown']" tone="primary"><x-icon name="users" class="size-5"/></x-stat-card>
            <x-stat-card label="Punya Bawahan" :value="$summary['managers']" tone="emerald"><x-icon name="user-check" class="size-5"/></x-stat-card>
            <x-stat-card label="Puncak Bagan" :value="$summary['roots']" tone="sky"><x-icon name="briefcase" class="size-5"/></x-stat-card>
        </section>

        <section class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
            <form method="GET" action="{{ route('organization.chart') }}" class="grid grid-cols-1 gap-3 sm:grid-cols-3 lg:items-end">
                <div>
                    <label for="branch_id" class="block text-xs font-medium text-gray-600">Lokasi</label>
                    <select id="branch_id" name="branch_id" onchange="this.form.submit()" class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                        <option value="">Semua lokasi</option>
                        @foreach ($branches as $branch)
                            <option value="{{ $branch->id }}" @selected($branchId === $branch->id)>{{ $branch->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="department_id" class="block text-xs font-medium text-gray-600">Divisi</label>
                    <select id="department_id" name="department_id" onchange="this.form.submit()" class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                        <option value="">Semua divisi</option>
                        @foreach ($departments as $department)
                            <option value="{{ $department->id }}" @selected($departmentId === $department->id)>{{ $department->name }}</option>
                        @endforeach
                    </select>
                </div>
                @if ($branchId || $departmentId)
                    <div>
                        <a href="{{ route('organization.chart') }}" class="inline-flex rounded-md border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Reset filter</a>
                    </div>
                @endif
            </form>
        </section>

        <section class="overflow-x-auto rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
            @forelse ($tree as $node)
                @include('organization.partials.node', ['node' => $node, 'depth' => 0])
            @empty
                <p class="px-2 py-8 text-center text-sm text-gray-400">Belum ada karyawan aktif untuk ditampilkan pada bagan.</p>
            @endforelse
        </section>
    </div>

    @push('scripts')
    <script>
        (function () {
            const setNode = (button, open) => {
                const node = button.closest('[data-org-node]');
                const children = node?.querySelector(':scope > [data-org-children]');
                if (!children) return;
                children.hidden = !open;
                button.setAttribute('aria-expanded', String(open));
                button.classList.toggle('rotate-90', open);
            };

            document.querySelectorAll('[data-org-toggle]').forEach((button) => {
                button.addEventListener('click', () => {
                    const node = button.closest('[data-org-node]');
                    const children = node?.querySelector(':scope > [data-org-children]');
                    setNode(button, Boolean(children?.hidden));
                });
            });

            document.querySelector('[data-org-expand-all]')?.addEventListener('click', () => {
                document.querySelectorAll('[data-org-toggle]').forEach((button) => setNode(button, true));
            });
            document.querySelector('[data-org-collapse-all]')?.addEventListener('click', () => {
                document.querySelectorAll('[data-org-toggle]').forEach((button) => setNode(button, false));
            });
        })();
    </script>
    @endpush
</x-layouts.app>
