<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Notas de atención construidas por el patrón Builder.
 *
 * Las secciones de texto libre y las listas (diagnósticos, prescripciones,
 * signos vitales) se guardan tal como las armó el builder: la tabla es un
 * reflejo del producto `ClinicalNote`, no una estructura paralela.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinical_encounters', function (Blueprint $table) {
            $table->id();
            $table->string('type', 30)->index();

            $table->foreignId('patient_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('professional_id')->constrained('users')->restrictOnDelete();
            $table->string('professional_license', 40);

            $table->timestamp('attended_at')->index();

            // Contenido mínimo de la historia clínica (Res. 1995 de 1999).
            $table->text('chief_complaint');
            $table->text('present_illness');
            $table->text('history')->nullable();
            $table->text('physical_exam')->nullable();
            $table->text('treatment_plan');

            $table->json('vital_signs')->nullable();
            $table->json('diagnoses');
            $table->json('prescriptions')->nullable();
            $table->json('metadata')->nullable();

            $table->string('triage', 5)->nullable();
            $table->timestamp('follow_up_at')->nullable();

            $table->timestamps();

            $table->index(['patient_id', 'attended_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinical_encounters');
    }
};
