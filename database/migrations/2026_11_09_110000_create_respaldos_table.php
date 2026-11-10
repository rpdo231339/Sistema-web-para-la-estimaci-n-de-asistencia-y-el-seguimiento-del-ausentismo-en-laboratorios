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
        // D.18 respaldos: historial de generacion y restauracion de copias
        Schema::create('respaldos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('administrador_id')->constrained('usuarios')->restrictOnDelete()->restrictOnUpdate();
            $table->string('operacion', 20);
            $table->string('estado', 20)->default('pendiente');
            $table->foreignId('respaldo_origen_id')->nullable()->constrained('respaldos')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('respaldo_preventivo_id')->nullable()->constrained('respaldos')->restrictOnDelete()->restrictOnUpdate();
            $table->string('ruta', 512)->nullable();
            $table->unsignedBigInteger('bytes')->nullable();
            $table->char('sha256', 64)->nullable();
            $table->json('manifiesto')->nullable();
            $table->text('detalle')->nullable();
            $table->dateTime('iniciado_en');
            $table->dateTime('terminado_en')->nullable();
            $table->dateTime('created_at');
            $table->dateTime('updated_at');
        });

        DB::statement("ALTER TABLE respaldos ADD CONSTRAINT chk_respaldos_operacion CHECK (operacion IN ('respaldo','restauracion'))");
        DB::statement("ALTER TABLE respaldos ADD CONSTRAINT chk_respaldos_estado CHECK (estado IN ('pendiente','en_proceso','completado','error'))");
        DB::statement("ALTER TABLE respaldos ADD CONSTRAINT chk_respaldos_bytes CHECK (bytes IS NULL OR bytes > 0)");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('respaldos');
    }
};
