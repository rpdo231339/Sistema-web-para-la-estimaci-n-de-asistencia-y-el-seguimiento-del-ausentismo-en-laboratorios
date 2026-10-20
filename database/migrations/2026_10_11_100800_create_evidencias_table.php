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
        // D.10 evidencias (archivos privados y versiones)
        Schema::create('evidencias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pase_lista_id')->nullable()->constrained('pases_lista')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('archivo_origen_id')->nullable()->constrained('evidencias')->restrictOnDelete()->restrictOnUpdate();
            $table->unsignedInteger('version')->default(1);
            $table->string('tipo', 15);
            $table->string('ruta', 512);
            $table->string('nombre_original', 255);
            $table->string('mime', 100);
            $table->unsignedBigInteger('bytes');
            $table->char('sha256', 64);
            $table->unsignedInteger('ancho')->nullable();
            $table->unsignedInteger('alto')->nullable();
            $table->dateTime('capturada_en')->nullable();
            $table->unsignedBigInteger('instante_ms')->nullable();
            $table->foreignId('cargado_por')->constrained('usuarios')->restrictOnDelete()->restrictOnUpdate();
            $table->dateTime('created_at');

            // La version mas alta de cada pase es la vigente
            $table->unique(['pase_lista_id', 'version'], 'uq_evidencias_pase_version');
        });

        DB::statement("ALTER TABLE evidencias ADD CONSTRAINT chk_evidencias_tipo CHECK (tipo IN ('imagen','video','fotograma'))");
        DB::statement("ALTER TABLE evidencias ADD CONSTRAINT chk_evidencias_version CHECK (version >= 1)");
        DB::statement("ALTER TABLE evidencias ADD CONSTRAINT chk_evidencias_bytes CHECK (bytes > 0)");
        DB::statement("ALTER TABLE evidencias ADD CONSTRAINT chk_evidencias_dimensiones CHECK ((ancho IS NULL OR ancho > 0) AND (alto IS NULL OR alto > 0))");
        DB::statement("ALTER TABLE evidencias ADD CONSTRAINT chk_evidencias_fotograma CHECK (tipo <> 'fotograma' OR (archivo_origen_id IS NOT NULL AND instante_ms IS NOT NULL))");
        DB::statement("ALTER TABLE evidencias ADD CONSTRAINT chk_evidencias_captura CHECK (pase_lista_id IS NULL OR capturada_en IS NOT NULL)");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('evidencias');
    }
};
