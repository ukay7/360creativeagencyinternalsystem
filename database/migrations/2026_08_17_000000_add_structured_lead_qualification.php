<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table): void {
            $table->string('company_size_range')->nullable()->after('employee_count');
            $table->string('years_in_business_range')->nullable()->after('company_size_range');
            $table->string('business_stage')->nullable()->after('years_in_business_range');
            $table->string('budget_range')->nullable()->after('estimated_budget');
        });

        Schema::create('lead_service_interests', function (Blueprint $table): void {
            $table->foreignId('lead_id')->constrained('leads')->cascadeOnDelete();
            $table->foreignId('service_id')->constrained('services');
            $table->primary(['lead_id', 'service_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_service_interests');

        Schema::table('leads', function (Blueprint $table): void {
            $table->dropColumn(['company_size_range', 'years_in_business_range', 'business_stage', 'budget_range']);
        });
    }
};
