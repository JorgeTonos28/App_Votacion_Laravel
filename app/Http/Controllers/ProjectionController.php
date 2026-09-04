<?php

namespace App\Http\Controllers;

use App\Exceptions\DomainException;
use App\Models\VotingEvent;
use App\Services\EventQueryService;
use App\Services\ResultService;
use App\Support\Domain;
use Illuminate\Support\Str;

class ProjectionController extends Controller
{
    public function __construct(private readonly EventQueryService $queries, private readonly ResultService $results) {}

    public function live(string $eventCode)
    {
        $event = $this->event($eventCode);
        if ($event->status === 'Published') {
            return redirect()->route('projection.ranking', $event->code);
        }
        $state = $this->queries->liveStateByCode($event->code);

        return view('projection.live', compact('event', 'state'));
    }

    public function ranking(string $eventCode)
    {
        $event = $this->event($eventCode);
        if ($event->status !== 'Published') {
            throw new DomainException('RESULTS_NOT_PUBLISHED', 'Los resultados todavía no han sido publicados.', 403);
        }$state = $this->queries->liveStateByCode($event->code);
        $ranking = $this->results->ranking($event->id);

        return view('projection.ranking', compact('event', 'state', 'ranking'));
    }

    public function state(string $eventCode)
    {
        return response()->json(['ok' => true, 'data' => $this->queries->liveStateByCode($eventCode), 'error' => null, 'meta' => ['requestId' => (string) Str::uuid(), 'serverTime' => now()->toIso8601String()]]);
    }

    private function event(string $code): VotingEvent
    {
        $event = VotingEvent::query()->with('branding')->whereRaw('UPPER(code)=?', [Domain::normalizeCode($code)])->first();
        if (! $event) {
            throw new DomainException('EVENT_NOT_FOUND', 'No encontramos el evento.', 404);
        }

return $event;
    }
}
