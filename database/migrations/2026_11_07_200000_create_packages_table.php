<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            // The highest running number ever given, so numbers are never reused.
            $table->integer('last_package_number')->nullable();
        });

        Schema::create('packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('packages')->restrictOnDelete();
            $table->text('number');
            // The highest sub-number ever given under this package, so sub-numbers are never reused either.
            $table->integer('last_sub_number')->default(0);
            $table->foreignId('category_id')->nullable()->constrained('project_categories')->restrictOnDelete();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->foreignId('owner_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('buyer_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('status', 20)->default('draft');
            $table->string('purchase_type', 20)->nullable();
            $table->decimal('quantity', 14, 3)->nullable();
            $table->string('unit', 20)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('parent_id');
            $table->index('category_id');
        });

        // The running number followed by each sub-number, such as {7,1,3} for P100-ELE-0007.01.03.
        // Sorting by it lists every package right after its parent, with numbers compared as numbers,
        // and the unique index makes it impossible for two packages to get the same number.
        DB::statement('ALTER TABLE packages ADD COLUMN number_path integer[] NOT NULL');
        DB::statement('CREATE UNIQUE INDEX packages_number_path_unique ON packages (project_id, number_path)');
        DB::statement('CREATE INDEX packages_project_number_index ON packages (project_id, number)');
        DB::statement("ALTER TABLE packages ADD CONSTRAINT packages_status_check CHECK (status IN ('draft', 'planned', 'tendering', 'ordered', 'delivered', 'closed', 'cancelled'))");
        DB::statement("ALTER TABLE packages ADD CONSTRAINT packages_purchase_type_check CHECK (purchase_type IN ('equipment', 'material', 'service', 'subcontract'))");
        DB::statement('ALTER TABLE packages ADD CONSTRAINT packages_quantity_check CHECK (quantity > 0)');
        DB::statement('ALTER TABLE packages ADD CONSTRAINT packages_parent_check CHECK (parent_id <> id)');
    }

    public function down(): void
    {
        Schema::dropIfExists('packages');

        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('last_package_number');
        });
    }
};
