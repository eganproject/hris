<x-layouts.app title="Serah Terima Aset - {{ config('app.name', 'HRIS') }}" heading="Serah Terima Aset">
    <div class="mx-auto max-w-7xl space-y-6">
        <section>
            <p class="text-sm font-medium text-gray-500">Manajemen aset</p>
            <h1 class="mt-1 text-2xl font-semibold text-gray-950">Serah Terima Aset</h1>
            <p class="mt-1 text-sm text-gray-500">Siapa memegang apa, mana yang belum dikonfirmasi, dan mana yang telat kembali.</p>
        </section>

        <x-scope-notice :has-no-scope="$hasNoScope" />

        <section class="flex flex-wrap gap-2">
            @foreach (['open' => 'Sedang dipegang', 'unacknowledged' => 'Belum dikonfirmasi', 'overdue' => 'Telat kembali', 'closed' => 'Sudah kembali', 'all' => 'Semua'] as $value => $label)
                <a href="{{ route('assets.assignments.index', ['state' => $value]) }}"
                   @class([
                       'rounded-md px-4 py-2 text-sm font-medium transition',
                       'bg-primary text-white shadow-xs' => $filter === $value,
                       'border border-gray-200 text-gray-700 hover:bg-gray-50' => $filter !== $value,
                   ])>{{ $label }}</a>
            @endforeach
        </section>

        <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Aset</th>
                            <th>Pemegang</th>
                            <th>Diserahkan</th>
                            <th>Target Kembali</th>
                            <th>Konfirmasi</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($assignments as $assignment)
                            <tr>
                                <td>
                                    <a href="{{ route('assets.show', $assignment->asset_id) }}" class="font-medium text-gray-950 hover:text-primary hover:underline">{{ $assignment->asset?->name }}</a>
                                    <p class="mt-0.5 font-mono text-xs text-gray-500">{{ $assignment->asset?->asset_code }}</p>
                                </td>
                                <td>{{ $assignment->employee?->full_name ?? '-' }}</td>
                                <td>
                                    <p class="text-sm text-gray-900">{{ $assignment->assigned_at?->translatedFormat('d M Y') }}</p>
                                    <p class="mt-0.5 text-xs text-gray-500">oleh {{ $assignment->assignedBy?->name ?? '-' }}</p>
                                </td>
                                <td>
                                    @if ($assignment->expected_return_at)
                                        <span @class(['text-sm', 'font-medium text-red-600' => $assignment->isOverdue(), 'text-gray-900' => ! $assignment->isOverdue()])>
                                            {{ $assignment->expected_return_at->translatedFormat('d M Y') }}
                                        </span>
                                    @else
                                        <span class="text-sm text-gray-400">Tanpa batas</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($assignment->isAcknowledged())
                                        <x-status-badge tone="success">Dikonfirmasi</x-status-badge>
                                    @elseif ($assignment->isOpen())
                                        <x-status-badge tone="warning">Menunggu</x-status-badge>
                                    @else
                                        <span class="text-sm text-gray-400">-</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($assignment->isOpen())
                                        <x-status-badge :tone="$assignment->isOverdue() ? 'danger' : 'info'">{{ $assignment->isOverdue() ? 'Telat kembali' : 'Dipegang' }}</x-status-badge>
                                    @else
                                        <x-status-badge tone="neutral">Kembali {{ $assignment->returned_at?->translatedFormat('d M Y') }}</x-status-badge>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="cell-empty">Belum ada serah-terima yang cocok dengan penyaring ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-gray-200 px-5 py-4">{{ $assignments->links() }}</div>
        </section>
    </div>
</x-layouts.app>
