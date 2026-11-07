<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * In-app notifications, in Laravel's own table layout plus project_id. "type" holds the kind
 * (such as "package_assigned"), not a class name; see App\Notifications\PackageNotification.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type', 50);
            $table->string('notifiable_type');
            $table->unsignedBigInteger('notifiable_id');
            // A real column, not only in the data, so hiding notifications of projects a person has left can use an index.
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->jsonb('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            // A person's notifications, newest first: the notifications page.
            $table->index(['notifiable_type', 'notifiable_id', 'created_at']);
        });

        // The unread count every page shows. Partial, so it stays small however many old notifications there are.
        DB::statement('CREATE INDEX notifications_unread_index ON notifications (notifiable_type, notifiable_id, project_id) WHERE read_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
