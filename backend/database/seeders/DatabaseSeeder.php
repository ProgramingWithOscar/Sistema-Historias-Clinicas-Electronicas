<?php

namespace Database\Seeders;

use App\Models\ClinicalEncounter;
use App\Models\DeviceReading;
use App\Models\User;
use App\Support\Encounters\EncounterDirectorResolver;
use App\Support\Iot\DeviceReadingFactoryResolver;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Datos de demostración.
 *
 * Es idempotente a propósito: puede ejecutarse varias veces sin duplicar nada,
 * porque quien evalúa el proyecto no tiene por qué saber si ya lo corrió.
 *
 * Las lecturas y la nota de atención NO se insertan a mano: se crean pasando
 * por las fábricas y los directores reales, de modo que los datos de muestra
 * son exactamente los que produciría la aplicación en uso.
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $medico = User::firstOrCreate(
            ['email' => 'medico@hce.test'],
            [
                'name' => 'Dra. Helena Ruiz',
                'password' => Hash::make('password'),
                'document_type' => 'CC',
                'document_number' => '1032456789',
                'birth_date' => '1984-02-19',
                'email_verified_at' => now(),
            ],
        );

        $paciente = User::firstOrCreate(
            ['email' => 'paciente@hce.test'],
            [
                'name' => 'Ana María Rodríguez Pérez',
                'password' => Hash::make('password'),
                'document_type' => 'CC',
                'document_number' => '52847391',
                'birth_date' => '1985-04-12',
                'email_verified_at' => now(),
            ],
        );

        $this->seedDeviceReadings($paciente);
        $this->seedEncounter($medico, $paciente);
    }

    /** Lecturas IoT creadas con las fábricas reales (patrón Factory Method). */
    private function seedDeviceReadings(User $paciente): void
    {
        if (DeviceReading::where('patient_id', $paciente->id)->exists()) {
            return;
        }

        $resolver = new DeviceReadingFactoryResolver;

        $muestras = [
            ['sphygmomanometer', ['systolic' => 148, 'diastolic' => 92, 'pulse' => 78], 3],
            ['sphygmomanometer', ['systolic' => 138, 'diastolic' => 86, 'pulse' => 72], 2],
            ['glucometer', ['mg_dl' => 132, 'fasting' => true], 2],
            ['pulse_oximeter', ['spo2' => 97, 'pulse' => 74], 1],
        ];

        foreach ($muestras as [$dispositivo, $payload, $diasAtras]) {
            $resolver->for($dispositivo)->ingest(
                payload: [...$payload, 'measured_at' => now()->subDays($diasAtras)],
                patientId: $paciente->id,
            );
        }
    }

    /** Nota de atención creada con su director (patrón Builder). */
    private function seedEncounter(User $medico, User $paciente): void
    {
        if (ClinicalEncounter::where('patient_id', $paciente->id)->exists()) {
            return;
        }

        $nota = (new EncounterDirectorResolver)->for('outpatient_control')->construct(
            payload: [
                'professional_license' => 'RM-12345',
                'chief_complaint' => 'Control de hipertensión arterial',
                'present_illness' => 'Paciente asintomática, adherente al tratamiento antihipertensivo.',
                'history' => 'Hipertensa desde 2019, sin otras comorbilidades conocidas.',
                'physical_exam' => 'Ruidos cardiacos rítmicos, sin soplos. Sin edemas.',
                'follow_up_at' => now()->addMonths(3)->toDateString(),
                'program' => 'Riesgo cardiovascular',
                'diagnoses' => [
                    ['code' => 'I10', 'description' => 'Hipertensión esencial (primaria)', 'primary' => true],
                ],
                'treatment_plan' => 'Continuar losartán, dieta hiposódica y actividad física '
                    .'150 minutos por semana. Toma domiciliaria de presión arterial.',
                'prescriptions' => [[
                    'active_ingredient' => 'Losartán',
                    'dose' => '50 mg',
                    'frequency' => 'cada 24 horas',
                    'duration_days' => 90,
                ]],
            ],
            patient: $paciente,
            professional: $medico,
        );

        ClinicalEncounter::fromNote($nota);
    }
}
