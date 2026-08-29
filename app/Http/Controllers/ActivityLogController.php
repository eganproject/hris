<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class ActivityLogController extends Controller
{
    /** Pilihan jumlah baris per halaman. */
    private const PER_PAGE_OPTIONS = [25, 50, 100, 200];

    public function index(Request $request): View
    {
        // Seluruh penyaring dibaca defensif. Halaman ini dibuka lewat URL yang
        // gampang disalin-tempel dan disunting tangan, dan dua jebakan bawaan Laravel
        // menunggu di situ: Request::date() melempar pengecualian pada tanggal yang
        // tidak masuk akal, dan Request::string() melempar TypeError begitu
        // parameternya dikirim sebagai array (?module[]=x). Keduanya berujung 500.
        $filters = [
            'user_id' => $request->integer('user_id') ?: null,
            'module' => $this->text($request->input('module')),
            'event' => $this->text($request->input('event')),
            'from' => $this->date($request->input('from')),
            'to' => $this->date($request->input('to')),
            'search' => $this->text($request->input('search')),
        ];

        $perPage = in_array((int) $request->input('per_page'), self::PER_PAGE_OPTIONS, true)
            ? (int) $request->input('per_page')
            : self::PER_PAGE_OPTIONS[0];

        $logs = ActivityLog::query()
            ->filtered($filters)
            ->with('user:id,name,email')
            ->latest('created_at')
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();

        return view('activity.index', [
            'logs' => $logs,
            'filters' => $filters,
            'perPage' => $perPage,
            'perPageOptions' => self::PER_PAGE_OPTIONS,
            'modules' => ActivityLog::MODULES,
            'events' => ActivityLog::EVENTS,
            // Hanya pengguna yang benar-benar pernah tercatat yang ditawarkan di
            // penyaring — daftar seluruh akun membuat kotaknya panjang tanpa guna.
            'users' => User::query()
                ->whereIn('id', ActivityLog::query()->select('user_id')->whereNotNull('user_id')->distinct())
                ->orderBy('name')
                ->get(['id', 'name']),
            'stats' => $this->stats(),
        ]);
    }

    /** Teks penyaring, atau null bila yang datang bukan teks. */
    private function text(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    /** Tanggal penyaring, atau null bila tidak bisa dibaca sebagai tanggal. */
    private function date(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Angka ringkas 24 jam terakhir. Dihitung dari seluruh jejak, bukan dari hasil
     * penyaring — gunanya justru sebagai latar pembanding bagi baris yang disaring.
     *
     * @return array<string, int>
     */
    private function stats(): array
    {
        $since = now()->subDay();

        $base = fn () => ActivityLog::query()->where('created_at', '>=', $since);

        return [
            'total' => $base()->count(),
            'users' => (int) $base()->whereNotNull('user_id')->distinct()->count('user_id'),
            'changes' => $base()->whereIn('event', ['created', 'updated', 'deleted', 'restored'])->count(),
            'failed_logins' => $base()->whereIn('event', ['login_failed', 'login_blocked'])->count(),
        ];
    }
}
