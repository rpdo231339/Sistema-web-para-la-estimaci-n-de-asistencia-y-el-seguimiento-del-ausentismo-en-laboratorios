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
        // D.11 configuraciones_modelo (una configuracion usada es inmutable: los cambios crean otra fila)
        Schema::create('configuraciones_modelo', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100);
            $table->string('yolo_version', 100);
            $table->string('yolo_pesos', 255);
            $table->string('sam_version', 100);
            $table->string('sam_pesos', 255);
            $table->json('huellas_pesos');
            $table->json('parametros');
            $table->json('entorno');
            $table->boolean('activo')->default(true);
            $table->dateTime('created_at');
            $table->dateTime('updated_at');
        });

        DB::statement("ALTER TABLE configuraciones_modelo ADD CONSTRAINT chk_configuraciones_activo CHECK (activo IN (0,1))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('configuraciones_modelo');
    }
};
