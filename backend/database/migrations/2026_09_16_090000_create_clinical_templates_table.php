<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Plantillas de atención guardadas por los profesionales.
 *
 * Son prototipos persistidos: al arrancar, el registro las carga junto a las
 * institucionales y a partir de ahí se comportan igual —se entregan copiadas,
 * nunca el original—.
 *
 * Obsérvese lo que la tabla NO guarda: ni paciente, ni fecha de atención, ni
 * anamnesis, ni hallazgos. Una plantilla que arrastrara eso convertiría el
 * «guardar como plantilla» en una fuga de datos del paciente que la originó.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinical_templates', function (Blueprint $table) {
            $table->id();
            $table->string('key', 60)->unique();
            $table->string('name', 160);
            $table->string('type', 30)->index();

            $table->string('chief_complaint', 500);
            $table->text('treatment_plan');
            $table->unsignedSmallInteger('follow_up_days')->nullable();

            $table->json('diagnoses');
            $table->json('prescriptions')->nullable();
            $table->json('metadata')->nullable();

            // Quién la guardó y de qué nota salió: trazabilidad de la plantilla.
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('origin_encounter_id')->nullable()
                ->constrained('clinical_encounters')->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinical_templates');
    }
};
