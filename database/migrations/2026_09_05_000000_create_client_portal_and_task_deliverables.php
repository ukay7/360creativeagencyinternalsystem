<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table): void {
            $table->foreignId('portal_user_id')->nullable()->unique()->after('account_manager_id')->constrained('users')->nullOnDelete();
        });

        Schema::create('task_updates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('task_id')->constrained('project_tasks')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('visibility', 20)->default('internal');
            $table->text('body');
            $table->string('created_at', 35)->nullable();
            $table->index(['task_id', 'visibility', 'id']);
        });

        Schema::create('task_files', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('task_id')->constrained('project_tasks')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('visibility', 20)->default('client');
            $table->string('original_name');
            $table->string('stored_name');
            $table->string('storage_path', 500)->unique();
            $table->string('mime_type', 150)->nullable();
            $table->unsignedBigInteger('file_size')->default(0);
            $table->string('created_at', 35)->nullable();
            $table->index(['task_id', 'visibility', 'id']);
        });

        $clientRoleId = DB::table('roles')->where('slug', 'client')->value('id');
        if (! $clientRoleId) {
            $clientRoleId = DB::table('roles')->insertGetId([
                'name'=>'Client',
                'slug'=>'client',
                'description'=>'Secure client portal access to the client’s own projects, tasks, calendar, updates, and deliverables.',
                'created_at'=>date('c'),
            ]);
        }

        $permissionIds = DB::table('permissions')->whereIn('slug', [
            'dashboard.access', 'projects.access', 'tasks.access', 'calendar.access',
        ])->pluck('id');
        foreach ($permissionIds as $permissionId) {
            DB::table('role_permissions')->insertOrIgnore([
                'role_id'=>(int) $clientRoleId,
                'permission_id'=>(int) $permissionId,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('task_files');
        Schema::dropIfExists('task_updates');

        Schema::table('clients', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('portal_user_id');
        });

        $clientRoleId = DB::table('roles')->where('slug', 'client')->value('id');
        if ($clientRoleId) {
            DB::table('role_permissions')->where('role_id', $clientRoleId)->delete();
            if (! DB::table('users')->where('role_id', $clientRoleId)->exists()) {
                DB::table('roles')->where('id', $clientRoleId)->delete();
            }
        }
    }
};
