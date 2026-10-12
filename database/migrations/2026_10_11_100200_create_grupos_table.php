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
        // D.5 grupos
        Schema::create('grupos', function (Blueprint $table) {
            $table->id();
            $table->string('clave', 30);
            $table->string('programa', 150);
            $table->unsignedTinyInteger('cuatrimestre');
            $table->string('periodo', 20);
            $table->boolean('activo')->default(true);
            $table->dateTime('created_at');
            $table->dateTime('updated_at');

            $table->unique(['clave', 'periodo'], 'uq_grupos_clave_periodo');
        });

        DB::statement("ALTER TABLE grupos ADD CONSTRAINT chk_grupos_cuatrimestre CHECK (cuatrimestre >= 1)");
        DB::statement("ALTER TABLE grupos ADD CONSTRAINT chk_grupos_activo CHECK (activo IN (0,1))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grupos');
    }
};
