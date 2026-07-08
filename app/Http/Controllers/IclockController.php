<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Services\DeviceCommandService;
use App\Services\PunchIngestionService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Implements the ZKTeco "iclock" push (ADMS) protocol that the Solution X100-C
 * speaks. The device is configured with our domain as its Cloud Server and pushes
 * attendance logs here over HTTP(S). Endpoints are public (the device cannot send
 * auth headers) but gated by a serial-number allowlist: only registered, active
 * devices are served. Run behind HTTPS.
 */
class IclockController extends Controller
{
    public function __construct(
        private readonly PunchIngestionService $ingestion,
        private readonly DeviceCommandService $commands,
    ) {
    }

    /**
     * GET /iclock/cdata — handshake. The device fetches its operating options.
     */
    public function handshake(Request $request): Response
    {
        $device = $this->device($request);
        $this->record($device, $request, 'handshake');

        $options = implode("\n", [
            'GET OPTION FROM: '.$device->serial_number,
            'Stamp=9999',
            'OpStamp=9999',
            'ErrorDelay=30',
            'Delay=30',
            'TransTimes=00:00;14:05',
            'TransInterval=1',
            'TransFlag=1111000000',
            'Realtime=1',
            'Encrypt=0',
        ]);

        return $this->text($options);
    }

    /**
     * POST /iclock/cdata — the device pushes data. table=ATTLOG carries punches.
     */
    public function receive(Request $request): Response
    {
        $device = $this->device($request);

        $table = strtoupper((string) $request->query('table'));
        $body = $request->getContent();

        if ($table === 'ATTLOG') {
            $count = $this->ingestion->ingestAttlog($device, $body);
            $this->record($device, $request, 'attlog', $count);

            return $this->text("OK: {$count}");
        }

        // OPERLOG (user/enroll data), etc. — acknowledged; not processed for MVP.
        $this->record($device, $request, 'data');

        return $this->text('OK');
    }

    /**
     * GET /iclock/getrequest — the device polls for server commands. Deliver any
     * pending queued commands (one per line), else OK.
     */
    public function getrequest(Request $request): Response
    {
        $device = $this->device($request);
        $this->record($device, $request, 'poll');

        $batch = $this->commands->nextBatch($device);

        if ($batch->isEmpty()) {
            return $this->text('OK');
        }

        return $this->text($batch->map(fn ($command) => $this->commands->toWire($command))->implode("\n"));
    }

    /**
     * POST /iclock/devicecmd — the device reports command results.
     * Body is form-encoded, e.g. "ID=12&Return=0&CMD=DATA".
     */
    public function devicecmd(Request $request): Response
    {
        $device = $this->device($request);
        $this->record($device, $request, 'command');

        $params = $this->parseParams($request);

        if (isset($params['ID'])) {
            $this->commands->acknowledge($device, (int) $params['ID'], (int) ($params['Return'] ?? -1));
        }

        return $this->text('OK');
    }

    /**
     * Read key=value params from the request (query, parsed body, or raw body).
     *
     * @return array<string, string>
     */
    private function parseParams(Request $request): array
    {
        if ($request->has('ID')) {
            return $request->all();
        }

        parse_str(str_replace("\n", '&', trim($request->getContent())), $params);

        return $params;
    }

    /**
     * Update the device's last-seen marker and append a communication log row.
     */
    private function record(Device $device, Request $request, string $event, int $count = 0): void
    {
        $device->markSeen($request->ip());

        $device->communications()->create([
            'event' => $event,
            'records_count' => $count,
            'ip' => $request->ip(),
        ]);
    }

    /**
     * Resolve and authorize the calling device by its serial number (?SN=...).
     */
    private function device(Request $request): Device
    {
        $serial = (string) ($request->query('SN') ?? $request->query('sn'));

        $device = Device::query()->where('serial_number', $serial)->where('is_active', true)->first();

        abort_if(! $device, 401, 'Unknown or inactive device.');

        return $device;
    }

    private function text(string $body): Response
    {
        return response($body, 200)->header('Content-Type', 'text/plain');
    }
}
