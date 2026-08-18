<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $groups = [
        ['pre_sale', 'Pre-Sale', 'fal fa-stream', 20, ['leads', 'pipeline', 'discovery']],
        ['client_management', 'Client Management', 'fal fa-building', 30, ['clients', 'packages', 'services', 'proposals', 'contracts']],
        ['project_delivery', 'Project Delivery', 'fal fa-briefcase', 40, ['projects', 'tasks', 'time']],
        ['content_marketing', 'Content & Marketing', 'fal fa-photo-video', 50, ['visits', 'content', 'media']],
        ['operations_finance', 'Operations & Finance', 'fal fa-chart-line', 60, ['calendar', 'invoices', 'reports']],
        ['settings', 'Settings', 'fal fa-cog', 70, ['team', 'settings', 'audit']],
    ];

    public function up(): void
    {
        Schema::create('navigation_groups', function (Blueprint $table): void {
            $table->id();
            $table->string('slug')->unique();
            $table->string('label');
            $table->string('icon')->default('fal fa-folder');
            $table->unsignedInteger('position')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::table('navigation_items', function (Blueprint $table): void {
            $table->foreignId('group_id')->nullable()->after('parent_id')->constrained('navigation_groups')->nullOnDelete();
        });

        foreach ($this->groups as [$slug, $label, $icon, $position, $modules]) {
            DB::table('navigation_groups')->updateOrInsert(
                ['slug' => $slug],
                ['label' => $label, 'icon' => $icon, 'position' => $position, 'active' => true, 'created_at' => now(), 'updated_at' => now()]
            );

            $groupId = DB::table('navigation_groups')->where('slug', $slug)->value('id');
            DB::table('navigation_items')->whereIn('module', $modules)->update(['group_id' => $groupId, 'updated_at' => now()]);
        }

        DB::table('navigation_items')->where('module', 'settings')->update(['label' => 'System Settings', 'updated_at' => now()]);
        DB::table('permissions')->where('slug', 'settings.access')->update(['name' => 'Access System Settings']);
    }

    public function down(): void
    {
        DB::table('navigation_items')->where('module', 'settings')->update(['label' => 'Settings', 'updated_at' => now()]);
        DB::table('permissions')->where('slug', 'settings.access')->update(['name' => 'Access Settings']);

        Schema::table('navigation_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('group_id');
        });
        Schema::dropIfExists('navigation_groups');
    }
};
