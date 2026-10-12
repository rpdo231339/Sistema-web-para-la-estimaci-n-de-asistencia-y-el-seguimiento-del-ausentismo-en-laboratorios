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
        // D.3 espacios
        Schema::create('espacios', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100);
            $table->string('tipo', 20);
            $table->string('ubicacion', 200);
            $table->text('descripcion')->nullable();
            $table->boolean('activo')->default(true);
            $table->dateTime('created_at');
            $table->dateTime('updated_at');
        });

        DB::statement("ALTER TABLE espacios ADD CONSTRAINT chk_espacios_tipo CHECK (tipo IN ('aula','laboratorio'))");
        DB::statement("ALTER TABLE espacios ADD CONSTRAINT chk_espacios_activo CHECK (activo IN (0,1))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('espacios');
    }
};
