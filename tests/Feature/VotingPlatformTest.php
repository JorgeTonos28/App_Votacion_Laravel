<?php

namespace Tests\Feature;

use App\Models\Vote;
use App\Models\Voter;
use App\Models\VotingEvent;
use App\Services\LiveControlService;
use App\Services\ResultService;
use App\Services\VoteService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
