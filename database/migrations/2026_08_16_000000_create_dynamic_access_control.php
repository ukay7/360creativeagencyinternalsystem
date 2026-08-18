<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $modules = [
        ['dashboard', 'Command Center', 'dashboard', 'fal fa-home', 10],
        ['leads', 'Leads', 'leads', 'fal fa-user-plus', 20],
        ['pipeline', 'Sales Pipeline', 'pipeline', 'fal fa-stream', 30],
        ['discovery', 'Discovery', 'discovery', 'fal fa-comments-alt', 40],
        ['clients', 'Clients', 'clients', 'fal fa-building', 50],
        ['packages', 'Packages', 'packages', 'fal fa-box-open', 60],
        ['services', 'Services', 'services', 'fal fa-concierge-bell', 70],
        ['proposals', 'Proposals', 'proposals', 'fal fa-file-signature', 80],
        ['contracts', 'Contracts', 'contracts', 'fal fa-file-contract', 90],
        ['projects', 'Projects', 'projects', 'fal fa-briefcase', 100],
        ['tasks', 'Tasks', 'tasks', 'fal fa-check-square', 110],
        ['visits', 'Content Visits', 'visits', 'fal fa-camera-retro', 120],
        ['content', 'Content Calendar', 'content', 'fal fa-calendar-alt', 130],
        ['media', 'Media Library', 'media', 'fal fa-photo-video', 140],
        ['calendar', 'Agency Calendar', 'calendar', 'fal fa-calendar', 150],
        ['invoices', 'Invoices', 'invoices', 'fal fa-file-invoice-dollar', 160],
        ['time', 'Time Tracking', 'time', 'fal fa-clock', 170],
        ['team', 'Team', 'team', 'fal fa-users', 180],
        ['reports', 'Reports', 'reports', 'fal fa-chart-line', 190],
        ['settings', 'Settings', 'settings', 'fal fa-cog', 200],
        ['audit', 'Audit Log', 'audit', 'fal fa-history', 210],
    ];

    public function up(): void
    {
        Schema::create('navigation_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('navigation_items')->nullOnDelete();
            $table->string('module')->unique();
            $table->string('label');
            $table->string('route')->unique();
            $table->string('icon')->default('fal fa-circle');
            $table->string('permission_slug');
            $table->unsignedInteger('position')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('user_permissions', function (Blueprint $table): void {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->boolean('allowed');
            $table->timestamps();
            $table->primary(['user_id', 'permission_id']);
        });

        foreach ($this->modules as [$module, $label, $route, $icon, $position]) {
            $slug = $module.'.access';
            DB::table('permissions')->updateOrInsert(
                ['slug' => $slug],
                ['name' => 'Access '.$label]
            );
            DB::table('navigation_items')->updateOrInsert(
                ['module' => $module],
                ['label'=>$label, 'route'=>$route, 'icon'=>$icon, 'permission_slug'=>$slug, 'position'=>$position, 'active'=>true, 'created_at'=>now(), 'updated_at'=>now()]
            );
        }

        $legacyMap = [
            'dashboard.view' => ['dashboard','calendar'],
            'crm.manage' => ['leads','pipeline','discovery','proposals'],
            'clients.manage' => ['clients','contracts'],
            'packages.manage' => ['packages','services'],
            'projects.manage' => ['projects'],
            'tasks.manage' => ['tasks','time'],
            'content.manage' => ['visits','content','media'],
            'finance.manage' => ['invoices'],
            'team.manage' => ['team'],
            'reports.view' => ['reports'],
            'settings.manage' => ['settings','audit'],
        ];

        foreach ($legacyMap as $legacySlug => $modules) {
            $roleIds = DB::table('role_permissions as rp')
                ->join('permissions as p', 'p.id', '=', 'rp.permission_id')
                ->where('p.slug', $legacySlug)
                ->pluck('rp.role_id');
            foreach ($roleIds as $roleId) {
                foreach ($modules as $module) {
                    $permissionId = DB::table('permissions')->where('slug', $module.'.access')->value('id');
                    DB::table('role_permissions')->insertOrIgnore(['role_id'=>$roleId, 'permission_id'=>$permissionId]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_permissions');
        Schema::dropIfExists('navigation_items');
        $slugs = array_map(static fn (array $module): string => $module[0].'.access', $this->modules);
        DB::table('permissions')->whereIn('slug', $slugs)->delete();
    }
};
