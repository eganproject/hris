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

        <section data-org-frame class="org-frame overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
            @if (count($tree) > 0)
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-100 px-4 py-2.5">
                    <p class="text-xs text-gray-500">Geser bagan dengan menahan klik lalu tarik. Ctrl + scroll untuk zoom.</p>
                    <div class="flex items-center gap-1.5">
                        <button type="button" data-org-zoom-out class="rounded-md border border-gray-200 bg-white px-2.5 py-1.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50" aria-label="Perkecil" title="Perkecil">&minus;</button>
                        <span data-org-zoom-label class="w-12 text-center text-xs font-medium tabular-nums text-gray-600">100%</span>
                        <button type="button" data-org-zoom-in class="rounded-md border border-gray-200 bg-white px-2.5 py-1.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50" aria-label="Perbesar" title="Perbesar">+</button>
                        <button type="button" data-org-zoom-fit class="rounded-md border border-gray-200 bg-white px-2.5 py-1.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50">Pas layar</button>
                        <button type="button" data-org-zoom-reset class="rounded-md border border-gray-200 bg-white px-2.5 py-1.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50">100%</button>
                        <button type="button" data-org-fullscreen class="rounded-md border border-gray-200 bg-white px-2.5 py-1.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50">Layar penuh</button>
                    </div>
                </div>
                <div data-org-viewport class="org-viewport p-4">
                    <div data-org-canvas class="org-canvas">
                        <ul class="org-tree">
                            @foreach ($tree as $node)
                                <li>@include('organization.partials.node', ['node' => $node, 'depth' => 0])</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @else
                <p class="px-2 py-8 text-center text-sm text-gray-400">Belum ada karyawan aktif untuk ditampilkan pada bagan.</p>
            @endif
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
                button.querySelector('svg')?.classList.toggle('rotate-90', open);
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

            // Zoom, geser (drag) dan layar penuh.
            const frame = document.querySelector('[data-org-frame]');
            const viewport = document.querySelector('[data-org-viewport]');
            const canvas = document.querySelector('[data-org-canvas]');
            if (!viewport || !canvas) return;

            const MIN = 0.3, MAX = 1.5, STEP = 0.1;
            const label = document.querySelector('[data-org-zoom-label]');
            let scale = 1;

            const setZoom = (value, anchor) => {
                const next = Math.min(MAX, Math.max(MIN, Math.round(value * 100) / 100));
                // Pertahankan titik yang sedang dilihat (atau tengah layar) saat zoom berubah.
                const ax = anchor?.x ?? viewport.clientWidth / 2;
                const ay = anchor?.y ?? viewport.clientHeight / 2;
                const ratio = next / scale;
                const left = (viewport.scrollLeft + ax) * ratio - ax;
                const top = (viewport.scrollTop + ay) * ratio - ay;
                scale = next;
                canvas.style.zoom = String(next);
                viewport.scrollLeft = left;
                viewport.scrollTop = top;
                if (label) label.textContent = Math.round(next * 100) + '%';
            };

            // Lebar & tinggi asli bagan (pada zoom 100%).
            const naturalSize = () => {
                const current = canvas.style.zoom;
                canvas.style.zoom = '1';
                const tree = canvas.firstElementChild;
                const size = { width: tree.scrollWidth, height: tree.scrollHeight };
                canvas.style.zoom = current;
                return size;
            };

            const fitScale = () => {
                const style = getComputedStyle(viewport);
                const padX = parseFloat(style.paddingLeft) + parseFloat(style.paddingRight);
                const { width } = naturalSize();
                return width > 0 ? (viewport.clientWidth - padX) / width : 1;
            };

            const centerHorizontally = () => {
                viewport.scrollLeft = (viewport.scrollWidth - viewport.clientWidth) / 2;
                viewport.scrollTop = 0;
            };

            document.querySelector('[data-org-zoom-in]')?.addEventListener('click', () => setZoom(scale + STEP));
            document.querySelector('[data-org-zoom-out]')?.addEventListener('click', () => setZoom(scale - STEP));
            document.querySelector('[data-org-zoom-reset]')?.addEventListener('click', () => { setZoom(1); centerHorizontally(); });
            document.querySelector('[data-org-zoom-fit]')?.addEventListener('click', () => { setZoom(Math.min(1, fitScale())); centerHorizontally(); });

            viewport.addEventListener('wheel', (event) => {
                if (!event.ctrlKey && !event.metaKey) return;
                event.preventDefault();
                const rect = viewport.getBoundingClientRect();
                setZoom(scale * (event.deltaY < 0 ? 1.1 : 1 / 1.1), { x: event.clientX - rect.left, y: event.clientY - rect.top });
            }, { passive: false });

            // Geser dengan mouse: tahan klik di area kosong lalu tarik. Sentuh/trackpad tetap scroll biasa.
            let drag = null;
            viewport.addEventListener('pointerdown', (event) => {
                if (event.pointerType !== 'mouse' || event.button !== 0 || event.target.closest('a, button')) return;
                drag = { x: event.clientX, y: event.clientY, left: viewport.scrollLeft, top: viewport.scrollTop };
                viewport.setPointerCapture(event.pointerId);
                viewport.classList.add('is-panning');
            });
            viewport.addEventListener('pointermove', (event) => {
                if (!drag) return;
                viewport.scrollLeft = drag.left - (event.clientX - drag.x);
                viewport.scrollTop = drag.top - (event.clientY - drag.y);
            });
            const endDrag = () => { drag = null; viewport.classList.remove('is-panning'); };
            viewport.addEventListener('pointerup', endDrag);
            viewport.addEventListener('pointercancel', endDrag);

            const fullscreenButton = document.querySelector('[data-org-fullscreen]');
            if (fullscreenButton && frame?.requestFullscreen) {
                fullscreenButton.addEventListener('click', () => {
                    document.fullscreenElement ? document.exitFullscreen() : frame.requestFullscreen();
                });
                document.addEventListener('fullscreenchange', () => {
                    fullscreenButton.textContent = document.fullscreenElement ? 'Keluar layar penuh' : 'Layar penuh';
                });
            } else {
                fullscreenButton?.remove();
            }

            // Saat dibuka: kecilkan otomatis bila bagan lebih lebar dari layar (tidak di bawah 60% agar tetap terbaca).
            setZoom(Math.min(1, Math.max(0.6, fitScale())));
            centerHorizontally();
        })();
    </script>
    @endpush
</x-layouts.app>
