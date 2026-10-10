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
        // D.2 usuarios
        Schema::create('usuarios', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 150);
            $table->string('correo', 254)->unique();
            $table->string('password');
            $table->string('rol', 20);
            $table->boolean('activo')->default(true);
            $table->unsignedInteger('version_seguridad')->default(1);
            $table->dateTime('created_at');
            $table->dateTime('updated_at');
        });
 
        // El rol solo puede ser uno de los tres definidos en el diseño
        DB::statement("ALTER TABLE usuarios ADD CONSTRAINT chk_usuarios_rol CHECK (rol IN ('administrador','encargado','profesor'))");
 
        // D.20 password_reset_tokens (la columna se llama email porque Laravel la usa asi)
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email', 254)->primary();
            $table->string('token');
            $table->dateTime('created_at');
 
            $table->foreign('email')
                ->references('correo')
                ->on('usuarios')
                ->cascadeOnDelete();
        });
 
        // Tabla tecnica de Laravel para guardar las sesiones (SESSION_DRIVER=database)
        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }
 
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('usuarios');
    }
};
 