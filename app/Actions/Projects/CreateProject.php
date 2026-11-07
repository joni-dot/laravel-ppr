<?php

namespace App\Actions\Projects;

use App\Enums\ProjectRole;
use App\Enums\SettingKey;
use App\Models\Project;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateProject
{
    public function __construct(private readonly AddDefaultMilestones $addDefaultMilestones) {}

    /**
     * Each project gets its own copy of the company risk warnings, so changing
     * the company defaults later never changes a running project's warnings.
     */
    public function handle(string $name, string $code, ?string $description, User $manager): Project
    {
        return DB::transaction(function () use ($name, $code, $description, $manager) {
            $project = new Project(['name' => $name, 'code' => $code, 'description' => $description]);
            $project->risk_amber_days = Setting::integer(SettingKey::RiskAmberDays);
            $project->risk_red_days = Setting::integer(SettingKey::RiskRedDays);
            $project->save();

            $membership = $project->members()->make(['role' => ProjectRole::ProjectManager]);
            $membership->user()->associate($manager);
            $membership->save();

            $this->addDefaultMilestones->handle($project);

            return $project;
        });
    }
}
