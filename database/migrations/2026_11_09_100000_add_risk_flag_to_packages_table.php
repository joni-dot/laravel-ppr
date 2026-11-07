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
            // Someone marked the package as high risk by hand, and why. See App\Risk\PackageRisk.
            $table->text('risk_flag_reason')->nullable();
            // Users are deactivated, never deleted, so who flagged it stays known.
            $table->foreignId('risk_flagged_by_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('risk_flagged_at')->nullable();
        });

        DB::statement('ALTER TABLE packages ADD CONSTRAINT packages_risk_flag_check CHECK ((risk_flag_reason IS NULL) = (risk_flagged_at IS NULL))');
    }

    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('risk_flagged_by_id');
            $table->dropColumn(['risk_flag_reason', 'risk_flagged_at']);
        });
    }
};
