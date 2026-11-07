<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_milestones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->smallInteger('position');
            // The milestone whose planned date is when the project needs the package, such as "Required on site".
            // Lead times count back from it to give the latest safe date of every earlier milestone.
            $table->boolean('is_need_date')->default(false);
            $table->timestamps();

            $table->index(['project_id', 'position']);
        });

        DB::statement('CREATE UNIQUE INDEX project_milestones_name_unique ON project_milestones (project_id, lower(name))');
        DB::statement('CREATE UNIQUE INDEX project_milestones_need_date_unique ON project_milestones (project_id) WHERE is_need_date');
        DB::statement('ALTER TABLE project_milestones ADD CONSTRAINT project_milestones_position_check CHECK (position >= 0)');

        Schema::create('package_milestones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('package_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_milestone_id')->constrained()->cascadeOnDelete();
            $table->date('planned_date')->nullable();
            $table->date('forecast_date')->nullable();
            $table->date('actual_date')->nullable();
            // Calendar days from the previous milestone to this one.
            $table->smallInteger('lead_days')->nullable();
            $table->timestamps();

            $table->unique(['package_id', 'project_milestone_id']);
            $table->index('project_milestone_id');
        });

        DB::statement('ALTER TABLE package_milestones ADD CONSTRAINT package_milestones_lead_days_check CHECK (lead_days BETWEEN 0 AND 3650)');

        $this->addDefaultMilestonesToExistingProjects();
    }

    public function down(): void
    {
        Schema::dropIfExists('package_milestones');
        Schema::dropIfExists('project_milestones');
    }

    /**
     * The same list as App\Actions\Projects\AddDefaultMilestones, written out here so this
     * migration keeps working if that list changes later.
     */
    private function addDefaultMilestonesToExistingProjects(): void
    {
        $names = [
            'Specification ready',
            'Request for quotation sent',
            'Offers received',
            'Order placed',
            'Delivered from supplier',
            'Required on site',
            'Installed / commissioned',
        ];

        foreach (DB::table('projects')->pluck('id') as $projectId) {
            DB::table('project_milestones')->insert(array_map(fn (string $name, int $position) => [
                'project_id' => $projectId,
                'name' => $name,
                'position' => $position,
                'is_need_date' => $name === 'Required on site',
                'created_at' => now(),
                'updated_at' => now(),
            ], $names, array_keys($names)));
        }
    }
};
