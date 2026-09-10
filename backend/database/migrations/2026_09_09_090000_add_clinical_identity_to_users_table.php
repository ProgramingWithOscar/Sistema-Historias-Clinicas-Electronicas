<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Datos mínimos de identificación del paciente exigidos por la Resolución 866
 * de 2021 para el conjunto de datos clínicos relevantes: tipo y número de
 * documento y fecha de nacimiento.
 *
 * Son también los datos que cada familia de exportación trata de forma distinta:
 * FHIR los publica como `identifier`, el RDA como `tipoDocumento`/`numeroDocumento`
 * y la familia anonimizada no los publica en absoluto.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('document_type', 5)->nullable()->after('email');
            $table->string('document_number', 30)->nullable()->after('document_type');
            $table->date('birth_date')->nullable()->after('document_number');

            $table->index(['document_type', 'document_number']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['document_type', 'document_number']);
            $table->dropColumn(['document_type', 'document_number', 'birth_date']);
        });
    }
};
