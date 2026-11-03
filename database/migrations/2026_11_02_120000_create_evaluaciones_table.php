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
        // D.15 evaluaciones: referencia humana y condiciones de una prueba de conteo con una imagen estatica
        Schema::create('evaluaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('procesamiento_id')->unique()->constrained('procesamientos')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('registrado_por')->constrained('usuarios')->restrictOnDelete()->restrictOnUpdate();
            $table->string('ambiente', 20);
            $table->string('iluminacion', 150);
            $table->string('angulo', 150);
            $table->string('oclusion', 20);
            $table->unsignedInteger('referencia_manual');
            $table->unsignedInteger('omisiones')->nullable();
            $table->unsignedInteger('duplicaciones')->nullable();
            $table->text('observaciones')->nullable();
            $table->dateTime('created_at');
            $table->dateTime('updated_at');
        });

        DB::statement("ALTER TABLE evaluaciones ADD CONSTRAINT chk_evaluaciones_ambiente CHECK (ambiente IN ('aula','laboratorio'))");
        DB::statement("ALTER TABLE evaluaciones ADD CONSTRAINT chk_evaluaciones_oclusion CHECK (oclusion IN ('nula','parcial','alta'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('evaluaciones');
    }
};
