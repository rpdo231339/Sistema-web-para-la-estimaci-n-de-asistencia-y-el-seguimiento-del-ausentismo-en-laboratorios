<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // D.9 pases_lista (tres registros por sesion)
        Schema::create('pases_lista', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sesion_id')->constrained('sesiones_clase')->restrictOnDelete()->restrictOnUpdate();
            $table->string('momento', 15);
            $table->dateTime('programado_en');
            $table->unsignedInteger('version_programacion')->default(1);
            $table->string('estado', 20)->default('sin_evidencia');
            $table->text('observaciones')->nullable();
            $table->dateTime('created_at');
            $table->dateTime('updated_at');

            $table->unique(['sesion_id', 'momento'], 'uq_pases_sesion_momento');
            $table->index('programado_en', 'idx_pases_programado');
        });

        DB::statement("ALTER TABLE pases_lista ADD CONSTRAINT chk_pases_momento CHECK (momento IN ('inicio','desarrollo','cierre'))");
        DB::statement("ALTER TABLE pases_lista ADD CONSTRAINT chk_pases_estado CHECK (estado IN ('sin_evidencia','procesado','validado','con_incidencia'))");
        DB::statement("ALTER TABLE pases_lista ADD CONSTRAINT chk_pases_version CHECK (version_programacion >= 1)");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pases_lista');
    }
};
