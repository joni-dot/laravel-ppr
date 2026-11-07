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
            // How many days ahead a person is reminded of a package's coming dates; 0 turns the reminders off.
            $table->unsignedSmallInteger('notification_days')->default(7);
        });

        DB::statement('ALTER TABLE users ADD CONSTRAINT users_notification_days_check CHECK (notification_days BETWEEN 0 AND 90)');
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('notification_days');
        });
    }
};
