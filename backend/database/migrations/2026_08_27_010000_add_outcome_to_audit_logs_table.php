<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->string('outcome', 20)->default('success')->after('action')->index();
            $table->unsignedSmallInteger('status_code')->nullable()->after('outcome');
        });

        // Los registros anteriores no tenían la columna: se deduce del `action`
        // para no dejar huecos en una tabla de auditoría.
        DB::table('audit_logs')->where('action', 'like', '%.failed')->update(['outcome' => 'failure']);
        DB::table('audit_logs')->where('action', 'like', '%.throttled')->update(['outcome' => 'denied']);
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropColumn(['outcome', 'status_code']);
        });
    }
};
