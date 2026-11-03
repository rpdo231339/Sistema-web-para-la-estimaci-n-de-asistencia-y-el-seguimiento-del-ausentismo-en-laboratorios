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
        // D.13 revisiones_conteo (historial inmutable: solo se agregan filas)
        Schema::create('revisiones_conteo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('procesamiento_id')->constrained('procesamientos')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('profesor_id')->constrained('usuarios')->restrictOnDelete()->restrictOnUpdate();
            $table->string('decision', 20);
            $table->unsignedInteger('conteo_revisado')->nullable();
            $table->text('motivo')->nullable();
            $table->dateTime('created_at');

            $table->index(['procesamiento_id', 'id'], 'idx_revisiones_proc_id');
        });

        DB::statement("ALTER TABLE revisiones_conteo ADD CONSTRAINT chk_revisiones_decision CHECK (decision IN ('validado','observado'))");
        DB::statement("ALTER TABLE revisiones_conteo ADD CONSTRAINT chk_revisiones_conteo CHECK (decision <> 'validado' OR conteo_revisado IS NOT NULL)");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('revisiones_conteo');
    }
};
