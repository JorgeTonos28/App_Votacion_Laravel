<?php

namespace App\Http\Controllers;

use App\Exceptions\DomainException;
use App\Models\VotingEvent;
use App\Services\EventQueryService;
use App\Services\EventSessionService;
use App\Services\VoteService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PublicController extends Controller
{
    public function __construct(private readonly EventSessionService $sessions, private readonly EventQueryService $queries, private readonly VoteService $votes) {}

    public function lobby(Request $request)
    {
        $session = $this->current($request);
        $state = $this->queries->liveState($session);
        $event = VotingEvent::query()->with(['branding', 'participants'])->findOrFail($session['eventId']);
        if ($state['eventStatus'] === 'Published') {
            return redirect()->to(route('projection.ranking', $event->code).'?transition=lobby');
        }
        $participants = $event->participants->sortBy('presentation_order')->values();

        return view('public.lobby', compact('session', 'state', 'event', 'participants'));
    }

    public function ballot(Request $request)
    {
        $session = $this->current($request);
        $state = $this->queries->liveState($session);
        if ($state['currentActorHasVoted']) {
            return view('public.already-voted', compact('state'));
        }$ballot = $this->queries->ballot($session);
        $form = ['presentationId' => $ballot['presentationId'], 'clientRequestId' => Str::uuid()->toString(), 'answers' => []];

        return view('public.ballot', compact('ballot', 'form'));
    }

    public function submit(Request $request)
    {
        $session = $this->current($request);
        $data = $request->validate(['presentation_id' => 'required|uuid', 'client_request_id' => 'required|string|max:100', 'answers' => 'required|array', 'answers.*.criterion_id' => 'required|uuid', 'answers.*.value' => 'nullable|numeric', 'answers.*.comment' => 'nullable|string|max:2000']);
        try {
            $receipt = $this->votes->submit($session, $data['presentation_id'], $data['client_request_id'], array_values(array_filter($data['answers'], fn ($a) => isset($a['value']))));

            return redirect()->route('public.confirmed')->with('voteReceipt', $receipt);
        } catch (DomainException $e) {
            $ballot = $this->queries->ballot($session);
            $form = ['presentationId' => $data['presentation_id'], 'clientRequestId' => $data['client_request_id'], 'answers' => $data['answers']];

            return view('public.ballot', compact('ballot', 'form'))->withErrors([$e->getMessage()]);
        }
    }

    public function confirmed(Request $request)
    {
        $session = $this->current($request);
        $receipt = session('voteReceipt');
        if (! $receipt) {
            return redirect()->route('public.lobby');
        }$eventName = VotingEvent::query()->whereKey($session['eventId'])->value('name');

        return view('public.confirmed', compact('receipt', 'eventName'));
    }

    public function state(Request $request)
    {
        $session = $this->current($request);

        return $this->envelope($this->queries->liveState($session));
    }

    private function current(Request $request): array
    {
        return $this->sessions->validate($request->cookie('innovamente_public'), 'Public');
    }

    private function envelope(array $data)
    {
        return response()->json(['ok' => true, 'data' => $data, 'error' => null, 'meta' => ['requestId' => (string) Str::uuid(), 'serverTime' => now()->toIso8601String()]]);
    }
}
