<?php

namespace App\Http\Controllers;

use App\Exceptions\DomainException;
use App\Mail\JurorAccessMail;
use App\Models\AppSetting;
use App\Models\AuditEntry;
use App\Models\Criterion;
use App\Models\EventBranding;
use App\Models\EventSession;
use App\Models\EventTemplate;
use App\Models\Juror;
use App\Models\Participant;
use App\Models\Presentation;
use App\Models\User;
use App\Models\Vote;
use App\Models\Voter;
use App\Models\VotingEvent;
use App\Models\VotingGroup;
use App\Models\VotingResult;
use App\Services\AuditService;
use App\Services\EventQueryService;
use App\Services\EventRoundService;
use App\Services\LiveControlService;
use App\Services\QrCodeService;
use App\Services\ResultService;
use App\Services\RubricCsvImporter;
use App\Services\VoteService;
use App\Support\Domain;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminController extends Controller
{
    public function __construct(
        private readonly AuditService $audit,
        private readonly EventQueryService $queries,
        private readonly EventRoundService $rounds,
        private readonly LiveControlService $liveControl,
        private readonly ResultService $results,
        private readonly VoteService $votes,
        private readonly QrCodeService $qr,
        private readonly RubricCsvImporter $rubricCsvImporter,
    ) {}

    public function index()
    {
        $events = $this->eventDirectory();
        $metrics = ['activeEvents' => $events->whereIn('status', ['Live', 'LobbyOpen', 'Paused'])->count(), 'upcomingEvents' => $events->where('status', 'Scheduled')->count(), 'finishedEvents' => $events->whereIn('status', ['Finished', 'Published'])->count(), 'totalVotes' => Vote::query()->where('status', '!=', 'Invalidated')->count(), 'activeJurors' => Juror::query()->where('status', 'Active')->count(), 'connectedUsers' => EventSession::query()->whereNull('revoked_at')->where('expires_at', '>', now())->count(), 'totalParticipants' => Participant::query()->count()];

        return view('admin.index', compact('events', 'metrics'));
    }

    public function projects()
    {
        $events = $this->eventDirectory();

        return view('admin.projects', compact('events'));
    }

    public function allJurors()
    {
        $voteCounts = Vote::query()
            ->where('role_type', 'Jury')
            ->where('status', '!=', 'Invalidated')
            ->selectRaw('actor_id, COUNT(*) AS votes_count')
            ->groupBy('actor_id')
            ->pluck('votes_count', 'actor_id');
        $records = Juror::query()->with('event')->orderBy('name')->get();
        $jurors = $records->groupBy(fn (Juror $juror) => $this->jurorIdentityKey($juror))
            ->map(function ($history) use ($voteCounts) {
                $representative = $history->sortByDesc(fn (Juror $juror) => (string) $juror->created_at)->first();
                $representative->history = $history->sortByDesc(fn (Juror $juror) => (string) $juror->created_at)->values();
                $representative->events_count = $history->count();
                $representative->votes_count = $history->sum(fn (Juror $juror) => (int) $voteCounts->get($juror->id, 0));
                $representative->is_active = $history->contains(fn (Juror $juror) => $juror->status === 'Active');
                $representative->latest_access_at = $history->max('last_access_at');

                return $representative;
            })
            ->sortBy('name')
            ->values();

        return view('admin.all-jurors', compact('jurors'));
    }

    public function allResults()
    {
        $events = VotingEvent::query()->withCount('participants')->get()->each(function ($e) {
            $e->calculated_results = VotingResult::query()->where('event_id', $e->id)->where('round_number', $e->current_round)->whereNull('voting_group_id')->count();
            $e->published_at = VotingResult::query()->where('event_id', $e->id)->where('round_number', $e->current_round)->whereNull('voting_group_id')->value('published_at');
        });

        return view('admin.all-results', compact('events'));
    }

    public function settings()
    {
        $metrics = ['administrators' => User::query()->where('role', 'Administrator')->count(), 'operators' => User::query()->where('role', 'Operator')->count(), 'mfa' => User::query()->where('two_factor_enabled', true)->count()];
        $templates = EventTemplate::query()->where('active', true)->orderBy('name')->get();
        $settings = AppSetting::query()->orderBy('key')->get();

        return view('admin.settings', compact('metrics', 'templates', 'settings'));
    }

    public function create()
    {
        $event = null;

        return view('admin.create', compact('event'));
    }

    public function store(Request $request)
    {
        $data = $this->validateEvent($request, true);
        $code = Domain::normalizeCode($data['code'] ?? '');
        if (! $code) {
            do {
                $code = Domain::randomCode();
            } while (VotingEvent::query()->where('code', $code)->exists());
        }
        if (VotingEvent::query()->where('code', $code)->exists()) {
            return back()->withInput()->withErrors(['code' => 'Ese código ya está en uso.']);
        }
        if (abs($data['jury_weight_percent'] + $data['public_weight_percent'] - 100) > .001) {
            return back()->withInput()->withErrors(['Los pesos del jurado y el público deben sumar 100%.']);
        }
        $event = DB::transaction(function () use ($data, $code, $request) {
            $event = VotingEvent::query()->create($this->eventPayload($data) + ['code' => $code, 'created_by' => (string) $request->user()->id, 'updated_by' => (string) $request->user()->id]);
            EventBranding::query()->create(['event_id' => $event->id, 'logo_url' => '/images/logo-innovatep.png']);
            $this->addDefaultRubrics($event, $data['jury_weight_percent'] / 100, $data['public_weight_percent'] / 100);
            $this->audit->write($event->id, 'Administrator', (string) $request->user()->id, 'EVENT_CREATED', 'Event', $event->id, newValue: ['name' => $event->name, 'code' => $event->code]);

            return $event;
        });

        return redirect()->route('admin.participants', $event)->with('success', 'Evento creado. Ahora agrega los participantes.');
    }

    public function edit(VotingEvent $event)
    {
        $event->load('groups');

        return view('admin.create', compact('event'));
    }

    public function update(Request $request, VotingEvent $event)
    {
        $data = $this->validateEvent($request, false);
        if (Vote::query()->where('event_id', $event->id)->exists() && $event->public_access_mode !== $data['public_access_mode']) {
            return back()->withInput()->withErrors(['No puedes cambiar la modalidad de acceso después de recibir votos.']);
        }
        $previous = ['name' => $event->name, 'status' => $event->status, 'publicAccessMode' => $event->public_access_mode];
        $event->update($this->eventPayload($data) + ['updated_by' => (string) $request->user()->id, 'version' => $event->version + 1]);
        $this->audit->write($event->id, 'Administrator', (string) $request->user()->id, 'EVENT_UPDATED', 'Event', $event->id, $previous, ['name' => $event->name, 'status' => $event->status, 'publicAccessMode' => $event->public_access_mode]);

        return back()->with('success', 'Configuración guardada.');
    }

    public function cloneEvent(Request $request, VotingEvent $event)
    {
        $event->load(['branding', 'groups.criteria']);
        do {
            $code = Domain::randomCode();
        } while (VotingEvent::query()->where('code', $code)->exists());
        $clone = DB::transaction(function () use ($event, $code, $request) {
            $clone = VotingEvent::query()->create($event->only(['subtitle', 'description', 'category', 'organizer', 'venue', 'time_zone', 'public_access_mode', 'results_visibility', 'presentation_duration_seconds', 'voting_duration_seconds', 'allow_juror_vote_edit']) + ['code' => $code, 'name' => $event->name.' (copia)', 'created_by' => (string) $request->user()->id]);
            EventBranding::query()->create(['event_id' => $clone->id, 'logo_url' => $event->branding?->logo_url, 'cover_url' => $event->branding?->cover_url, 'primary_color' => $event->branding?->primary_color ?? '#042E80', 'secondary_color' => $event->branding?->secondary_color ?? '#FEA203']);
            foreach ($event->groups as $group) {
                $copy = VotingGroup::query()->create($group->only(['name', 'role_type', 'weight', 'enabled', 'minimum_votes', 'minimum_participation_percent', 'require_all_jurors', 'allow_edit_until_close', 'show_live_progress', 'sort_order']) + ['event_id' => $clone->id]);
                foreach ($group->criteria as $criterion) {
                    Criterion::query()->create($criterion->only(['rubric_version', 'name', 'description', 'control_type', 'scale_min', 'scale_max', 'minimum_label', 'maximum_label', 'weight', 'required', 'comment_mode', 'help_text', 'sort_order', 'enabled']) + ['event_id' => $clone->id, 'voting_group_id' => $copy->id]);
                }
            }

            return $clone;
        });

        return redirect()->route('admin.events.edit', $clone)->with('success', "Evento clonado con el código {$clone->code}.");
    }

    public function archive(Request $request, VotingEvent $event)
    {
        if (in_array($event->status, ['Live', 'Paused'], true)) {
            throw new DomainException('INVALID_TRANSITION', 'No puedes archivar un evento en ejecución.');
        }$event->update(['status' => 'Archived', 'archived_at' => now()]);
        $this->audit->write($event->id, 'Administrator', (string) $request->user()->id, 'EVENT_ARCHIVED', 'Event', $event->id);

        return redirect()->route('admin.index');
    }

    public function participants(VotingEvent $event)
    {
        $participants = Participant::query()->with('presentation')->where('event_id', $event->id)->orderBy('presentation_order')->get();

        return view('admin.participants', compact('event', 'participants'));
    }

    public function addParticipant(Request $request)
    {
        $data = $request->validate(['event_id' => 'required|uuid|exists:events,id', 'name' => 'required|string|max:180', 'project_title' => 'nullable|string|max:240', 'members' => 'nullable|array|max:30', 'members.*' => 'nullable|string|max:180', 'area' => 'nullable|string|max:160', 'description' => 'nullable|string|max:3000']);
        if (Presentation::query()->where('event_id', $data['event_id'])->where('status', '!=', 'Pending')->exists()) {
            throw new DomainException('CONFIGURATION_LOCKED', 'No puedes agregar participantes después de iniciar el evento.');
        }
        $number = (int) Participant::query()->where('event_id', $data['event_id'])->max('number') + 1;
        DB::transaction(function () use ($data, $number, $request) {
            $p = Participant::query()->create(['event_id' => $data['event_id'], 'number' => $number, 'presentation_order' => $number, 'name' => trim($data['name']), 'project_title' => trim($data['project_title'] ?? '') ?: null, 'members' => Participant::serializeMemberNames($data['members'] ?? []), 'area' => trim($data['area'] ?? '') ?: null, 'description' => trim($data['description'] ?? '') ?: null]);
            $round = (int) VotingEvent::query()->whereKey($data['event_id'])->value('current_round');
            Presentation::query()->create(['event_id' => $data['event_id'], 'participant_id' => $p->id, 'round_number' => $round, 'sequence' => $number]);
            $this->audit->write($data['event_id'], 'Administrator', (string) $request->user()->id, 'PARTICIPANT_CREATED', 'Participant', $p->id, newValue: ['name' => $p->name]);
        });

        return back()->with('success', 'Participante agregado.');
    }

    public function importParticipants(Request $request, VotingEvent $event)
    {
        $request->validate(['csv' => 'required|file|max:2048']);
        if (Presentation::query()->where('event_id', $event->id)->where('status', '!=', 'Pending')->exists()) {
            throw new DomainException('CONFIGURATION_LOCKED', 'No puedes importar participantes después de iniciar el evento.');
        }
        $rows = file($request->file('csv')->getRealPath(), FILE_IGNORE_NEW_LINES);
        $order = (int) Participant::query()->where('event_id', $event->id)->max('presentation_order');
        foreach (array_slice($rows, 1) as $line) {
            $c = str_getcsv($line);
            if (! trim($c[0] ?? '')) {
                continue;
            }$order++;
            $p = Participant::query()->create(['event_id' => $event->id, 'number' => $order, 'presentation_order' => $order, 'name' => trim($c[0]), 'project_title' => trim($c[1] ?? '') ?: null, 'members' => Participant::serializeMemberNames($c[2] ?? null), 'area' => trim($c[3] ?? '') ?: null, 'description' => trim($c[4] ?? '') ?: null]);
            Presentation::query()->create(['event_id' => $event->id, 'participant_id' => $p->id, 'round_number' => $event->current_round, 'sequence' => $order]);
        }

        return back()->with('success', 'Participantes importados.');
    }

    public function editParticipant(Participant $participant)
    {
        $participant->load(['event', 'presentation']);
        $event = $participant->event;

        return view('admin.participant-edit', compact('event', 'participant'));
    }

    public function updateParticipant(Request $request, Participant $participant)
    {
        $data = $request->validate([
            'name' => 'required|string|max:180',
            'project_title' => 'nullable|string|max:240',
            'members' => 'nullable|array|max:30',
            'members.*' => 'nullable|string|max:180',
            'area' => 'nullable|string|max:160',
            'description' => 'nullable|string|max:3000',
        ]);
        $previous = $participant->only(['name', 'project_title', 'members', 'area', 'description']);
        $payload = collect($data)->map(fn ($value) => is_string($value) ? (trim($value) ?: null) : $value)->all();
        $payload['name'] = trim($data['name']);
        $payload['members'] = Participant::serializeMemberNames($data['members'] ?? []);
        $participant->update($payload);
        $this->audit->write($participant->event_id, 'Administrator', (string) $request->user()->id, 'PARTICIPANT_UPDATED', 'Participant', $participant->id, $previous, $participant->only(array_keys($previous)));

        return redirect()->route('admin.participants', $participant->event_id)->with('success', 'La información del equipo fue actualizada.');
    }

    public function changeParticipantStatus(Request $request, Participant $participant)
    {
        $data = $request->validate([
            'status' => 'required|in:Active,Disqualified',
            'reason' => 'nullable|required_if:status,Disqualified|string|min:8|max:1000',
        ]);
        $participant->load(['event', 'presentation']);
        if ($participant->event->status === 'Published') {
            throw new DomainException('RESULTS_ALREADY_PUBLISHED', 'Oculta los resultados antes de cambiar el estado de un equipo.');
        }
        if ($participant->presentation && $participant->event->active_presentation_id === $participant->presentation->id) {
            throw new DomainException('PARTICIPANT_IS_ACTIVE', 'No puedes inhabilitar el equipo que está activo en el escenario.');
        }

        $previous = ['status' => $participant->status, 'reason' => $participant->disqualification_reason];
        DB::transaction(function () use ($participant, $data) {
            $isDisabled = $data['status'] === 'Disqualified';
            $participant->update([
                'status' => $data['status'],
                'disqualification_reason' => $isDisabled ? trim($data['reason']) : null,
            ]);
            if ($participant->presentation?->status === 'Pending' && $isDisabled) {
                $participant->presentation->update(['status' => 'Disqualified']);
            } elseif ($participant->presentation?->status === 'Disqualified' && ! $isDisabled) {
                $participant->presentation->update(['status' => 'Pending']);
            }
        });
        $this->audit->write($participant->event_id, 'Administrator', (string) $request->user()->id, 'PARTICIPANT_STATUS_CHANGED', 'Participant', $participant->id, $previous, ['status' => $participant->status, 'reason' => $participant->disqualification_reason]);
        if (VotingResult::query()->where('event_id', $participant->event_id)->exists()) {
            $this->results->calculate($participant->event_id, (string) $request->user()->id);
        }

        $message = $data['status'] === 'Disqualified' ? 'El equipo fue inhabilitado y ya no participará en el ranking.' : 'El equipo fue habilitado nuevamente.';

        return back()->with('success', $message);
    }

    public function deleteParticipant(Request $request, Participant $participant)
    {
        $participant->load(['event', 'presentation']);
        if ($participant->event->status === 'Published') {
            throw new DomainException('RESULTS_ALREADY_PUBLISHED', 'Oculta los resultados antes de eliminar un equipo.');
        }
        if ($participant->presentation && $participant->event->active_presentation_id === $participant->presentation->id) {
            throw new DomainException('PARTICIPANT_IS_ACTIVE', 'No puedes eliminar el equipo que está activo en el escenario.');
        }
        if (Vote::query()->where('participant_id', $participant->id)->exists()) {
            throw new DomainException('PARTICIPANT_HAS_VOTES', 'Este equipo ya tiene votos. Inhabilítalo para conservar la integridad del evento.');
        }

        $eventId = $participant->event_id;
        $participantName = $participant->name;
        DB::transaction(function () use ($participant, $request, $eventId, $participantName) {
            $this->audit->write($eventId, 'Administrator', (string) $request->user()->id, 'PARTICIPANT_DELETED', 'Participant', $participant->id, ['name' => $participantName]);
            $participant->delete();
        });

        return redirect()->route('admin.participants', $eventId)->with('success', "El equipo {$participantName} fue eliminado.");
    }

    public function jurors(VotingEvent $event)
    {
        $jurors = Juror::query()->where('event_id', $event->id)->orderBy('name')->get()->each(fn ($j) => $j->completed_evaluations = Vote::query()->where('event_id', $event->id)->where('round_number', $event->current_round)->where('actor_id', $j->id)->where('status', '!=', 'Invalidated')->count());

        return view('admin.jurors', compact('event', 'jurors'));
    }

    public function searchJurors(Request $request)
    {
        $term = trim($request->query('query', ''));
        $data = $term === '' || mb_strlen($term) < 2 ? [] : Juror::query()
            ->where('name', 'like', "%{$term}%")
            ->orderByDesc('created_at')
            ->get(['name', 'title', 'email', 'created_at'])
            ->groupBy(fn (Juror $juror) => $this->jurorIdentityKey($juror))
            ->map(fn ($history) => $history->first())
            ->take(8)
            ->values()
            ->map(fn ($juror) => ['name' => $juror->name, 'title' => $juror->title, 'email' => $juror->email])
            ->all();

        return $this->envelope($data);
    }

    public function addJuror(Request $request)
    {
        $data = $request->validate(['event_id' => 'required|uuid|exists:events,id', 'name' => 'required|string|max:180', 'title' => 'nullable|string|max:180', 'email' => 'nullable|email|max:254', 'individual_weight' => 'required|numeric|min:.1|max:10']);
        $event = VotingEvent::query()->findOrFail($data['event_id']);
        $code = Domain::jurorCode();
        $juror = Juror::query()->create(['event_id' => $data['event_id'], 'name' => trim($data['name']), 'title' => trim($data['title'] ?? '') ?: null, 'email' => trim($data['email'] ?? '') ?: null, 'individual_weight' => $data['individual_weight'], 'code_hash' => Domain::hashSecret($code), 'expires_at' => now()->addYear()]);
        $this->audit->write($juror->event_id, 'Administrator', (string) $request->user()->id, 'JUROR_CREATED', 'Juror', $juror->id, newValue: ['name' => $juror->name]);
        $accessUrl = $this->jurorAccessUrl($event, $code);
        $emailSent = $this->sendJurorAccess($juror, $event, $code, $accessUrl);

        return back()
            ->with('issuedJuror', $this->issuedJurorPayload($juror, $event, $code, $accessUrl, $emailSent))
            ->with('issuedJurorCode', "{$juror->name}: {$code}")
            ->with('success', $this->jurorIssuedMessage($emailSent));
    }

    public function regenerateJuror(Request $request, Juror $juror)
    {
        $event = $juror->event;
        $code = Domain::jurorCode();
        $juror->update(['code_hash' => Domain::hashSecret($code), 'status' => 'Active', 'failed_attempts' => 0, 'locked_until' => null]);
        EventSession::query()->where('event_id', $juror->event_id)->where('actor_id', $juror->id)->whereNull('revoked_at')->update(['revoked_at' => now()]);
        $this->audit->write($juror->event_id, 'Administrator', (string) $request->user()->id, 'JUROR_CODE_REGENERATED', 'Juror', $juror->id);
        $accessUrl = $this->jurorAccessUrl($event, $code);
        $emailSent = $this->sendJurorAccess($juror, $event, $code, $accessUrl);

        return back()
            ->with('issuedJuror', $this->issuedJurorPayload($juror, $event, $code, $accessUrl, $emailSent))
            ->with('issuedJurorCode', "{$juror->name}: {$code}")
            ->with('success', $this->jurorIssuedMessage($emailSent, true));
    }

    public function revokeJuror(Request $request, Juror $juror)
    {
        $juror->update(['status' => 'Revoked']);
        EventSession::query()->where('event_id', $juror->event_id)->where('actor_id', $juror->id)->whereNull('revoked_at')->update(['revoked_at' => now()]);
        $this->audit->write($juror->event_id, 'Administrator', (string) $request->user()->id, 'JUROR_CODE_REVOKED', 'Juror', $juror->id);

        return back();
    }

    public function editJuror(Request $request, Juror $juror)
    {
        $juror->load('event');
        $event = $request->boolean('global') ? null : $juror->event;
        $isGlobal = $event === null;

        return view('admin.juror-edit', compact('event', 'juror', 'isGlobal'));
    }

    public function updateJuror(Request $request, Juror $juror)
    {
        $isGlobal = $request->boolean('global');
        $rules = [
            'global' => 'nullable|boolean',
            'name' => 'required|string|max:180',
            'title' => 'nullable|string|max:180',
            'email' => 'nullable|email|max:254',
            'individual_weight' => 'sometimes|required|numeric|min:.1|max:10',
        ];
        $data = $request->validate($rules);
        $payload = [
            'name' => trim($data['name']),
            'title' => trim($data['title'] ?? '') ?: null,
            'email' => trim($data['email'] ?? '') ?: null,
        ];
        if (array_key_exists('individual_weight', $data)) {
            $payload['individual_weight'] = $data['individual_weight'];
        }

        if ($isGlobal) {
            $records = $this->jurorRecordsForIdentity($juror)->get();
            $previous = ['name' => $juror->name, 'title' => $juror->title, 'email' => $juror->email];
            DB::transaction(function () use ($records, $payload, $request, $previous) {
                foreach ($records as $record) {
                    $record->update($payload);
                    $this->audit->write($record->event_id, 'Administrator', (string) $request->user()->id, 'JUROR_UPDATED', 'Juror', $record->id, $previous, $record->only(array_keys($previous)));
                }
            });

            return redirect()->route('admin.jurors.all')->with('success', 'La información del jurado fue actualizada en su historial.');
        }

        $previous = $juror->only(['name', 'title', 'email', 'individual_weight']);
        $juror->update($payload);
        $this->audit->write($juror->event_id, 'Administrator', (string) $request->user()->id, 'JUROR_UPDATED', 'Juror', $juror->id, $previous, $juror->only(array_keys($previous)));

        return redirect()->route('admin.jurors', $juror->event_id)->with('success', 'La información del jurado fue actualizada.');
    }

    public function voters(VotingEvent $event)
    {
        $voters = Voter::query()->where('event_id', $event->id)->orderByDesc('last_access_at')->get()->each(fn ($v) => $v->votes_count = Vote::query()->where('event_id', $event->id)->where('round_number', $event->current_round)->where('actor_id', $v->id)->where('status', '!=', 'Invalidated')->count());

        return view('admin.voters', compact('event', 'voters'));
    }

    public function generateVoterCodes(Request $request, VotingEvent $event)
    {
        $data = $request->validate(['count' => 'required|integer|min:1|max:500', 'label_prefix' => 'nullable|string|max:80']);
        $prefix = trim($data['label_prefix'] ?? '') ?: 'Invitado';
        $existing = Voter::query()->where('event_id', $event->id)->count();
        $issued = [];
        for ($i = 1; $i <= $data['count']; $i++) {
            $code = Domain::jurorCode();
            $label = sprintf('%s %03d', $prefix, $existing + $i);
            Voter::query()->create(['event_id' => $event->id, 'mode' => 'IndividualCode', 'display_name' => $label, 'code_hash' => Domain::hashSecret($code)]);
            $issued[] = [$label, $code, $event->code];
        }$this->audit->write($event->id, 'Administrator', (string) $request->user()->id, 'VOTER_CODES_ISSUED', 'Voter', $event->id, newValue: ['count' => count($issued)]);

        return response()->streamDownload(function () use ($issued) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['nombre', 'codigo', 'evento']);
            foreach ($issued as $row) {
                fputcsv($out, $row);
            }fclose($out);
        }, "codigos-votantes-{$event->code}.csv", ['Content-Type' => 'text/csv; charset=utf-8']);
    }

    public function importVoters(Request $request, VotingEvent $event)
    {
        $request->validate(['csv' => 'required|file|max:2048']);
        $imported = $duplicates = 0;
        $seen = [];
        foreach (file($request->file('csv')->getRealPath(), FILE_IGNORE_NEW_LINES) as $line) {
            $c = str_getcsv($line);
            $id = strtoupper(trim($c[0] ?? ''));
            if (! $id || in_array(strtolower($id), ['identificador', 'id'], true)) {
                continue;
            }if (isset($seen[$id]) || Voter::query()->where('event_id', $event->id)->where('external_id', $id)->exists()) {
                $duplicates++;

                continue;
            }$seen[$id] = true;
            Voter::query()->create(['event_id' => $event->id, 'mode' => 'AttendeeList', 'external_id' => $id, 'display_name' => trim($c[1] ?? '') ?: null]);
            $imported++;
        }$this->audit->write($event->id, 'Administrator', (string) $request->user()->id, 'VOTER_LIST_IMPORTED', 'Voter', $event->id, newValue: compact('imported', 'duplicates'));

        return back()->with('success', "{$imported} votantes importados; {$duplicates} duplicados omitidos.");
    }

    public function changeVoterStatus(Request $request, Voter $voter)
    {
        $data = $request->validate(['status' => 'required|in:'.implode(',', Domain::VOTER_STATUSES)]);
        $previous = $voter->status;
        $voter->update(['status' => $data['status']]);
        if ($data['status'] !== 'Active') {
            EventSession::query()->where('event_id', $voter->event_id)->where('actor_id', $voter->id)->where('actor_type', 'Public')->whereNull('revoked_at')->update(['revoked_at' => now()]);
        }$this->audit->write($voter->event_id, 'Administrator', (string) $request->user()->id, 'VOTER_STATUS_CHANGED', 'Voter', $voter->id, $previous, $data['status']);

        return back();
    }

    public function voting(VotingEvent $event)
    {
        $groups = VotingGroup::query()->with('criteria')->where('event_id', $event->id)->orderBy('sort_order')->get();
        $locked = $this->configurationLocked($event);

        return view('admin.voting', compact('event', 'groups', 'locked'));
    }

    public function saveWeights(Request $request)
    {
        $data = $request->validate(['event_id' => 'required|uuid|exists:events,id', 'jury_weight_percent' => 'required|numeric|min:0|max:100', 'public_weight_percent' => 'required|numeric|min:0|max:100', 'minimum_public_votes' => 'required|integer|min:0', 'minimum_juror_votes' => 'required|integer|min:0', 'allow_juror_edit' => 'nullable|boolean']);
        if (abs($data['jury_weight_percent'] + $data['public_weight_percent'] - 100) > .001) {
            throw new DomainException('WEIGHTS_INVALID', 'Los pesos deben sumar 100%.');
        }$event = VotingEvent::query()->findOrFail($data['event_id']);
        if ($this->configurationLocked($event)) {
            return back()->with('error', 'La evaluación queda bloqueada cuando el evento inicia.');
        }$jury = VotingGroup::query()->where('event_id', $event->id)->where('role_type', 'Jury')->firstOrFail();
        $public = VotingGroup::query()->where('event_id', $event->id)->where('role_type', 'Public')->firstOrFail();
        $jury->update(['weight' => $data['jury_weight_percent'] / 100, 'minimum_votes' => $data['minimum_juror_votes'], 'allow_edit_until_close' => $request->boolean('allow_juror_edit')]);
        $public->update(['weight' => $data['public_weight_percent'] / 100, 'minimum_votes' => $data['minimum_public_votes']]);
        $event->update(['allow_juror_vote_edit' => $request->boolean('allow_juror_edit')]);
        $this->audit->write($event->id, 'Administrator', (string) $request->user()->id, 'VOTING_WEIGHTS_UPDATED', 'VotingGroup', $event->id, newValue: $data);

        return back()->with('success', 'Configuración de evaluación guardada.');
    }

    public function saveRubric(Request $request, VotingEvent $event)
    {
        if ($this->configurationLocked($event)) {
            return back()->with('error', 'La rúbrica queda bloqueada cuando el evento inicia.');
        }$data = $request->validate(['voting_group_id' => 'required|uuid', 'criteria' => 'required|array|min:1', 'criteria.*.id' => 'nullable|uuid', 'criteria.*.name' => 'required|string|max:180', 'criteria.*.description' => 'nullable|string|max:1000', 'criteria.*.weight_percent' => 'required|numeric|min:.01|max:100', 'criteria.*.scale_min' => 'required|numeric|min:0|max:100', 'criteria.*.scale_max' => 'required|numeric|min:.01|max:100', 'criteria.*.minimum_label' => 'nullable|string|max:80', 'criteria.*.maximum_label' => 'nullable|string|max:80', 'criteria.*.required' => 'nullable|boolean', 'criteria.*.comment_mode' => 'required|in:'.implode(',', Domain::COMMENT_MODES), 'criteria.*.help_text' => 'nullable|string|max:500']);
        $group = VotingGroup::query()->with('criteria')->whereKey($data['voting_group_id'])->where('event_id', $event->id)->first();
        if (! $group) {
            return back()->with('error', 'No encontramos la audiencia de evaluación.');
        }if (abs(collect($data['criteria'])->sum('weight_percent') - 100) > .001) {
            return back()->with('error', 'Los pesos de los criterios deben sumar 100%.');
        }$names = collect($data['criteria'])->map(fn ($c) => mb_strtolower(trim($c['name'])));
        if ($names->duplicates()->isNotEmpty()) {
            return back()->with('error', 'No repitas nombres de criterios dentro de la misma rúbrica.');
        }DB::transaction(function () use ($data, $group, $event, $request) {
            $submitted = collect($data['criteria'])->pluck('id')->filter();
            Criterion::query()->where('voting_group_id', $group->id)->whereNotIn('id', $submitted)->delete();
            foreach ($data['criteria'] as $i => $input) {
                if ($input['scale_max'] <= $input['scale_min']) {
                    throw new DomainException('INVALID_RUBRIC', 'Cada criterio necesita una escala válida.');
                }Criterion::query()->updateOrCreate(['id' => $input['id'] ?? Domain::uuid()], ['event_id' => $event->id, 'voting_group_id' => $group->id, 'name' => trim($input['name']), 'description' => trim($input['description'] ?? '') ?: null, 'weight' => $input['weight_percent'] / 100, 'scale_min' => $input['scale_min'], 'scale_max' => $input['scale_max'], 'minimum_label' => trim($input['minimum_label'] ?? '') ?: null, 'maximum_label' => trim($input['maximum_label'] ?? '') ?: null, 'required' => (bool) ($input['required'] ?? false), 'comment_mode' => $input['comment_mode'], 'help_text' => trim($input['help_text'] ?? '') ?: null, 'sort_order' => $i + 1, 'enabled' => true]);
            }$this->audit->write($event->id, 'Administrator', (string) $request->user()->id, 'RUBRIC_UPDATED', 'VotingGroup', $group->id, newValue: ['group' => $group->name, 'criteria' => count($data['criteria'])]);
        });

        return back()->with('success', "Rúbrica de {$group->name} guardada.");
    }

    public function importRubric(Request $request, VotingEvent $event)
    {
        if ($this->configurationLocked($event)) {
            return back()->with('error', 'La rúbrica queda bloqueada cuando el evento inicia.');
        }
        $data = $request->validate([
            'voting_group_id' => 'required|uuid',
            'csv' => 'required|file|max:2048|mimes:csv,txt',
        ]);
        $group = VotingGroup::query()->whereKey($data['voting_group_id'])->where('event_id', $event->id)->first();
        if (! $group) {
            return back()->with('error', 'No encontramos la audiencia de evaluación.');
        }
        $import = $this->rubricCsvImporter->parse($request->file('csv')->getRealPath());

        DB::transaction(function () use ($import, $group, $event, $request) {
            Criterion::query()->where('voting_group_id', $group->id)->delete();
            foreach ($import['criteria'] as $index => $criterion) {
                Criterion::query()->create([
                    'id' => Domain::uuid(),
                    'event_id' => $event->id,
                    'voting_group_id' => $group->id,
                    'name' => $criterion['name'],
                    'description' => $criterion['description'],
                    'weight' => $criterion['weight_percent'] / 100,
                    'scale_min' => $criterion['scale_min'],
                    'scale_max' => $criterion['scale_max'],
                    'minimum_label' => $criterion['minimum_label'],
                    'maximum_label' => $criterion['maximum_label'],
                    'required' => $criterion['required'],
                    'comment_mode' => $criterion['comment_mode'],
                    'help_text' => $criterion['help_text'],
                    'sort_order' => $index + 1,
                    'enabled' => true,
                ]);
            }
            $this->audit->write($event->id, 'Administrator', (string) $request->user()->id, 'RUBRIC_IMPORTED', 'VotingGroup', $group->id, newValue: ['group' => $group->name, 'criteria' => count($import['criteria']), 'columns' => $import['columns']]);
        });

        return back()->with('success', count($import['criteria'])." criterios importados para {$group->name}.");
    }

    public function live(VotingEvent $event)
    {
        $event->load('branding');
        $participants = Participant::query()->with('presentation')->where('event_id', $event->id)->orderBy('presentation_order')->get();
        $state = $this->queries->liveStateByCode($event->code);
        $voted = $state['presentationId'] ? Vote::query()->where('presentation_id', $state['presentationId'])->where('role_type', 'Jury')->where('status', '!=', 'Invalidated')->pluck('actor_id') : collect();
        $jurors = Juror::query()->where('event_id', $event->id)->where('status', 'Active')->orderBy('name')->get()->map(fn ($j) => ['id' => $j->id, 'name' => $j->name, 'hasVoted' => $voted->contains($j->id)]);

        return view('admin.live', compact('event', 'participants', 'state', 'jurors'));
    }

    public function liveState(VotingEvent $event)
    {
        return $this->envelope($this->queries->liveStateByCode($event->code));
    }

    public function control(Request $request, VotingEvent $event, string $operation)
    {
        if (strtolower($operation) === 'restart') {
            $hasVotes = Vote::query()->where('event_id', $event->id)->where('round_number', $event->current_round)->where('status', '!=', 'Invalidated')->exists();
            $hasResults = VotingResult::query()->where('event_id', $event->id)->where('round_number', $event->current_round)->exists();
            if ($hasVotes && ($event->status !== 'Published' || ! $hasResults)) {
                $this->results->calculate($event->id, (string) $request->user()->id);
            }
            $round = $this->rounds->restart($event->id, (string) $request->user()->id);

            return back()->with('success', "La ronda {$round} está lista. Puedes abrir el lobby cuando quieras.");
        }
        $this->liveControl->operate($event->id, $operation, (string) $request->user()->id, $request->input('participant_id'), $request->input('presentation_id'), $request->input('reason'));
        if (in_array(strtolower($operation), ['close', 'finish'], true)) {
            $this->results->calculate($event->id, (string) $request->user()->id);
        }

        return back()->with('success', 'Estado actualizado.');
    }

    public function eventResults(Request $request, VotingEvent $event)
    {
        $round = max(1, min((int) $event->current_round, (int) $request->integer('round', $event->current_round)));
        $ranking = $this->results->ranking($event->id, $round);
        $rounds = range(1, (int) $event->current_round);
        $isCurrentRound = $round === (int) $event->current_round;
        $roundPublished = VotingResult::query()->where('event_id', $event->id)->where('round_number', $round)->whereNotNull('published_at')->exists();

        return view('admin.results', compact('event', 'ranking', 'round', 'rounds', 'isCurrentRound', 'roundPublished'));
    }

    public function recalculate(Request $request, VotingEvent $event)
    {
        $this->results->calculate($event->id, (string) $request->user()->id);

        return back()->with('success', 'Resultados recalculados.');
    }

    public function publish(Request $request, VotingEvent $event)
    {
        $this->results->publish($event->id, (string) $request->user()->id);

        return back()->with('success', 'Resultados publicados.');
    }

    public function unpublish(Request $request, VotingEvent $event)
    {
        $this->results->unpublish($event->id, (string) $request->user()->id);

        return back()->with('success', 'Resultados ocultos.');
    }

    public function invalidateVote(Request $request, Vote $vote)
    {
        $data = $request->validate(['event_id' => 'required|uuid', 'reason' => 'required|string|min:8|max:1000']);
        $this->votes->invalidate($vote->id, $data['reason'], (string) $request->user()->id);
        $this->results->calculate($data['event_id'], (string) $request->user()->id);

        return back();
    }

    public function reports(VotingEvent $event)
    {
        $totalVotes = Vote::query()->where('event_id', $event->id)->where('round_number', $event->current_round)->where('status', '!=', 'Invalidated')->count();
        $activeJurors = Juror::query()->where('event_id', $event->id)->where('status', 'Active')->count();
        $auditEntries = AuditEntry::query()->where('event_id', $event->id)->orderByDesc('timestamp')->limit(100)->get();
        $templates = EventTemplate::query()->where('active', true)->orderBy('name')->get();
        $settings = AppSetting::query()->orderBy('key')->get();

        return view('admin.reports', compact('event', 'totalVotes', 'activeJurors', 'auditEntries', 'templates', 'settings'));
    }

    public function export(Request $request, VotingEvent $event): StreamedResponse
    {
        $ranking = $this->results->ranking($event->id);
        $this->audit->write($event->id, 'Administrator', (string) $request->user()->id, 'REPORT_EXPORTED', 'Event', $event->id, note: 'CSV de resultados');

        return response()->streamDownload(function () use ($ranking) {
            $o = fopen('php://output', 'w');
            fwrite($o, "\xEF\xBB\xBF");
            fputcsv($o, ['Posición', 'Participante', 'Proyecto', 'Jurado', 'Público', 'Total', 'Votos jurado', 'Votos público', 'Quórum']);
            foreach ($ranking as $r) {
                fputcsv($o, [$r['rank'], $r['participantName'], $r['projectTitle'], number_format($r['juryScore'], 4, '.', ''), number_format($r['publicScore'], 4, '.', ''), number_format($r['finalScore'], 4, '.', ''), $r['juryVotes'], $r['publicVotes'], $r['quorumMet'] ? 'Sí' : 'No']);
            }fclose($o);
        }, "resultados-{$event->id}.csv", ['Content-Type' => 'text/csv; charset=utf-8']);
    }

    public function eventQr(string $eventCode)
    {
        $target = route('event.code', ['code' => Domain::normalizeCode($eventCode)]);

        return response($this->qr->png($target))->header('Content-Type', 'image/png');
    }

    private function eventDirectory()
    {
        return VotingEvent::query()->withCount(['participants', 'jurors'])->get()->map(function ($event) {
            $event->votes_count = Vote::query()->where('event_id', $event->id)->where('status', '!=', 'Invalidated')->count();

            return $event;
        })->sortByDesc('starts_at')->values();
    }

    private function jurorIdentityKey(Juror $juror): string
    {
        $email = $this->normalizeJurorValue($juror->email);
        if ($email !== '') {
            return 'email:'.$email;
        }

        return 'name:'.$this->normalizeJurorValue($juror->name).'|title:'.$this->normalizeJurorValue($juror->title);
    }

    private function jurorRecordsForIdentity(Juror $juror)
    {
        $email = $this->normalizeJurorValue($juror->email);
        if ($email !== '') {
            return Juror::query()->whereRaw("LOWER(COALESCE(TRIM(email), '')) = ?", [$email]);
        }

        return Juror::query()
            ->whereRaw("LOWER(COALESCE(TRIM(name), '')) = ?", [$this->normalizeJurorValue($juror->name)])
            ->whereRaw("LOWER(COALESCE(TRIM(title), '')) = ?", [$this->normalizeJurorValue($juror->title)]);
    }

    private function normalizeJurorValue(?string $value): string
    {
        return mb_strtolower(trim((string) preg_replace('/\s+/', ' ', $value ?? '')));
    }

    private function jurorAccessUrl(VotingEvent $event, string $code): string
    {
        return route('jury.access', ['event' => $event->code, 'code' => $code]);
    }

    private function sendJurorAccess(Juror $juror, VotingEvent $event, string $code, string $accessUrl): ?bool
    {
        if (! $juror->email) {
            return null;
        }

        try {
            Mail::to($juror->email)->send(new JurorAccessMail($juror, $event, $code, $accessUrl));

            return true;
        } catch (\Throwable $exception) {
            report($exception);

            return false;
        }
    }

    private function issuedJurorPayload(Juror $juror, VotingEvent $event, string $code, string $accessUrl, ?bool $emailSent): array
    {
        return [
            'name' => $juror->name,
            'code' => $code,
            'eventName' => $event->name,
            'eventCode' => $event->code,
            'accessUrl' => $accessUrl,
            'qrDataUri' => 'data:image/png;base64,'.base64_encode($this->qr->png($accessUrl, 280)),
            'emailSent' => $emailSent,
        ];
    }

    private function jurorIssuedMessage(?bool $emailSent, bool $regenerated = false): string
    {
        $action = $regenerated ? 'regenerado' : 'creado';

        return match ($emailSent) {
            true => "Acceso {$action}. El código y el enlace fueron enviados al correo del jurado.",
            false => "Acceso {$action}, pero no se pudo enviar el correo. Comparte el código desde esta pantalla.",
            default => "Acceso {$action}. Comparte el código desde esta pantalla.",
        };
    }

    private function validateEvent(Request $request, bool $creating): array
    {
        return $request->validate(['name' => 'required|string|max:180', 'subtitle' => 'nullable|string|max:240', 'description' => 'nullable|string|max:4000', 'category' => 'nullable|string|max:100', 'organizer' => 'nullable|string|max:180', 'venue' => 'nullable|string|max:240', 'time_zone' => 'required|string|max:80', 'starts_at_local' => 'nullable|date', 'ends_at_local' => 'nullable|date', 'code' => $creating ? 'nullable|string|max:12' : 'required|string|max:12', 'public_access_mode' => 'required|in:'.implode(',', Domain::ACCESS_MODES), 'results_visibility' => 'required|in:'.implode(',', Domain::RESULTS_VISIBILITIES), 'presentation_duration_seconds' => 'required|integer|min:30|max:7200', 'voting_duration_seconds' => 'required|integer|min:15|max:3600', 'jury_weight_percent' => $creating ? 'required|numeric|min:0|max:100' : 'nullable', 'public_weight_percent' => $creating ? 'required|numeric|min:0|max:100' : 'nullable', 'allow_juror_vote_edit' => 'nullable|boolean']);
    }

    private function eventPayload(array $data): array
    {
        return ['name' => trim($data['name']), 'subtitle' => trim($data['subtitle'] ?? '') ?: null, 'description' => trim($data['description'] ?? '') ?: null, 'category' => trim($data['category'] ?? '') ?: null, 'organizer' => trim($data['organizer'] ?? '') ?: null, 'venue' => trim($data['venue'] ?? '') ?: null, 'time_zone' => $data['time_zone'], 'starts_at' => $this->utc($data['starts_at_local'] ?? null, $data['time_zone']), 'ends_at' => $this->utc($data['ends_at_local'] ?? null, $data['time_zone']), 'public_access_mode' => $data['public_access_mode'], 'results_visibility' => $data['results_visibility'], 'presentation_duration_seconds' => $data['presentation_duration_seconds'], 'voting_duration_seconds' => $data['voting_duration_seconds'], 'allow_juror_vote_edit' => (bool) ($data['allow_juror_vote_edit'] ?? false)];
    }

    private function utc(?string $value, string $zone): ?Carbon
    {
        return $value ? Carbon::parse($value, $zone)->utc() : null;
    }

    private function configurationLocked(VotingEvent $event): bool
    {
        return in_array($event->status, ['Live', 'Paused', 'Finished', 'Published', 'Archived'], true) || Presentation::query()->where('event_id', $event->id)->where('status', '!=', 'Pending')->exists();
    }

    private function addDefaultRubrics(VotingEvent $event, float $juryWeight, float $publicWeight): void
    {
        foreach ([['Jurado', 'Jury', $juryWeight, [['Claridad', 'La propuesta se comunica con precisión.', .25], ['Innovación', 'La solución presenta un enfoque novedoso.', .25], ['Impacto', 'La propuesta genera valor verificable.', .25], ['Presentación', 'El equipo argumenta y demuestra su solución.', .25]]], ['Público', 'Public', $publicWeight, [['Claridad del resultado', 'La propuesta se entiende fácilmente.', .34], ['Utilidad', 'La solución parece aplicable y valiosa.', .33], ['Presentación', 'El equipo comunica bien su proceso.', .33]]]] as $gi => $g) {
            [$name,$role,$weight,$criteria] = $g;
            $group = VotingGroup::query()->create(['event_id' => $event->id, 'name' => $name, 'role_type' => $role, 'weight' => $weight, 'minimum_votes' => 1, 'allow_edit_until_close' => $role === 'Jury', 'sort_order' => $gi + 1]);
            foreach ($criteria as $i => [$cn,$cd,$cw]) {
                Criterion::query()->create(['event_id' => $event->id, 'voting_group_id' => $group->id, 'sort_order' => $i + 1, 'name' => $cn, 'description' => $cd, 'weight' => $cw, 'minimum_label' => 'Deficiente', 'maximum_label' => 'Excelente']);
            }
        }
    }

    private function envelope(array $data)
    {
        return response()->json(['ok' => true, 'data' => $data, 'error' => null, 'meta' => ['requestId' => Domain::uuid(), 'serverTime' => now()->toIso8601String()]]);
    }
}
