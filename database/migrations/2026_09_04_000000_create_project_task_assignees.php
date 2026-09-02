<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_task_assignees', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('task_id')->constrained('project_tasks')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('assigned_at', 35)->nullable();
            $table->unique(['task_id', 'employee_id']);
            $table->index(['employee_id', 'task_id']);
        });

        $now = date('c');
        DB::table('project_tasks')->whereNotNull('assigned_employee_id')->orderBy('id')
            ->get(['id', 'assigned_employee_id'])->each(function (object $task) use ($now): void {
                DB::table('project_task_assignees')->insertOrIgnore([
                    'task_id'=>(int) $task->id,
                    'employee_id'=>(int) $task->assigned_employee_id,
                    'assigned_by'=>null,
                    'assigned_at'=>$now,
                ]);
            });

        $permissionIds = DB::table('permissions')->whereIn('slug', [
            'dashboard.access', 'projects.access', 'tasks.access', 'calendar.access',
        ])->pluck('id');
        $roleIds = DB::table('roles')->whereIn('slug', [
            'admin', 'sales', 'project_manager', 'marketing', 'creative', 'developer', 'employee',
        ])->pluck('id');
        foreach ($roleIds as $roleId) {
            foreach ($permissionIds as $permissionId) {
                DB::table('role_permissions')->insertOrIgnore([
                    'role_id'=>(int) $roleId,
                    'permission_id'=>(int) $permissionId,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('project_task_assignees');
    }
};
