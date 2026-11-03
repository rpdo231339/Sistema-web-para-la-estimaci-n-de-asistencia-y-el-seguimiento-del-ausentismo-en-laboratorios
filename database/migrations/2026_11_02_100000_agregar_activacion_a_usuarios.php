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
        // activado_en vacio = cuenta creada por el administrador que aun no define su contrasena
        Schema::table('usuarios', function (Blueprint $table) {
            $table->dateTime('activado_en')->nullable()->after('activo');
        });

        // Las cuentas que ya existen (incluido el primer administrador) ya tienen contrasena: quedan activadas
        DB::statement('UPDATE usuarios SET activado_en = COALESCE(created_at, NOW())');

        // Tokens de activacion: misma estructura que password_reset_tokens, pero con vigencia propia
        Schema::create('activacion_tokens', function (Blueprint $table) {
            $table->string('email', 254)->primary();
            $table->string('token');
            $table->dateTime('created_at');

            $table->foreign('email')
                ->references('correo')
                ->on('usuarios')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activacion_tokens');

        Schema::table('usuarios', function (Blueprint $table) {
            $table->dropColumn('activado_en');
        });
    }
};
