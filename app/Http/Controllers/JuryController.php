<?php

namespace App\Http\Controllers;

use App\Exceptions\DomainException;
use App\Models\Juror;
use App\Models\Vote;
use App\Models\VotingEvent;
use App\Services\AccessService;
use App\Services\EventQueryService;
use App\Services\EventSessionService;
use App\Services\VoteService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class JuryController extends Controller
{
    public function __construct(private readonly AccessService $access, private readonly EventSessionService $sessions, private readonly EventQueryService $queries, private readonly VoteService $votes) {}

    public function access(Request $request)
    {
        $eventCode = $request->query('event', $request->query('eventCode'));
        $jurorCode = $request->query('code', $request->query('jurorCode'));

        return view('jury.access', compact('eventCode', 'jurorCode'));
    }

    public function validateCode(Request $request)
    {
        $data = $request->validate(['event_code' => 'required|string|max:12', 'juror_code' => 'required|string|max:32'], ['event_code.required' => 'Ingresa el código del evento.', 'juror_code.required' => 'Ingresa tu código personal.']);
        try {
            $juror = $this->access->validateJuror($data['event_code'], $data['juror_code']);
            $event = VotingEvent::query()->findOrFail($juror->event_id);
            $confirmationToken = Crypt::encryptString(json_encode(['eventId' => $event->id, 'jurorId' => $juror->id, 'expires' => now()->addMinutes(5)->timestamp]));

            return view('jury.confirm', compact('juror', 'event', 'confirmationToken'));
        } catch (DomainException $e) {
            return back()->withInput()->withErrors([$e->getMessage()]);
        }
    }

    public function confirm(Request $request)
    {
        try {
            $payload = json_decode(Crypt::decryptString((string) $request->string('confirmation_token')), true, 512, JSON_THROW_ON_ERROR);
            if (($payload['expires'] ?? 0) < time()) {
                throw new \RuntimeException;
            }$event = VotingEvent::query()->findOrFail($payload['eventId']);
            $juror = Juror::query()->whereKey($payload['jurorId'])->where('event_id', $event->id)->where('status', 'Active')->firstOrFail();
            [$token,$session] = $this->sessions->createJuror($event, $juror, $request->userAgent());

            return redirect()->route('jury.dashboard')->cookie('innovamente_juror', $token, 480, null, null, $request->isSecure(), true, false, 'Lax');
        } catch (\Throwable) {
            return redirect()->route('jury.access')->withErrors(['La confirmación expiró. Valida tu código nuevamente.']);
        }
    }

    public function dashboard(Request $request)
    {
        $session = $this->current($request);
        $juror = Juror::query()->findOrFail($session['actorId']);
        $event = VotingEvent::query()->with('presentations.participant')->findOrFail($session['eventId']);
        $votes = Vote::query()->where('event_id', $event->id)->where('actor_id', $juror->id)->get()->keyBy('presentation_id');
        $history = $event->presentations->sortBy('sequence')->map(fn ($p) => ['participantName' => $p->participant->name, 'projectTitle' => $p->participant->project_title, 'presentationStatus' => $p->status, 'voteStatus' => $votes->get($p->id)?->status, 'submittedAt' => $votes->get($p->id)?->submitted_at])->values();
        $state = $this->queries->liveState($session);

        return view('jury.dashboard', compact('juror', 'event', 'history', 'state'));
    }

    public function ballot(Request $request)
    {
        $session = $this->current($request);
        $ballot = $this->queries->ballot($session);
        $existing = Vote::query()->with('details')->where('presentation_id', $ballot['presentationId'])->where('actor_id', $session['actorId'])->where('status', '!=', 'Invalidated')->first();
        $answers = [];
        foreach ($ballot['criteria'] as $criterion) {
            $detail = $existing?->details->firstWhere('criterion_id', $criterion->id);
            $answers[] = ['criterion_id' => $criterion->id, 'value' => $detail?->raw_value, 'comment' => $detail?->comment];
        }$form = ['presentationId' => $ballot['presentationId'], 'clientRequestId' => Str::uuid()->toString(), 'answers' => $answers];

        return view('jury.ballot', compact('ballot', 'form'));
    }

    public function submit(Request $request)
    {
        $session = $this->current($request);
        $data = $request->validate(['presentation_id' => 'required|uuid', 'client_request_id' => 'required|string|max:100', 'answers' => 'required|array', 'answers.*.criterion_id' => 'required|uuid', 'answers.*.value' => 'nullable|numeric', 'answers.*.comment' => 'nullable|string|max:2000']);
        try {
            $this->votes->submit($session, $data['presentation_id'], $data['client_request_id'], array_values(array_filter($data['answers'], fn ($a) => isset($a['value']))));

            return redirect()->route('jury.dashboard')->with('success', 'Evaluación registrada.');
        } catch (DomainException $e) {
            $ballot = $this->queries->ballot($session);
            $form = ['presentationId' => $data['presentation_id'], 'clientRequestId' => $data['client_request_id'], 'answers' => $data['answers']];

            return view('jury.ballot', compact('ballot', 'form'))->withErrors([$e->getMessage()]);
        }
    }

    public function logout(Request $request)
    {
        $this->sessions->revoke($request->cookie('innovamente_juror'));

        return redirect()->route('jury.access')->withoutCookie('innovamente_juror');
    }

    public function state(Request $request)
    {
        return response()->json(['ok' => true, 'data' => $this->queries->liveState($this->current($request)), 'error' => null, 'meta' => ['requestId' => (string) Str::uuid(), 'serverTime' => now()->toIso8601String()]]);
    }

    private function current(Request $request): array
    {
        return $this->sessions->validate($request->cookie('innovamente_juror'), 'Juror');
    }
}
