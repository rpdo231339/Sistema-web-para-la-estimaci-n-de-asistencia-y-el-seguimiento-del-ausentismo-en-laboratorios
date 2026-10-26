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
        // D.12 procesamientos (ejecuciones de inferencia ligadas a una evidencia y una configuracion)
        Schema::create('procesamientos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evidencia_id')->constrained('evidencias')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('configuracion_id')->constrained('configuraciones_modelo')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('solicitado_por')->constrained('usuarios')->restrictOnDelete()->restrictOnUpdate();
            $table->string('clave_operacion', 64);
            $table->string('estado', 20)->default('pendiente');
            $table->unsignedInteger('conteo')->nullable();
            $table->json('datos_resultado')->nullable();
            $table->dateTime('iniciado_en')->nullable();
            $table->dateTime('terminado_en')->nullable();
            $table->unsignedInteger('duracion_ms')->nullable();
            $table->text('error')->nullable();
            $table->unsignedInteger('intentos')->default(0);
            $table->dateTime('created_at');
            $table->dateTime('updated_at');

            $table->unique('clave_operacion', 'uq_procesamientos_clave');
        });

        DB::statement("ALTER TABLE procesamientos ADD CONSTRAINT chk_procesamientos_estado CHECK (estado IN ('pendiente','en_proceso','procesado','error'))");
        // El conteo es nulo hasta un resultado valido; cero es un conteo valido
        DB::statement("ALTER TABLE procesamientos ADD CONSTRAINT chk_procesamientos_conteo CHECK (estado <> 'procesado' OR conteo IS NOT NULL)");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('procesamientos');
    }
};
