<?php

namespace Database\Seeders;

use App\Models\Criterion;
use App\Models\EventBranding;
use App\Models\EventTemplate;
use App\Models\Juror;
use App\Models\Participant;
use App\Models\Presentation;
use App\Models\User;
use App\Models\VotingEvent;
use App\Models\VotingGroup;
use App\Support\Domain;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::query()->updateOrCreate([
            'email' => config('innovamente.admin_email'),
        ], [
            'name' => config('innovamente.admin_name'),
            'password' => Hash::make(config('innovamente.admin_password')),
            'email_verified_at' => now(),
            'role' => 'Administrator',
        ]);

        if (VotingEvent::query()->exists()) {
            return;
        }

        $event = VotingEvent::query()->create([
            'code' => 'BTP726',
            'name' => 'Batalla de Prompts',
            'subtitle' => 'Innovación, creatividad e inteligencia artificial',
            'description' => 'Competencia de equipos que diseñan, prueban y mejoran prompts para resolver retos reales.',
            'category' => 'Innovación',
            'organizer' => 'INFOTEP · InnovaMente',
            'venue' => 'Sala InnovaMente, Aula 305 del ECI',
            'time_zone' => 'America/Santo_Domingo',
            'starts_at' => '2026-07-30 17:30:00',
            'ends_at' => '2026-07-30 19:30:00',
            'status' => 'LobbyOpen',
            'public_access_mode' => 'Device',
            'results_visibility' => 'ParticipationOnly',
            'presentation_duration_seconds' => 300,
            'voting_duration_seconds' => 180,
            'allow_juror_vote_edit' => true,
            'require_quorum_to_publish' => false,
        ]);

        EventBranding::query()->create([
            'event_id' => $event->id,
            'logo_url' => '/images/logo-innovatep.png',
            'primary_color' => '#042E80',
            'secondary_color' => '#FEA203',
            'accent_color' => '#0C58C7',
            'background_color' => '#F4F7FB',
            'text_color' => '#17233D',
        ]);

        $juryGroup = VotingGroup::query()->create([
            'event_id' => $event->id, 'name' => 'Jurado', 'role_type' => 'Jury',
            'weight' => .70, 'minimum_votes' => 3, 'allow_edit_until_close' => true, 'sort_order' => 1,
        ]);
        $publicGroup = VotingGroup::query()->create([
            'event_id' => $event->id, 'name' => 'Público', 'role_type' => 'Public',
            'weight' => .30, 'minimum_votes' => 3, 'allow_edit_until_close' => false, 'sort_order' => 2,
        ]);

        $juryCriteria = [
            ['Claridad del prompt', 'La instrucción es comprensible, específica y no ambigua.', .20],
            ['Estructura del prompt', 'Incluye contexto, rol, tarea, formato y criterios relevantes.', .25],
            ['Iteración y mejora', 'El equipo identifica fallas y mejora claramente el prompt.', .20],
            ['Calidad del resultado', 'El producto final es útil, ordenado y responde al reto.', .20],
            ['Presentación del equipo', 'Explica el proceso, el antes y después y defiende el resultado.', .15],
        ];
        $publicCriteria = [
            ['Claridad del resultado', 'El producto se entiende con facilidad.', .34],
            ['Utilidad', 'El resultado parece aplicable o valioso.', .33],
            ['Explicación del proceso', 'El equipo explica bien cómo mejoró su prompt.', .33],
        ];
        foreach ([[$juryGroup, $juryCriteria], [$publicGroup, $publicCriteria]] as [$group, $criteria]) {
            foreach ($criteria as $index => [$name, $description, $weight]) {
                Criterion::query()->create([
                    'event_id' => $event->id, 'voting_group_id' => $group->id,
                    'sort_order' => $index + 1, 'name' => $name, 'description' => $description,
                    'weight' => $weight, 'minimum_label' => 'Deficiente', 'maximum_label' => 'Excelente',
                    'help_text' => 'Selecciona una puntuación de 1 a 5.',
                ]);
            }
        }

        $participants = [
            ['Equipo Alpha', 'Generador de Mundos', 'Plataforma de creación procedural de entornos virtuales mediante modelos de lenguaje avanzados.'],
            ['EcoSolutions', 'Sistema Inteligente de Reciclaje Urbano', 'Clasificación y trazabilidad de residuos con apoyo de IA.'],
            ['BioTrack AI', 'Monitoreo preventivo de salud', 'Asistente para identificar señales tempranas y orientar hábitos saludables.'],
            ['Quantum Mesh', 'Redes de baja latencia para IoT', 'Orquestación inteligente de dispositivos para ciudades conectadas.'],
            ['AgriAI', 'Cultivos más eficientes', 'Recomendaciones de riego y nutrición basadas en datos locales.'],
        ];
        foreach ($participants as $index => [$name, $title, $description]) {
            $participant = Participant::query()->create([
                'event_id' => $event->id, 'number' => $index + 1, 'name' => $name,
                'project_title' => $title, 'description' => $description,
                'presentation_order' => $index + 1, 'area' => 'Innovación tecnológica',
            ]);
            Presentation::query()->create([
                'event_id' => $event->id, 'participant_id' => $participant->id, 'sequence' => $index + 1,
            ]);
        }

        $jurors = [
            ['Elena Rodríguez', 'Especialista en Innovación'],
            ['Carlos Mendoza', 'Mentor de Emprendimiento'],
            ['Ing. Luis Varela', 'Arquitecto de Soluciones'],
            ['María Fernández', 'Diseñadora de Producto'],
            ['Rafael Castillo', 'Experto en Inteligencia Artificial'],
        ];
        foreach ($jurors as $index => [$name, $title]) {
            $code = $index === 0 ? config('innovamente.demo_juror_code') : Domain::jurorCode();
            Juror::query()->create([
                'event_id' => $event->id, 'name' => $name, 'title' => $title,
                'code_hash' => Domain::hashSecret($code), 'expires_at' => now()->addYear(),
            ]);
        }

        foreach ([
            ['Batalla de Prompts', 'Jurado 70% y público 30% con rúbricas diferenciadas.'],
            ['Evaluación de proyectos', 'Evaluación técnica estructurada por jurado.'],
            ['Concurso con jurado y público', 'Pesos configurables para dos grupos.'],
            ['Premiación por voto popular', 'Selección rápida del público.'],
            ['Presentaciones con rúbrica', 'Criterios y escalas reutilizables.'],
        ] as [$name, $description]) {
            EventTemplate::query()->create([
                'name' => $name,
                'description' => $description,
                'configuration_json' => '{}',
                'branding_json' => '{}',
            ]);
        }
    }
}
