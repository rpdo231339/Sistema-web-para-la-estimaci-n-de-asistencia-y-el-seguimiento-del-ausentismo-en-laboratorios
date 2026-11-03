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
        // Cada calculo es una fila inmutable; el mas reciente es el vigente
        Schema::create('estimaciones_presencia', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sesion_id')->constrained('sesiones_clase')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('calculado_por')->constrained('usuarios')->restrictOnDelete()->restrictOnUpdate();
            $table->unsignedInteger('inscritos');
            $table->unsignedInteger('conteo_inicio');
            $table->unsignedInteger('conteo_desarrollo');
            $table->unsignedInteger('conteo_cierre');
            $table->decimal('media', 10, 4);
            $table->decimal('ausentismo', 7, 4);
            $table->json('origen');
            $table->dateTime('created_at');

            $table->index(['sesion_id', 'id'], 'idx_estimaciones_sesion_id');
        });

        DB::statement('ALTER TABLE estimaciones_presencia ADD CONSTRAINT chk_estimaciones_inscritos CHECK (inscritos > 0)');
        DB::statement('ALTER TABLE estimaciones_presencia ADD CONSTRAINT chk_estimaciones_limites CHECK (conteo_inicio <= inscritos AND conteo_desarrollo <= inscritos AND conteo_cierre <= inscritos)');
        DB::statement('ALTER TABLE estimaciones_presencia ADD CONSTRAINT chk_estimaciones_ausentismo CHECK (ausentismo >= 0 AND ausentismo <= 100)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('estimaciones_presencia');
    }
};
