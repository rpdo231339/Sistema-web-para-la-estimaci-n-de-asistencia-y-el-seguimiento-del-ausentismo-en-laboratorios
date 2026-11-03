<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // D.19 auditoria (bitacora inmutable; nunca guarda contrasenas, tokens ni credenciales)
        Schema::create('auditoria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->nullable()->constrained('usuarios')->restrictOnDelete()->restrictOnUpdate();
            $table->string('entidad', 64);
            $table->unsignedBigInteger('registro_id')->nullable();
            $table->string('accion', 40);
            $table->json('antes')->nullable();
            $table->json('despues')->nullable();
            $table->text('motivo')->nullable();
            $table->dateTime('created_at');

            $table->index(['entidad', 'registro_id'], 'idx_auditoria_entidad');
            $table->index('created_at', 'idx_auditoria_fecha');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('auditoria');
    }
};
