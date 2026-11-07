<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('member')->after('email');
            $table->timestamp('deactivated_at')->nullable();
            $table->string('password')->nullable()->change();
        });

        DB::statement("ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role IN ('admin', 'member'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE users DROP CONSTRAINT users_role_check');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'deactivated_at']);
            $table->string('password')->nullable(false)->change();
        });
    }
};
