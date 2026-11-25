<?php

namespace App\Http\Controllers\Api\Mobile\Hr;

use App\Models\ScheduleAcknowledgement;
use App\Support\Mobile\ScheduleDays;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/** Jadwal saya + konfirmasi jadwal mingguan yang diterbitkan atasan. */
class ScheduleController extends HrController
{
    public function __construct(private readonly ScheduleDays $days) {}

    /** ?from=YYYY-MM-DD&to=YYYY-MM-DD (bawaan: Senin–Minggu minggu ini, maks 62 hari). */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $employee = $this->employee();
        $from = $request->filled('from') ? Carbon::parse($request->input('from')) : now()->startOfWeek();
        $to = $request->filled('to') ? Carbon::parse($request->input('to')) : $from->copy()->addDays(6);

        if ($from->diffInDays($to) > 62) {
            $to = $from->copy()->addDays(62);
        }

        return $this->ok([
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'today' => now()->toDateString(),
            'days' => $this->days->for($employee, $from, $to),
            'pending_confirmations' => ScheduleAcknowledgement::query()
                ->where('employee_id', $employee->id)
                ->whereNull('confirmed_at')
                ->where('week_start', '>=', now()->startOfWeek()->toDateString())
                ->orderBy('week_start')
                ->get()
                ->map(fn (ScheduleAcknowledgement $ack) => [
                    'week_start' => $ack->week_start->toDateString(),
                    'week_end' => $ack->week_start->copy()->addDays(6)->toDateString(),
                    'published_at' => $ack->published_at?->toIso8601String(),
                ])
                ->values(),
        ]);
    }

    public function confirm(Request $request): JsonResponse
    {
        $data = $request->validate(['week_start' => ['required', 'date']]);

        $ack = ScheduleAcknowledgement::query()
            ->where('employee_id', $this->employee()->id)
            ->where('week_start', Carbon::parse($data['week_start'])->startOfWeek()->toDateString())
            ->first();

        abort_unless($ack, 404, 'Belum ada jadwal terbit untuk minggu itu.');

        if (! $ack->confirmed_at) {
            $ack->update(['confirmed_at' => now()]);
        }

        return $this->message('Terima kasih, jadwal sudah dikonfirmasi.');
    }
}
