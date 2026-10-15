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
        // D.8 sesiones_clase
        Schema::create('sesiones_clase', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clase_id')->constrained('clases')->restrictOnDelete()->restrictOnUpdate();
            $table->unsignedBigInteger('horario_clase_id');
            $table->date('fecha');
            $table->foreignId('profesor_id')->constrained('usuarios')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('grupo_id')->constrained('grupos')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('espacio_id')->constrained('espacios')->restrictOnDelete()->restrictOnUpdate();
            $table->unsignedInteger('inscritos');
            $table->time('hora_inicio');
            $table->time('hora_fin');
            $table->string('periodo', 20);
            $table->json('contexto_historico');
            $table->string('estado', 20)->default('programada');
            $table->text('observaciones')->nullable();
            $table->dateTime('created_at');
            $table->dateTime('updated_at');

            $table->unique(['horario_clase_id', 'fecha'], 'uq_sesiones_horario_fecha');
            $table->index('fecha', 'idx_sesiones_fecha');

            // El horario debe pertenecer a la clase de la sesion
            $table->foreign(['clase_id', 'horario_clase_id'], 'fk_sesiones_horario_clase')
                ->references(['clase_id', 'id'])->on('horarios_clase')
                ->restrictOnDelete()->restrictOnUpdate();
        });

        DB::statement("ALTER TABLE sesiones_clase ADD CONSTRAINT chk_sesiones_estado CHECK (estado IN ('programada','realizada','cancelada'))");
        DB::statement("ALTER TABLE sesiones_clase ADD CONSTRAINT chk_sesiones_inscritos CHECK (inscritos > 0)");
        DB::statement("ALTER TABLE sesiones_clase ADD CONSTRAINT chk_sesiones_horas CHECK (hora_fin > hora_inicio)");
        DB::statement("ALTER TABLE sesiones_clase ADD CONSTRAINT chk_sesiones_motivo CHECK (estado <> 'cancelada' OR observaciones IS NOT NULL)");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sesiones_clase');
    }
};
