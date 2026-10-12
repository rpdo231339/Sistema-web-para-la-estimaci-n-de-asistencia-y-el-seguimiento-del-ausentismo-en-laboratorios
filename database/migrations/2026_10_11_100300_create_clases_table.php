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
        // D.6 clases (el periodo se obtiene del grupo)
        Schema::create('clases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profesor_id')->constrained('usuarios')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('grupo_id')->constrained('grupos')->restrictOnDelete()->restrictOnUpdate();
            $table->string('asignatura', 150);
            $table->unsignedInteger('inscritos');
            $table->text('observaciones')->nullable();
            $table->boolean('vigente')->default(true);
            $table->dateTime('created_at');
            $table->dateTime('updated_at');
        });

        DB::statement("ALTER TABLE clases ADD CONSTRAINT chk_clases_inscritos CHECK (inscritos > 0)");
        DB::statement("ALTER TABLE clases ADD CONSTRAINT chk_clases_vigente CHECK (vigente IN (0,1))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clases');
    }
};
