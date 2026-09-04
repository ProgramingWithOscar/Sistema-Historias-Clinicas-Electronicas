<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_readings', function (Blueprint $table) {
            $table->id();
            $table->string('device_type')->index();
            $table->string('loinc_code')->index();
            $table->string('display');
            $table->decimal('value', 8, 2);
            $table->string('unit', 20);
            $table->string('severity', 20)->index();
            $table->json('components')->nullable();
            $table->foreignId('patient_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('measured_at')->index();
            $table->timestamps();

            $table->index(['patient_id', 'measured_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_readings');
    }
};
