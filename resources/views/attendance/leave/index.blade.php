<x-layouts.app title="Cuti & Izin - {{ config('app.name', 'HRIS') }}" heading="Cuti & Izin">
    <div class="mx-auto max-w-7xl space-y-6">
        <section class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <p class="text-sm font-medium text-gray-500">Master attendance</p>
                <h1 class="mt-1 text-2xl font-semibold text-gray-950">Cuti & Izin</h1>
            </div>
            @can('leave.create')
                <a href="{{ route('attendance.leave.create') }}" class="inline-flex items-center justify-center gap-1.5 rounded-md bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-xs transition hover:bg-primary-hover">
                    <x-icon name="plus" class="size-4"/> Ajukan Cuti/Izin
                </a>
            @endcan
        </section>

        <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
            <form method="GET" action="{{ route('attendance.leave.index') }}" class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="lg:col-span-2">
                    <label for="search" class="block text-sm font-medium text-gray-700">Cari karyawan</label>
                    <input id="search" name="search" value="{{ $filters['search'] ?? '' }}" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20" placeholder="Nama / NIK karyawan">
                </div>
                <div>
                    <label for="status" class="block text-sm font-medium text-gray-700">Status</label>
                    <select id="status" name="status" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                        <option value="">Semua status</option>
                        @foreach ($statuses as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="leave_type_id" class="block text-sm font-medium text-gray-700">Jenis cuti</label>
                    <select id="leave_type_id" name="leave_type_id" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                        <option value="">Semua jenis</option>
                        @foreach ($leaveTypes as $type)
                            <option value="{{ $type->id }}" @selected((string) ($filters['leave_type_id'] ?? '') === (string) $type->id)>{{ $type->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="branch_id" class="block text-sm font-medium text-gray-700">Lokasi</label>
                    <select id="branch_id" name="branch_id" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                        <option value="">Semua lokasi</option>
                        @foreach ($branches as $branch)
                            <option value="{{ $branch->id }}" @selected((string) ($filters['branch_id'] ?? '') === (string) $branch->id)>{{ $branch->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="department_id" class="block text-sm font-medium text-gray-700">Divisi</label>
                    <select id="department_id" name="department_id" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                        <option value="">Semua divisi</option>
                        @foreach ($departments as $department)
                            <option value="{{ $department->id }}" @selected((string) ($filters['department_id'] ?? '') === (string) $department->id)>{{ $department->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="date_from" class="block text-sm font-medium text-gray-700">Dari tanggal</label>
                    <input type="date" id="date_from" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                </div>
                <div>
                    <label for="date_to" class="block text-sm font-medium text-gray-700">Sampai tanggal</label>
                    <input type="date" id="date_to" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                </div>
                <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-end lg:col-span-4">
                    <button type="submit" class="w-full rounded-md bg-primary px-4 py-2.5 text-sm font-semibold text-white sm:w-auto">Filter</button>
                    <a href="{{ route('attendance.leave.index') }}" class="w-full rounded-md border border-gray-200 px-4 py-2.5 text-center text-sm font-medium text-gray-700 transition hover:bg-gray-50 sm:w-auto">Reset</a>
                </div>
            </form>
        </section>

        @php $canDecide = auth()->user()->can('leave.update'); @endphp
        <section data-approve-scope class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
            @if ($canDecide)
                <div data-approve-bar hidden class="flex flex-col gap-3 border-b border-primary/20 bg-primary-soft px-5 py-3 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-sm font-medium text-gray-800"><span data-approve-count>0</span> pengajuan dipilih</p>
                    <div class="flex items-center gap-2">
                        <button type="button" data-approve-submit class="inline-flex items-center gap-1.5 rounded-md bg-emerald-600 px-3 py-2 text-sm font-semibold text-white shadow-xs transition hover:bg-emerald-700"><x-icon name="user-check" class="size-4"/> Setujui terpilih</button>
                        <button type="button" data-approve-clear class="rounded-md border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-600 transition hover:bg-gray-50">Bersihkan</button>
                    </div>
                </div>
            @endif
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            @if ($canDecide)<th class="w-10"><input type="checkbox" data-approve-all aria-label="Pilih semua" class="size-4 rounded border-gray-300 text-primary focus:ring-primary/30"></th>@endif
                            <th>Karyawan</th>
                            <th>Jenis</th>
                            <th>Periode</th>
                            <th>Status</th>
                            <th class="text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($leaveRequests as $leaveRequest)
                            <tr>
                                @if ($canDecide)
                                    <td>
                                        @if ($leaveRequest->status->isPending() && ! ($leaveRequest->employee?->user_id && $leaveRequest->employee->user_id === auth()->id()))
                                            <input type="checkbox" data-approve-checkbox value="{{ $leaveRequest->id }}" aria-label="Pilih pengajuan {{ $leaveRequest->employee?->full_name }}" class="size-4 rounded border-gray-300 text-primary focus:ring-primary/30">
                                        @endif
                                    </td>
                                @endif
                                <td>
                                    <p class="font-medium text-gray-950">{{ $leaveRequest->employee?->full_name ?? '-' }}</p>
                                    <p class="mt-0.5 text-xs text-gray-500">{{ $leaveRequest->employee?->employee_number }}</p>
                                </td>
                                <td>
                                    {{ $leaveRequest->leaveType?->name ?? '-' }}
                                    <p class="mt-0.5 text-xs text-gray-500">{{ $leaveRequest->leaveType?->attendance_status?->label() }}</p>
                                </td>
                                <td>
                                    {{ $leaveRequest->start_date->format('d M Y') }} – {{ $leaveRequest->end_date->format('d M Y') }}
                                    <p class="mt-0.5 text-xs text-gray-500">{{ $leaveRequest->days }} hari</p>
                                </td>
                                <td>
                                    <x-status-badge :tone="$leaveRequest->status->tone()">{{ $leaveRequest->status->label() }}</x-status-badge>
                                    @if ($leaveRequest->supervisor)
                                        <p class="mt-1 text-xs text-gray-400">Atasan: {{ $leaveRequest->supervisor->full_name }}</p>
                                    @endif
                                    @if ($leaveRequest->approver)
                                        <p class="text-xs text-gray-400">HR: {{ $leaveRequest->approver->name }}</p>
                                    @endif
                                </td>
                                <td class="text-right">
                                    {{-- Hanya pengajuan yang masih menunggu keputusan yang punya aksi. Cuti/izin
                                         yang sudah DISETUJUI bersifat final: tidak bisa dibatalkan maupun dihapus.
                                         Pengajuan yang belum disetujui hanya bisa dihapus oleh karyawan yang
                                         mengajukan (menu Cuti Saya). --}}
                                    @if (auth()->user()->can('leave.update') && $leaveRequest->status->isPending())
                                        <x-action-menu>
                                            <form method="POST" action="{{ route('attendance.leave.approve', $leaveRequest) }}" data-no-confirm="true">
                                                @csrf @method('PATCH')
                                                <button type="submit" class="action-menu-item"><x-icon name="user-check"/> {{ $leaveRequest->status === \App\Enums\LeaveRequestStatus::PendingSupervisor ? 'Setujui (Atasan)' : 'Setujui (HR)' }}</button>
                                            </form>
                                            <form method="POST" action="{{ route('attendance.leave.reject', $leaveRequest) }}" data-no-confirm="true">
                                                @csrf @method('PATCH')
                                                <button type="submit" class="action-menu-item action-menu-item-danger"><x-icon name="user-x"/> Tolak</button>
                                            </form>
                                        </x-action-menu>
                                    @else
                                        <span class="text-xs text-gray-400">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="{{ $canDecide ? 6 : 5 }}" class="cell-empty">Belum ada pengajuan cuti/izin.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-gray-200 px-5 py-4">{{ $leaveRequests->links() }}</div>

            @if ($canDecide)
                <form data-approve-form method="POST" action="{{ route('attendance.leave.bulk-approve') }}" class="hidden" data-confirm-message="Setujui semua pengajuan cuti/izin yang dipilih?" data-confirm-approve="Ya, setujui">
                    @csrf
                    <div data-approve-ids></div>
                </form>
            @endif
        </section>
    </div>
</x-layouts.app>
