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
        // D.14 asistencias (una fila por sesion)
        Schema::create('asistencias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sesion_id')->constrained('sesiones_clase')->restrictOnDelete()->restrictOnUpdate();
            $table->unsignedInteger('asistentes')->nullable();
            $table->unsignedInteger('justificados')->nullable();
            $table->unsignedInteger('faltas')->nullable();
            $table->string('estado', 20)->default('borrador');
            $table->text('observaciones')->nullable();
            $table->foreignId('confirmado_por')->nullable()->constrained('usuarios')->restrictOnDelete()->restrictOnUpdate();
            $table->dateTime('confirmado_en')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->dateTime('created_at');
            $table->dateTime('updated_at');

            $table->unique('sesion_id', 'uq_asistencias_sesion');
        });

        DB::statement("ALTER TABLE asistencias ADD CONSTRAINT chk_asistencias_estado CHECK (estado IN ('borrador','pendiente_revision','confirmado'))");
        DB::statement("ALTER TABLE asistencias ADD CONSTRAINT chk_asistencias_version CHECK (version >= 1)");
        DB::statement("ALTER TABLE asistencias ADD CONSTRAINT chk_asistencias_confirmado CHECK (estado <> 'confirmado' OR (asistentes IS NOT NULL AND justificados IS NOT NULL AND faltas IS NOT NULL AND confirmado_por IS NOT NULL AND confirmado_en IS NOT NULL))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asistencias');
    }
};
