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
        // D.7 horarios_clase
        Schema::create('horarios_clase', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clase_id')->constrained('clases')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('espacio_id')->constrained('espacios')->restrictOnDelete()->restrictOnUpdate();
            $table->unsignedTinyInteger('dia');
            $table->time('hora_inicio');
            $table->time('hora_fin');
            $table->boolean('activo')->default(true);
            $table->dateTime('created_at');
            $table->dateTime('updated_at');

            // Permite la referencia compuesta desde sesiones_clase
            $table->unique(['clase_id', 'id'], 'uq_horarios_clase_id');
        });

        DB::statement("ALTER TABLE horarios_clase ADD CONSTRAINT chk_horarios_dia CHECK (dia BETWEEN 1 AND 7)");
        DB::statement("ALTER TABLE horarios_clase ADD CONSTRAINT chk_horarios_horas CHECK (hora_fin > hora_inicio)");
        DB::statement("ALTER TABLE horarios_clase ADD CONSTRAINT chk_horarios_activo CHECK (activo IN (0,1))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('horarios_clase');
    }
};
