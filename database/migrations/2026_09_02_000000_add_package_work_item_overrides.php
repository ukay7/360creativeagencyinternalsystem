<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('package_service_item_rules', function (Blueprint $table): void {
            $table->string('name_override')->nullable()->after('included');
            $table->string('name_ar_override')->nullable()->after('name_override');
            $table->string('name_he_override')->nullable()->after('name_ar_override');
            $table->text('description_override')->nullable()->after('name_he_override');
            $table->text('description_ar_override')->nullable()->after('description_override');
            $table->text('description_he_override')->nullable()->after('description_ar_override');
            $table->integer('sort_order')->nullable()->after('estimated_hours');
        });
    }

    public function down(): void
    {
        Schema::table('package_service_item_rules', function (Blueprint $table): void {
            $table->dropColumn([
                'name_override', 'name_ar_override', 'name_he_override',
                'description_override', 'description_ar_override', 'description_he_override',
                'sort_order',
            ]);
        });
    }
};
