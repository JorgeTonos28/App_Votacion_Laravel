<?php

namespace App\Http\Controllers;

use App\Exceptions\DomainException;
use App\Models\VotingEvent;
use App\Models\VotingResult;
use App\Services\EventQueryService;
use App\Services\ResultService;
use App\Support\Domain;
use Illuminate\Support\Carbon;
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
        $published = $event->status === 'Published';
        $state = $this->queries->liveStateByCode($event->code);
        $ranking = $published ? $this->results->ranking($event->id) : [];

        $publishedAt = null;
        if ($published) {
            $round = (int) ($event->current_round ?: 1);
            $publishedAt = VotingResult::query()
                ->where('event_id', $event->id)
                ->where('round_number', $round)
                ->whereNotNull('published_at')
                ->max('published_at')
                ?: VotingResult::query()
                    ->where('event_id', $event->id)
                    ->whereNotNull('published_at')
                    ->max('published_at');
        }

        // Solo mostrar animación si fue publicado hace menos de 5 minutos (300s)
        $secondsSincePublished = null;
        $isFreshlyPublished = false;
        if ($published && $publishedAt) {
            $carbonPublished = Carbon::parse($publishedAt);
            $secondsSincePublished = $carbonPublished->isFuture() ? 0 : (int) $carbonPublished->diffInSeconds(now());
            $isFreshlyPublished = $secondsSincePublished <= 300;
        }

        return view('projection.ranking', compact('event', 'state', 'ranking', 'published', 'isFreshlyPublished', 'secondsSincePublished'));
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
