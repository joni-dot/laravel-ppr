<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            // The risk level people were last told about, so the checker notices a change. NULL until the
            // package is first checked; that first check only stores the level. Never shown on a page.
            $table->string('notified_risk_level')->nullable();
        });

        DB::statement("ALTER TABLE packages ADD CONSTRAINT packages_notified_risk_level_check CHECK (notified_risk_level IN ('none', 'amber', 'red'))");

        // One row per date reminder sent, so each is sent only once. A moved date is a new due date and gets its own.
        Schema::create('sent_reminders', function (Blueprint $table) {
            $table->id();
            // Users are deactivated and packages are never deleted, so their rows stay.
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('package_id')->constrained()->restrictOnDelete();
            // Milestones can be removed, and their reminders go with them.
            $table->foreignId('project_milestone_id')->constrained()->cascadeOnDelete();
            $table->string('kind');
            $table->date('due_date');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['user_id', 'package_id', 'project_milestone_id', 'kind', 'due_date']);
            // For removing a milestone's reminders and for looking up a package's.
            $table->index('project_milestone_id');
            $table->index('package_id');
        });

        DB::statement("ALTER TABLE sent_reminders ADD CONSTRAINT sent_reminders_kind_check CHECK (kind IN ('coming_up', 'overdue'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('sent_reminders');

        Schema::table('packages', function (Blueprint $table) {
            $table->dropColumn('notified_risk_level');
        });
    }
};
