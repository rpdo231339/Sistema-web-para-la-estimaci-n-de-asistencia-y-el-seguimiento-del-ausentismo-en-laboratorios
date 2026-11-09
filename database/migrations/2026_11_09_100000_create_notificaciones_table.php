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
        // D.16 notificaciones: bandeja interna y control de entrega por correo
        Schema::create('notificaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->constrained('usuarios')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('pase_lista_id')->nullable()->constrained('pases_lista')->restrictOnDelete()->restrictOnUpdate();
            $table->string('tipo', 20);
            $table->string('clave_evento', 160);
            $table->string('titulo', 150);
            $table->text('mensaje');
            $table->json('datos');
            $table->dateTime('programada_en');
            $table->boolean('vigente')->default(true);
            $table->dateTime('leida_en')->nullable();
            $table->string('estado_correo', 20)->default('pendiente');
            $table->unsignedInteger('intentos_correo')->default(0);
            $table->dateTime('correo_enviado_en')->nullable();
            $table->text('ultimo_error')->nullable();
            $table->dateTime('created_at');
            $table->dateTime('updated_at');

            // Evita duplicados: un mismo evento genera una sola notificacion por usuario, aunque se reintente
            $table->unique(['usuario_id', 'clave_evento'], 'uq_notificaciones_usuario_evento');
            $table->index(['usuario_id', 'leida_en'], 'idx_notificaciones_bandeja');
            $table->index(['estado_correo', 'programada_en'], 'idx_notificaciones_correo');
        });

        DB::statement("ALTER TABLE notificaciones ADD CONSTRAINT chk_notificaciones_tipo CHECK (tipo IN ('recordatorio','cambio','resultado','incidencia'))");
        DB::statement("ALTER TABLE notificaciones ADD CONSTRAINT chk_notificaciones_correo CHECK (estado_correo IN ('pendiente','enviado','error','incierto','caducado'))");
        DB::statement("ALTER TABLE notificaciones ADD CONSTRAINT chk_notificaciones_vigente CHECK (vigente IN (0,1))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notificaciones');
    }
};
