<?php

namespace Tests\Feature;

use App\Mail\JurorAccessMail;
use App\Models\Criterion;
use App\Models\Juror;
use App\Models\Participant;
use App\Models\Presentation;
use App\Models\User;
use App\Models\Vote;
use App\Models\Voter;
use App\Models\VotingEvent;
use App\Models\VotingResult;
use App\Services\EventQueryService;
use App\Services\LiveControlService;
use App\Services\ResultService;
use App\Services\VoteService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

class VotingPlatformTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_demo_pages_and_credentials_are_available(): void
    {
        $this->get('/admin')->assertRedirect('/admin/acceso');

        $this->get('/e/BTP726')
            ->assertOk()
            ->assertSee('Plataforma de Votación')
            ->assertSee('BTP726')
            ->assertSee('favicon.png');

        $this->post('/evento/acceder', ['event_code' => 'BTP726'])
            ->assertOk()
            ->assertSee('Tu nombre')
            ->assertSee('Indica tu nombre para continuar.');

        $confirmation = $this->post('/jurado/validar', [
            'event_code' => 'BTP726',
            'juror_code' => 'J7K4-PQ9M',
        ]);
        $confirmation->assertOk()
            ->assertSee('Elena Rodríguez')
            ->assertSee('Confirmar identidad');

        preg_match('/name="confirmation_token" value="([^"]+)"/', $confirmation->getContent(), $matches);
        $this->assertNotEmpty($matches[1] ?? null);
        $this->post('/jurado/confirmar', ['confirmation_token' => html_entity_decode($matches[1])])
            ->assertRedirect('/jurado/panel')
            ->assertCookie('innovamente_juror');

        $this->post('/admin/acceso', [
            'email' => 'admin@innovamente.local',
            'password' => 'Admin-InnovaMente!2026',
        ])->assertRedirect('/admin');

        $this->get('/admin')
            ->assertOk()
            ->assertSee('Eventos Activos')
            ->assertSee('Batalla de Prompts');
    }

    public function test_juror_access_is_emailed_and_can_be_opened_from_the_prefilled_link(): void
    {
        Mail::fake();
        $admin = User::query()->where('email', 'admin@innovamente.local')->firstOrFail();
        $event = VotingEvent::query()->where('code', 'BTP726')->firstOrFail();

        $this->actingAs($admin)->get(route('admin.jurors', $event))
            ->assertOk()
            ->assertSee($event->name)
            ->assertSee($event->code)
            ->assertDontSee('Estás trabajando dentro de un evento')
            ->assertDontSee('Volver a Editar evento');
        $this->actingAs($admin)->get(route('admin.projects'))->assertOk()->assertSee('Proyectos');

        $response = $this->actingAs($admin)->post(route('admin.jurors.add'), [
            'event_id' => $event->id,
            'name' => 'Jurado de Prueba',
            'title' => 'Evaluador QA',
            'email' => 'jurado.qa@example.com',
            'individual_weight' => 1.5,
        ]);

        $response->assertRedirect();
        $issued = $response->getSession()->get('issuedJuror');
        $this->assertNotEmpty($issued['code'] ?? null);
        $this->assertStringContainsString('event=BTP726', $issued['accessUrl']);
        $this->assertStringContainsString('code=', $issued['accessUrl']);
        $this->assertStringStartsWith('data:image/png;base64,', $issued['qrDataUri']);
        Mail::assertSent(JurorAccessMail::class);

        $this->get($issued['accessUrl'])
            ->assertOk()
            ->assertSee('value="BTP726"', false)
            ->assertSee('value="'.$issued['code'].'"', false);
    }

    public function test_global_juror_directory_deduplicates_history_and_edits_without_changing_weights(): void
    {
        $admin = User::query()->where('email', 'admin@innovamente.local')->firstOrFail();
        $firstEvent = VotingEvent::query()->where('code', 'BTP726')->firstOrFail();
        $secondEvent = VotingEvent::query()->create(['code' => 'QA2026', 'name' => 'Evento QA']);
        $jurorData = ['name' => 'Jurado Recurrente', 'title' => 'Mentor', 'email' => 'recurrente@example.com', 'code_hash' => 'not-used'];
        $first = Juror::query()->create($jurorData + ['event_id' => $firstEvent->id, 'individual_weight' => 1.25]);
        $second = Juror::query()->create($jurorData + ['event_id' => $secondEvent->id, 'individual_weight' => 2.5]);

        $directory = $this->actingAs($admin)->get(route('admin.jurors.all'));
        $directory->assertOk()->assertSee('2 evento(s)');
        $this->assertSame(1, substr_count($directory->getContent(), 'Jurado Recurrente'));

        $this->actingAs($admin)->post(route('admin.jurors.update', $first).'?global=1', [
            'global' => 1,
            'name' => 'Jurado Actualizado',
            'title' => 'Directora',
            'email' => 'actualizada@example.com',
        ])->assertRedirect(route('admin.jurors.all'));

        $this->assertDatabaseHas('jurors', ['id' => $first->id, 'name' => 'Jurado Actualizado', 'individual_weight' => 1.25]);
        $this->assertDatabaseHas('jurors', ['id' => $second->id, 'name' => 'Jurado Actualizado', 'individual_weight' => 2.5]);
    }

    public function test_public_access_creates_a_reusable_device_voter_and_session(): void
    {
        $response = $this->withCookie('innovamente_device', 'qa-device-01')->post('/evento/acceder', [
            'event_code' => 'BTP726',
            'display_name' => 'Visitante QA',
        ]);

        $response->assertRedirect('/evento')->assertCookie('innovamente_public');
        $this->assertDatabaseHas('voters', ['display_name' => 'Visitante QA', 'status' => 'Active']);
        $this->assertDatabaseCount('event_sessions', 1);

        $this->withCookie('innovamente_device', 'qa-device-01')->post('/evento/acceder', [
            'event_code' => 'BTP726',
            'display_name' => 'Visitante QA Actualizado',
        ])->assertRedirect('/evento');

        $this->assertDatabaseCount('voters', 1);
        $this->assertDatabaseHas('voters', ['display_name' => 'Visitante QA Actualizado']);
    }

    public function test_public_and_jury_lobbies_redirect_to_published_results(): void
    {
        $event = VotingEvent::query()->where('code', 'BTP726')->firstOrFail();

        $publicAccess = $this->post('/evento/acceder', [
            'event_code' => $event->code,
            'display_name' => 'Visitante en lobby',
        ]);
        $publicToken = $publicAccess->getCookie('innovamente_public')->getValue();

        $this->withCookie('innovamente_public', $publicToken)
            ->get(route('public.lobby'))
            ->assertOk()
            ->assertSee('data-results-redirect', false)
            ->assertSee('transition=lobby', false);

        $confirmation = $this->post('/jurado/validar', [
            'event_code' => $event->code,
            'juror_code' => 'J7K4-PQ9M',
        ]);
        preg_match('/name="confirmation_token" value="([^"]+)"/', $confirmation->getContent(), $matches);
        $jurorLogin = $this->post('/jurado/confirmar', ['confirmation_token' => html_entity_decode($matches[1])]);
        $jurorToken = $jurorLogin->getCookie('innovamente_juror')->getValue();

        $this->withCookie('innovamente_juror', $jurorToken)
            ->get(route('jury.dashboard'))
            ->assertOk()
            ->assertSee('data-results-redirect', false)
            ->assertSee('transition=lobby', false);

        $event->update(['status' => 'Published']);
        $resultsUrl = route('projection.ranking', $event->code).'?transition=lobby';
        $this->withCookie('innovamente_public', $publicToken)
            ->get(route('public.lobby'))
            ->assertRedirect($resultsUrl);
        $this->withCookie('innovamente_juror', $jurorToken)
            ->get(route('jury.dashboard'))
            ->assertRedirect($resultsUrl);
    }

    public function test_access_limits_do_not_block_valid_jurors_or_distinct_audience_devices_on_the_same_network(): void
    {
        foreach (range(1, 7) as $attempt) {
            $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.25'])
                ->post('/jurado/validar', [
                    'event_code' => 'BTP726',
                    'juror_code' => 'J7K4-PQ9M',
                ])
                ->assertOk();
        }

        foreach (range(1, 10) as $device) {
            $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.25'])
                ->withCookie('innovamente_device', 'shared-network-device-'.$device)
                ->post('/evento/acceder', [
                    'event_code' => 'BTP726',
                    'display_name' => 'Asistente '.$device,
                ])
                ->assertRedirect('/evento');
        }
    }

    public function test_live_control_vote_idempotency_and_weighted_results(): void
    {
        $event = VotingEvent::query()->with(['presentations', 'jurors', 'groups.criteria'])->where('code', 'BTP726')->firstOrFail();
        $presentation = $event->presentations->first();
        $operatorId = (string) Str::uuid();

        $control = app(LiveControlService::class);
        $control->operate($event->id, 'start', $operatorId);
        $control->operate($event->id, 'presentation', $operatorId, $presentation->participant_id);
        $control->operate($event->id, 'open', $operatorId, presentationId: $presentation->id);

        $event->refresh();
        $presentation->refresh();
        $this->assertSame('Live', $event->status);
        $this->assertSame('VotingOpen', $presentation->status);

        $voteService = app(VoteService::class);
        $juryCriteria = $event->groups->firstWhere('role_type', 'Jury')->criteria;
        $publicCriteria = $event->groups->firstWhere('role_type', 'Public')->criteria;

        foreach ($event->jurors->take(3) as $index => $juror) {
            $session = $this->sessionPayload($event->id, $juror->id, 'Juror');
            $requestId = 'jury-'.$index.'-'.Str::uuid();
            $receipt = $voteService->submit($session, $presentation->id, $requestId, $this->answers($juryCriteria, 5));
            $this->assertSame(100.0, $receipt['normalizedScore']);

            if ($index === 0) {
                $retry = $voteService->submit($session, $presentation->id, $requestId, $this->answers($juryCriteria, 5));
                $this->assertTrue($retry['wasIdempotent']);
            }
        }

        foreach (range(1, 3) as $index) {
            $voter = Voter::query()->create([
                'event_id' => $event->id,
                'mode' => 'Device',
                'device_hash' => hash('sha256', 'result-device-'.$index),
                'display_name' => 'Público '.$index,
            ]);
            $voteService->submit(
                $this->sessionPayload($event->id, $voter->id, 'Public'),
                $presentation->id,
                'public-'.$index.'-'.Str::uuid(),
                $this->answers($publicCriteria, 3),
            );
        }

        $this->assertSame(6, Vote::query()->count());
        $ranking = app(ResultService::class)->calculate($event->id, $operatorId);

        $this->assertSame($presentation->participant_id, $ranking[0]['participantId']);
        $this->assertSame(100.0, $ranking[0]['juryScore']);
        $this->assertSame(50.0, $ranking[0]['publicScore']);
        $this->assertSame(85.0, $ranking[0]['finalScore']);
        $this->assertTrue($ranking[0]['quorumMet']);

        $control->operate($event->id, 'close', $operatorId, presentationId: $presentation->id);
        $this->assertSame('VotingClosed', $presentation->fresh()->status);
        $this->assertNull($event->fresh()->active_presentation_id);
    }

    public function test_qr_and_live_json_endpoints_return_expected_formats(): void
    {
        $this->get('/qr/evento/BTP726')
            ->assertOk()
            ->assertHeader('content-type', 'image/png');

        $this->getJson('/api/projection/BTP726')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.eventCode', 'BTP726');
    }

    public function test_administrator_can_edit_disable_enable_and_delete_a_participant_without_votes(): void
    {
        $admin = User::query()->where('email', 'admin@innovamente.local')->firstOrFail();
        $event = VotingEvent::query()->where('code', 'BTP726')->firstOrFail();
        $participant = Participant::query()->with('presentation')->where('event_id', $event->id)->orderBy('presentation_order')->firstOrFail();
        $presentationId = $participant->presentation->id;

        foreach (['admin.participants.edit', 'admin.participants.update', 'admin.participants.status', 'admin.participants.delete'] as $routeName) {
            $this->assertTrue(Route::has($routeName), "La ruta {$routeName} debe estar registrada.");
        }

        $this->actingAs($admin)
            ->get(route('admin.participants', $event))
            ->assertOk()
            ->assertSee(route('admin.participants.edit', $participant), false)
            ->assertSee(route('admin.participants.status', $participant), false)
            ->assertSee(route('admin.participants.delete', $participant), false);

        $this->actingAs($admin)
            ->get(route('admin.participants.edit', $participant))
            ->assertOk()
            ->assertSee('Editar equipo');

        $this->actingAs($admin)->post(route('admin.participants.update', $participant), [
            'name' => 'Equipo QA Renovado',
            'project_title' => 'Proyecto QA',
            'members' => ['Ada Lovelace', 'Linus Torvalds', 'Ada Lovelace', ''],
            'area' => 'Tecnología',
            'description' => 'Descripción actualizada para pruebas.',
        ])->assertRedirect(route('admin.participants', $event));
        $this->assertDatabaseHas('participants', ['id' => $participant->id, 'name' => 'Equipo QA Renovado', 'project_title' => 'Proyecto QA']);
        $this->assertSame(['Ada Lovelace', 'Linus Torvalds'], $participant->fresh()->member_names);

        $this->actingAs($admin)->post(route('admin.participants.status', $participant), [
            'status' => 'Disqualified',
            'reason' => 'Inhabilitado durante la prueba funcional.',
        ])->assertRedirect();
        $this->assertDatabaseHas('participants', ['id' => $participant->id, 'status' => 'Disqualified']);
        $this->assertDatabaseHas('presentations', ['id' => $presentationId, 'status' => 'Disqualified']);

        $this->actingAs($admin)->post(route('admin.participants.status', $participant), ['status' => 'Active'])->assertRedirect();
        $this->assertDatabaseHas('participants', ['id' => $participant->id, 'status' => 'Active']);
        $this->assertDatabaseHas('presentations', ['id' => $presentationId, 'status' => 'Pending']);

        $this->actingAs($admin)->post(route('admin.participants.delete', $participant))->assertRedirect(route('admin.participants', $event));
        $this->assertDatabaseMissing('participants', ['id' => $participant->id]);
        $this->assertDatabaseMissing('presentations', ['id' => $presentationId]);
    }

    public function test_public_projection_has_voting_access_and_published_results_are_graphical(): void
    {
        $event = VotingEvent::query()->where('code', 'BTP726')->firstOrFail();
        $actorId = (string) Str::uuid();
        $this->get(route('projection.ranking', $event->code))
            ->assertOk()
            ->assertSee('Los resultados aún no han sido publicados')
            ->assertSee('data-results-published="false"', false)
            ->assertDontSee('Podio de ganadores');
        $this->get(route('projection.live', $event->code))
            ->assertOk()
            ->assertSee('Entrar a votar')
            ->assertSee('Acceso de jurados')
            ->assertSee('data-results-redirect', false)
            ->assertSee('transition=projection', false);
        $event->update(['require_quorum_to_publish' => false]);
        app(ResultService::class)->calculate($event->id, $actorId);
        app(ResultService::class)->publish($event->id, $actorId);

        $this->get(route('projection.live', $event->code))
            ->assertRedirect(route('projection.ranking', $event->code));

        $this->get(route('projection.ranking', $event->code))
            ->assertOk()
            ->assertSee('Preparando los resultados')
            ->assertSee('data-results-duration="30000"', false)
            ->assertSee('data-results-published="true"', false)
            ->assertSee('data-results-force-animation="false"', false)
            ->assertSee('Podio de ganadores')
            ->assertSee('Rendimiento por equipo')
            ->assertSee('ranking-bar-row', false)
            ->assertSee('Desglose completo');

        $this->get(route('projection.ranking', $event->code).'?transition=projection')
            ->assertOk()
            ->assertSee('is-calculating', false)
            ->assertSee('data-results-force-animation="true"', false)
            ->assertSee('Preparando los resultados');

        $admin = User::query()->where('email', 'admin@innovamente.local')->firstOrFail();
        $this->actingAs($admin)->get(route('admin.live', $event))
            ->assertOk()
            ->assertSee('Ver resultados')
            ->assertSee(route('projection.ranking', $event->code), false);
    }

    public function test_live_panels_and_ballot_state_include_complete_team_details(): void
    {
        $event = VotingEvent::query()->with('presentations.participant')->where('code', 'BTP726')->firstOrFail();
        $presentation = $event->presentations->first();
        $participant = $presentation->participant;
        $participant->update([
            'members' => Participant::serializeMemberNames(['Ada Lovelace', 'Linus Torvalds']),
            'area' => 'Tecnología educativa',
            'description' => 'Una solución para aprender con inteligencia artificial.',
        ]);

        $control = app(LiveControlService::class);
        $control->operate($event->id, 'start', (string) Str::uuid());
        $control->operate($event->id, 'presentation', (string) Str::uuid(), $participant->id);

        $state = app(EventQueryService::class)->liveStateByCode($event->code);
        $this->assertSame(['Ada Lovelace', 'Linus Torvalds'], $state['participantMembers']);
        $this->assertSame('Tecnología educativa', $state['participantArea']);
        $this->assertSame('Una solución para aprender con inteligencia artificial.', $state['participantDescription']);

        $this->get(route('projection.live', $event->code))
            ->assertOk()
            ->assertSee('projection-participant', false)
            ->assertSee('Descripción del proyecto')
            ->assertSee('Integrantes')
            ->assertSee('Ada Lovelace')
            ->assertSee('Linus Torvalds')
            ->assertSee('Tecnología educativa')
            ->assertSee('Una solución para aprender con inteligencia artificial.');

        $admin = User::query()->where('email', 'admin@innovamente.local')->firstOrFail();
        $this->actingAs($admin)->get(route('admin.live', $event))
            ->assertOk()
            ->assertSee('Ada Lovelace')
            ->assertSee('Linus Torvalds')
            ->assertSee('Tecnología educativa');
    }

    public function test_admin_saves_jury_and_public_rubrics_together(): void
    {
        $admin = User::query()->where('email', 'admin@innovamente.local')->firstOrFail();
        $event = VotingEvent::query()->with('groups.criteria')->where('code', 'BTP726')->firstOrFail();
        $event->update(['status' => 'Draft']);

        $page = $this->actingAs($admin)->get(route('admin.voting', $event));
        $page->assertOk()
            ->assertSee('data-rubrics-form', false)
            ->assertSee('Guardar todas las rúbricas')
            ->assertDontSee('> Guardar rúbrica</button>', false);

        $rubrics = $event->groups->values()->map(function ($group, $groupIndex) {
            return [
                'voting_group_id' => $group->id,
                'criteria' => $group->criteria->values()->map(function ($criterion, $criterionIndex) use ($groupIndex) {
                    return [
                        'id' => $criterion->id,
                        'name' => $criterionIndex === 0 ? 'Criterio actualizado '.($groupIndex + 1) : $criterion->name,
                        'description' => $criterion->description,
                        'weight_percent' => $criterion->weight * 100,
                        'scale_min' => $criterion->scale_min,
                        'scale_max' => $criterion->scale_max,
                        'minimum_label' => $criterion->minimum_label,
                        'maximum_label' => $criterion->maximum_label,
                        'required' => $criterion->required ? 1 : 0,
                        'comment_mode' => $criterion->comment_mode,
                        'help_text' => $criterion->help_text,
                    ];
                })->all(),
            ];
        })->all();

        $this->actingAs($admin)->post(route('admin.voting.rubric', $event), ['rubrics' => $rubrics])
            ->assertRedirect()
            ->assertSessionHas('success', 'Todas las rúbricas se guardaron correctamente.');

        foreach ($event->groups->values() as $groupIndex => $group) {
            $this->assertDatabaseHas('criteria', [
                'voting_group_id' => $group->id,
                'name' => 'Criterio actualizado '.($groupIndex + 1),
            ]);
        }
    }

    public function test_admin_can_import_complete_rubrics_and_see_csv_hints(): void
    {
        $admin = User::query()->where('email', 'admin@innovamente.local')->firstOrFail();
        $event = VotingEvent::query()->with('groups')->where('code', 'BTP726')->firstOrFail();
        $jury = $event->groups->firstWhere('role_type', 'Jury');
        $csv = implode("\n", [
            'nombre,descripcion,peso,escala_minima,escala_maxima,etiqueta_minima,etiqueta_maxima,comentario,respuesta_obligatoria,ayuda_para_evaluar',
            'Impacto,Valor generado,60,1,10,Bajo,Alto,Required,Sí,Coteja alcance y evidencia',
            'Viabilidad,Posibilidad de ejecución,40,0,5,Débil,Sólida,Optional,No,Valida recursos y tiempo',
        ]);

        $this->actingAs($admin)->get(route('admin.voting', $event))
            ->assertOk()
            ->assertSee('Ver estructura CSV')
            ->assertSee('ayuda_para_evaluar');
        $this->actingAs($admin)->get(route('admin.participants', $event))->assertSee('Ver estructura CSV');
        $this->actingAs($admin)->get(route('admin.voters', $event))->assertSee('Ver estructura CSV');

        $this->actingAs($admin)->post(route('admin.voting.rubric.import', $event), [
            'voting_group_id' => $jury->id,
            'csv' => UploadedFile::fake()->createWithContent('rubrica.csv', $csv),
        ])->assertRedirect()->assertSessionHas('success');

        $criteria = Criterion::query()->where('voting_group_id', $jury->id)->orderBy('sort_order')->get();
        $this->assertCount(2, $criteria);
        $this->assertSame('Impacto', $criteria[0]->name);
        $this->assertEquals(.6, $criteria[0]->weight);
        $this->assertEquals(10, $criteria[0]->scale_max);
        $this->assertSame('Bajo', $criteria[0]->minimum_label);
        $this->assertSame('Required', $criteria[0]->comment_mode);
        $this->assertTrue($criteria[0]->required);
        $this->assertSame('Coteja alcance y evidencia', $criteria[0]->help_text);
        $this->assertFalse($criteria[1]->required);
    }

    public function test_event_can_restart_as_a_new_round_without_losing_previous_votes_or_results(): void
    {
        $admin = User::query()->where('email', 'admin@innovamente.local')->firstOrFail();
        $event = VotingEvent::query()->with(['presentations', 'jurors', 'groups.criteria'])->where('code', 'BTP726')->firstOrFail();
        $presentation = $event->presentations->first();
        $juror = $event->jurors->first();
        $juryCriteria = $event->groups->firstWhere('role_type', 'Jury')->criteria;
        $actorId = (string) $admin->id;
        $control = app(LiveControlService::class);

        $control->operate($event->id, 'start', $actorId);
        $control->operate($event->id, 'presentation', $actorId, $presentation->participant_id);
        $control->operate($event->id, 'open', $actorId, presentationId: $presentation->id);
        app(VoteService::class)->submit(
            $this->sessionPayload($event->id, $juror->id, 'Juror'),
            $presentation->id,
            'round-1-'.Str::uuid(),
            $this->answers($juryCriteria, 5),
        );
        $control->operate($event->id, 'close', $actorId, presentationId: $presentation->id);
        app(ResultService::class)->calculate($event->id, $actorId);
        $event->update(['require_quorum_to_publish' => false]);
        app(ResultService::class)->publish($event->id, $actorId);

        $roundOneVotes = Vote::query()->where('event_id', $event->id)->where('round_number', 1)->count();
        $roundOneResults = VotingResult::query()->where('event_id', $event->id)->where('round_number', 1)->count();
        $this->assertGreaterThan(0, $roundOneResults);
        $this->assertTrue(VotingResult::query()->where('event_id', $event->id)->where('round_number', 1)->whereNotNull('published_at')->exists());

        $this->actingAs($admin)->post(route('admin.control', [$event, 'restart']))
            ->assertRedirect()
            ->assertSessionHas('success');

        $event->refresh();
        $this->assertSame(2, $event->current_round);
        $this->assertSame('Draft', $event->status);
        $this->assertNull($event->active_presentation_id);
        $this->assertSame($roundOneVotes, Vote::query()->where('event_id', $event->id)->where('round_number', 1)->count());
        $this->assertSame($roundOneResults, VotingResult::query()->where('event_id', $event->id)->where('round_number', 1)->count());
        $this->assertTrue(VotingResult::query()->where('event_id', $event->id)->where('round_number', 1)->whereNotNull('published_at')->exists());
        $this->assertSame(
            Participant::query()->where('event_id', $event->id)->count(),
            Presentation::query()->where('event_id', $event->id)->where('round_number', 2)->count(),
        );
        $this->assertFalse(Vote::query()->where('event_id', $event->id)->where('round_number', 2)->exists());

        $this->actingAs($admin)->get(route('admin.event.results', ['event' => $event, 'round' => 1]))
            ->assertOk()
            ->assertSee('Ronda 1')
            ->assertSee('Historial conservado');

        $roundTwoPresentation = Presentation::query()->where('event_id', $event->id)->where('round_number', 2)->firstOrFail();
        $control->operate($event->id, 'lobby', $actorId);
        $control->operate($event->id, 'start', $actorId);
        $control->operate($event->id, 'presentation', $actorId, $roundTwoPresentation->participant_id);
        $control->operate($event->id, 'open', $actorId, presentationId: $roundTwoPresentation->id);
        app(VoteService::class)->submit(
            $this->sessionPayload($event->id, $juror->id, 'Juror'),
            $roundTwoPresentation->id,
            'round-2-'.Str::uuid(),
            $this->answers($juryCriteria, 4),
        );
        $this->assertDatabaseHas('votes', ['event_id' => $event->id, 'round_number' => 2, 'presentation_id' => $roundTwoPresentation->id]);
    }

    public function test_rubric_csv_can_omit_optional_columns_and_distributes_weight(): void
    {
        $admin = User::query()->where('email', 'admin@innovamente.local')->firstOrFail();
        $event = VotingEvent::query()->with('groups')->where('code', 'BTP726')->firstOrFail();
        $public = $event->groups->firstWhere('role_type', 'Public');
        $csv = "nombre;descripcion\nInnovación;Qué tan novedosa es\nPresentación;Claridad del equipo";

        $this->actingAs($admin)->post(route('admin.voting.rubric.import', $event), [
            'voting_group_id' => $public->id,
            'csv' => UploadedFile::fake()->createWithContent('rubrica.csv', $csv),
        ])->assertRedirect()->assertSessionHas('success');

        $criteria = Criterion::query()->where('voting_group_id', $public->id)->orderBy('sort_order')->get();
        $this->assertCount(2, $criteria);
        foreach ($criteria as $criterion) {
            $this->assertEquals(.5, $criterion->weight);
            $this->assertEquals(1, $criterion->scale_min);
            $this->assertEquals(5, $criterion->scale_max);
            $this->assertSame('Hidden', $criterion->comment_mode);
            $this->assertTrue($criterion->required);
            $this->assertNull($criterion->help_text);
        }
    }

    public function test_event_datetime_preserves_local_time_without_drift_on_repeated_updates(): void
    {
        $admin = User::query()->where('email', 'admin@innovamente.local')->firstOrFail();
        $event = VotingEvent::query()->where('code', 'BTP726')->firstOrFail();

        $initialStartsAt = '2026-09-15T18:30';
        $initialEndsAt = '2026-09-15T22:00';

        // 1st update
        $this->actingAs($admin)->post(route('admin.events.update', $event), [
            'name' => 'Batalla de Prompts Actualizado',
            'code' => $event->code,
            'time_zone' => 'America/Santo_Domingo',
            'starts_at_local' => $initialStartsAt,
            'ends_at_local' => $initialEndsAt,
            'public_access_mode' => $event->public_access_mode,
            'results_visibility' => $event->results_visibility,
            'presentation_duration_seconds' => $event->presentation_duration_seconds,
            'voting_duration_seconds' => $event->voting_duration_seconds,
        ])->assertRedirect()->assertSessionHas('success');

        $event->refresh();
        $this->assertSame('2026-09-15 18:30', $event->starts_at?->format('Y-m-d H:i'));
        $this->assertSame('2026-09-15 22:00', $event->ends_at?->format('Y-m-d H:i'));

        // 2nd update (e.g. updating name only, resending same datetime strings)
        $this->actingAs($admin)->post(route('admin.events.update', $event), [
            'name' => 'Batalla de Prompts Segunda Edición',
            'code' => $event->code,
            'time_zone' => 'America/Santo_Domingo',
            'starts_at_local' => $event->starts_at_local,
            'ends_at_local' => $event->ends_at_local,
            'public_access_mode' => $event->public_access_mode,
            'results_visibility' => $event->results_visibility,
            'presentation_duration_seconds' => $event->presentation_duration_seconds,
            'voting_duration_seconds' => $event->voting_duration_seconds,
        ])->assertRedirect()->assertSessionHas('success');

        $event->refresh();
        $this->assertSame('2026-09-15 18:30', $event->starts_at?->format('Y-m-d H:i'));
        $this->assertSame('2026-09-15 22:00', $event->ends_at?->format('Y-m-d H:i'));

        // Check the edit view renders the exact datetime in input value
        $this->actingAs($admin)->get(route('admin.events.edit', $event))
            ->assertOk()
            ->assertSee('value="2026-09-15T18:30"', false)
            ->assertSee('value="2026-09-15T22:00"', false);
    }

    public function test_responsive_views_and_dynamic_timezones_render_correctly(): void
    {
        $admin = User::query()->where('email', 'admin@innovamente.local')->firstOrFail();
        $event = VotingEvent::query()->where('code', 'BTP726')->firstOrFail();

        // 1. Voters screen
        $this->actingAs($admin)->get(route('admin.voters', $event))
            ->assertOk()
            ->assertSee('split-admin voters-layout')
            ->assertSee('voters-side-stack')
            ->assertSee('voter-action-panel')
            ->assertSee('Generar códigos')
            ->assertSee('Importar asistentes');

        // 2. Control room
        $this->actingAs($admin)->get(route('admin.live', $event))
            ->assertOk()
            ->assertSee('control-stage')
            ->assertSee('data-live-clock', false);

        // 3. Projection screen
        $this->get(route('projection.live', $event->code))
            ->assertOk()
            ->assertSee('projection-stage')
            ->assertSee('projection-center')
            ->assertSee('projection-join')
            ->assertSee('badge badge-live')
            ->assertSee('BTP726');
    }

    public function test_admin_can_invite_new_user_and_recipient_activates_account_and_sets_password(): void
    {
        Mail::fake();
        $admin = User::query()->where('email', 'admin@innovamente.local')->firstOrFail();

        $response = $this->actingAs($admin)->post(route('admin.users.create'), [
            'name' => 'Carlos Operador',
            'email' => 'carlos.operador@innovamente.org',
            'role' => 'Operator',
        ]);
        $response->assertRedirect()->assertSessionHas('success');

        $newUser = User::query()->where('email', 'carlos.operador@innovamente.org')->firstOrFail();
        $this->assertSame('Pending', $newUser->status);
        $this->assertSame('Operator', $newUser->role);
        $this->assertNotEmpty($newUser->invitation_token);
        $this->assertTrue($newUser->invitation_expires_at->isFuture());
        Mail::assertSent(\App\Mail\UserInvitationMail::class);

        $activationToken = $newUser->invitation_token;
        $this->get(route('auth.invitation.accept', $activationToken))
            ->assertOk()
            ->assertSee('Activar tu cuenta')
            ->assertSee('Carlos Operador');

        $setupResponse = $this->post(route('auth.invitation.setup', $activationToken), [
            'password' => 'CarlosPass2026!',
            'password_confirmation' => 'CarlosPass2026!',
        ]);
        $setupResponse->assertRedirect(route('admin.index'))->assertSessionHas('success');

        $newUser->refresh();
        $this->assertSame('Active', $newUser->status);
        $this->assertNull($newUser->invitation_token);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('CarlosPass2026!', $newUser->password));
        $this->assertAuthenticatedAs($newUser);
    }

    public function test_admin_can_add_global_juror_and_view_all_jurors(): void
    {
        Mail::fake();
        $admin = User::query()->where('email', 'admin@innovamente.local')->firstOrFail();
        $event = VotingEvent::query()->where('code', 'BTP726')->firstOrFail();

        $response = $this->actingAs($admin)->post(route('admin.jurors.global.add'), [
            'event_id' => $event->id,
            'name' => 'Dra. Isabel Santana',
            'title' => 'Especialista en IA',
            'email' => 'isabel.santana@example.org',
            'individual_weight' => 1.2,
        ]);
        $response->assertRedirect()->assertSessionHas('success')->assertSessionHas('issuedJuror');

        $this->assertDatabaseHas('jurors', [
            'event_id' => $event->id,
            'name' => 'Dra. Isabel Santana',
            'title' => 'Especialista en IA',
            'email' => 'isabel.santana@example.org',
        ]);

        $this->actingAs($admin)->get(route('admin.jurors.all'))
            ->assertOk()
            ->assertSee('Dra. Isabel Santana')
            ->assertSee('Ver historial');
    }

    public function test_flexible_round_restart_can_reset_same_round_or_advance_next_round(): void
    {
        $admin = User::query()->where('email', 'admin@innovamente.local')->firstOrFail();
        $event = VotingEvent::query()->with(['presentations', 'jurors', 'groups.criteria'])->where('code', 'BTP726')->firstOrFail();
        $presentation = $event->presentations->first();
        $juror = $event->jurors->first();
        $juryCriteria = $event->groups->firstWhere('role_type', 'Jury')->criteria;
        $actorId = (string) $admin->id;
        $control = app(LiveControlService::class);

        $control->operate($event->id, 'start', $actorId);
        $control->operate($event->id, 'presentation', $actorId, $presentation->participant_id);
        $control->operate($event->id, 'open', $actorId, presentationId: $presentation->id);
        app(VoteService::class)->submit(
            $this->sessionPayload($event->id, $juror->id, 'Juror'),
            $presentation->id,
            'same-round-test-'.Str::uuid(),
            $this->answers($juryCriteria, 5),
        );

        $this->assertTrue(Vote::query()->where('event_id', $event->id)->where('round_number', 1)->exists());

        $this->actingAs($admin)->post(route('admin.control', [$event, 'restart']), [
            'round_mode' => 'same_round',
        ])->assertRedirect()->assertSessionHas('success');

        $event->refresh();
        $this->assertSame(1, $event->current_round);
        $this->assertSame('Draft', $event->status);
        $this->assertNull($event->active_presentation_id);
        $this->assertFalse(Vote::query()->where('event_id', $event->id)->where('round_number', 1)->exists());
    }

    public function test_live_control_can_reset_single_team_turn_and_add_presentation_time(): void
    {
        $admin = User::query()->where('email', 'admin@innovamente.local')->firstOrFail();
        $event = VotingEvent::query()->with(['presentations.participant'])->where('code', 'BTP726')->firstOrFail();
        $presentation = $event->presentations->first();
        $actorId = (string) $admin->id;
        $control = app(LiveControlService::class);

        $control->operate($event->id, 'start', $actorId);
        $control->operate($event->id, 'presentation', $actorId, $presentation->participant_id);

        $presentation->refresh();
        $this->assertSame('OnStage', $presentation->status);

        $this->actingAs($admin)->post(route('admin.control', [$event, 'add_time']), [
            'presentation_id' => $presentation->id,
            'seconds' => 60,
        ])->assertRedirect()->assertSessionHas('success');

        $presentation->refresh();
        $this->assertSame(60, $presentation->extra_seconds);
        $this->assertSame(0, $presentation->paused_timer_seconds);

        $this->actingAs($admin)->post(route('admin.control', [$event, 'reset_turn']), [
            'presentation_id' => $presentation->id,
        ])->assertRedirect()->assertSessionHas('success');

        $presentation->refresh();
        $this->assertSame('Pending', $presentation->status);
        $this->assertSame(0, $presentation->extra_seconds);
        $this->assertNull($event->fresh()->active_presentation_id);
    }

    public function test_live_control_can_open_voting_when_paused_and_timer_recalculates(): void
    {
        $admin = User::query()->where('email', 'admin@innovamente.local')->firstOrFail();
        $event = VotingEvent::query()->with(['presentations.participant'])->where('code', 'BTP726')->firstOrFail();
        $presentation = $event->presentations->first();
        $actorId = (string) $admin->id;
        $control = app(LiveControlService::class);
        $queries = app(\App\Services\EventQueryService::class);

        $control->operate($event->id, 'start', $actorId);
        $control->operate($event->id, 'presentation', $actorId, $presentation->participant_id);
        $control->operate($event->id, 'pause', $actorId);

        $event->refresh();
        $this->assertSame('Paused', $event->status);

        // Can open voting even when paused
        $control->operate($event->id, 'open', $actorId, presentationId: $presentation->id);
        $event->refresh();
        $presentation->refresh();
        $this->assertSame('Live', $event->status);
        $this->assertSame('VotingOpen', $presentation->status);

        // Timer is active and has correct voting duration
        $state = $queries->liveStateByCode($event->code);
        $this->assertFalse($state['timerIsPaused']);
        $this->assertSame($event->voting_duration_seconds, $state['timerRemainingSeconds']);
    }

    public function test_user_can_update_own_profile_and_change_password(): void
    {
        $admin = User::query()->where('email', 'admin@innovamente.local')->firstOrFail();

        $this->actingAs($admin)->get(route('admin.profile'))
            ->assertOk()
            ->assertSee('Mi Perfil')
            ->assertSee($admin->email);

        $this->actingAs($admin)->post(route('admin.profile.update'), [
            'name' => 'Admin Modificado',
            'email' => 'admin.nuevo@innovamente.local',
        ])->assertRedirect()->assertSessionHas('success');

        $admin->refresh();
        $this->assertSame('Admin Modificado', $admin->name);
        $this->assertSame('admin.nuevo@innovamente.local', $admin->email);

        $this->actingAs($admin)->post(route('admin.profile.password'), [
            'current_password' => 'Admin-InnovaMente!2026',
            'password' => 'NuevoPasswordSeguro2026!',
            'password_confirmation' => 'NuevoPasswordSeguro2026!',
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('NuevoPasswordSeguro2026!', $admin->fresh()->password));
    }

    public function test_returning_device_voter_is_automatically_identified_without_reasking_name(): void
    {
        $event = VotingEvent::query()->where('code', 'BTP726')->firstOrFail();
        $deviceId = (string) Str::uuid();

        // 1. First time access: voter registers with device and name
        $response = $this->withCookie('innovamente_device', $deviceId)
            ->post(route('event.access'), [
                'event_code' => $event->code,
                'display_name' => 'María Rodríguez',
            ]);

        $response->assertRedirect(route('public.lobby'));
        $response->assertCookie('innovamente_public');

        // 2. Return access via /e/{code}: with session cleared but device cookie preserved
        $returnDirect = $this->withCookie('innovamente_device', $deviceId)
            ->get(route('event.code', ['code' => $event->code]));

        $returnDirect->assertRedirect(route('public.lobby'));
        $returnDirect->assertCookie('innovamente_public');

        // 3. Return access via form submission without name
        $returnForm = $this->withCookie('innovamente_device', $deviceId)
            ->post(route('event.access'), [
                'event_code' => $event->code,
                'display_name' => '',
            ]);

        $returnForm->assertRedirect(route('public.lobby'));
        $returnForm->assertCookie('innovamente_public');
    }

    public function test_admin_templates_workflow_and_event_creation_from_template(): void
    {
        $admin = User::query()->where('email', 'admin@innovamente.local')->firstOrFail();

        // 1. Settings view renders templates
        $this->actingAs($admin)->get(route('admin.settings'))
            ->assertOk()
            ->assertSee('Plantillas de Eventos')
            ->assertSee('Nueva Plantilla');

        // 2. Admin creates a new template with criteria
        $this->actingAs($admin)->post(route('admin.templates.create'), [
            'name' => 'Demo Day Tecnológico',
            'description' => 'Plantilla para evaluación de startups de base tecnológica',
            'category' => 'Startup',
            'presentation_duration_minutes' => 4,
            'voting_duration_minutes' => 3,
            'jury_weight_percent' => 80,
            'public_weight_percent' => 20,
            'public_access_mode' => 'Device',
            'criteria_text' => "Innovación Tecnológica: 30\nTracción y Negocio: 35\nEquipo y Pitch: 35",
        ])->assertRedirect()->assertSessionHas('success');

        $template = \App\Models\EventTemplate::query()->where('name', 'Demo Day Tecnológico')->firstOrFail();
        $this->assertSame(4 * 60, $template->configValue('presentation_duration_seconds'));
        $this->assertSame(80.0, $template->juryWeight());
        $this->assertCount(3, $template->criteria());

        // 3. Admin creates an event using this template
        $uniqueCode = 'TPL' . rand(100, 999);
        $this->actingAs($admin)->post(route('admin.events.store'), [
            'template_id' => $template->id,
            'name' => 'Pitch Competition 2026',
            'code' => $uniqueCode,
            'starts_at_local' => '2026-10-01T10:00',
            'ends_at_local' => '2026-10-01T14:00',
            'time_zone' => 'America/Santo_Domingo',
            'public_access_mode' => 'Device',
            'results_visibility' => 'ParticipationOnly',
            'presentation_duration_seconds' => 240,
            'voting_duration_seconds' => 180,
            'jury_weight_percent' => 80,
            'public_weight_percent' => 20,
            'allow_juror_vote_edit' => 1,
        ])->assertRedirect();

        $newEvent = VotingEvent::query()->where('code', $uniqueCode)->firstOrFail();
        $juryGroup = $newEvent->groups()->where('role_type', 'Jury')->with('criteria')->firstOrFail();
        $this->assertCount(3, $juryGroup->criteria);
        $this->assertTrue($juryGroup->criteria->pluck('name')->contains('Innovación Tecnológica'));

        // 4. Admin saves an existing event as a template
        $this->actingAs($admin)->post(route('admin.events.save-template', $newEvent), [
            'template_name' => 'Copia de Pitch Competition',
            'template_description' => 'Plantilla derivada de Pitch Competition',
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertTrue(\App\Models\EventTemplate::query()->where('name', 'Copia de Pitch Competition')->exists());
    }

    public function test_jury_dashboard_and_ballot_render_timer_when_presentation_is_active(): void
    {
        $event = VotingEvent::query()->with(['presentations', 'jurors'])->where('code', 'BTP726')->firstOrFail();
        $juror = $event->jurors->firstOrFail();
        $presentation = $event->presentations->firstOrFail();

        // 1. Iniciar presentación (OnStage)
        $control = app(LiveControlService::class);
        $operatorId = (string) Str::uuid();
        $control->operate($event->id, 'start', $operatorId);
        $control->operate($event->id, 'presentation', $operatorId, $presentation->participant_id);

        $sessionService = app(\App\Services\EventSessionService::class);
        [$token] = $sessionService->createJuror($event, $juror, 'Mozilla/5.0');

        // Juror dashboard should render timer-card and data-countdown or paused timer
        $this->withCookie('innovamente_juror', $token)
            ->get(route('jury.dashboard'))
            ->assertOk()
            ->assertSee('timer-card')
            ->assertSee('Tiempo de exposición / pitch')
            ->assertSee('data-countdown', false);

        // 2. Abrir votación (VotingOpen)
        $control->operate($event->id, 'open', $operatorId, presentationId: $presentation->id);

        $this->withCookie('innovamente_juror', $token)
            ->get(route('jury.dashboard'))
            ->assertOk()
            ->assertSee('timer-card')
            ->assertSee('Tiempo restante de votación')
            ->assertSee('data-countdown', false);

        // Juror ballot should also display timer
        $this->withCookie('innovamente_juror', $token)
            ->get(route('jury.ballot'))
            ->assertOk()
            ->assertSee('data-countdown', false)
            ->assertSee('Cierra en');
    }

    private function sessionPayload(string $eventId, string $actorId, string $actorType): array
    {
        return [
            'sessionId' => (string) Str::uuid(),
            'eventId' => $eventId,
            'actorId' => $actorId,
            'actorType' => $actorType,
        ];
    }

    private function answers($criteria, int $value): array
    {
        return $criteria->map(fn ($criterion) => [
            'criterion_id' => $criterion->id,
            'value' => $value,
            'comment' => '',
        ])->all();
    }
}

