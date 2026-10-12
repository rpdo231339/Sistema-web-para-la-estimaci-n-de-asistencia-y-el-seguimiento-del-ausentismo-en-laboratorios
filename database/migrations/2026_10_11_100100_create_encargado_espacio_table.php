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
        // D.4 encargado_espacio
        Schema::create('encargado_espacio', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->constrained('usuarios')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('espacio_id')->constrained('espacios')->restrictOnDelete()->restrictOnUpdate();
            $table->boolean('activo')->default(true);
            $table->dateTime('created_at');
            $table->dateTime('updated_at');

            $table->unique(['usuario_id', 'espacio_id'], 'uq_encargado_espacio');
        });

        DB::statement("ALTER TABLE encargado_espacio ADD CONSTRAINT chk_encargado_activo CHECK (activo IN (0,1))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('encargado_espacio');
    }
};
