<?php

namespace Tests\Feature;

use App\Models\Participant;
use App\Models\User;
use App\Models\Vote;
use App\Models\Voter;
use App\Models\VotingEvent;
use App\Services\LiveControlService;
use App\Services\ResultService;
use App\Services\VoteService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            ->assertSee('BTP726');

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
            'members' => 'Ada, Linus',
            'area' => 'Tecnología',
            'description' => 'Descripción actualizada para pruebas.',
        ])->assertRedirect(route('admin.participants', $event));
        $this->assertDatabaseHas('participants', ['id' => $participant->id, 'name' => 'Equipo QA Renovado', 'project_title' => 'Proyecto QA']);

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
        $event->update(['require_quorum_to_publish' => false]);
        app(ResultService::class)->calculate($event->id, $actorId);
        app(ResultService::class)->publish($event->id, $actorId);

        $this->get(route('projection.live', $event->code))
            ->assertOk()
            ->assertSee('Entrar a votar')
            ->assertSee('Acceso de jurados')
            ->assertSee('?event=BTP726', false);

        $this->get(route('projection.ranking', $event->code))
            ->assertOk()
            ->assertSee('Podio de ganadores')
            ->assertSee('Rendimiento por equipo')
            ->assertSee('ranking-bar-row', false)
            ->assertSee('Desglose completo');

        $admin = User::query()->where('email', 'admin@innovamente.local')->firstOrFail();
        $this->actingAs($admin)->get(route('admin.live', $event))
            ->assertOk()
            ->assertSee('Ver resultados')
            ->assertSee(route('projection.ranking', $event->code), false);
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
