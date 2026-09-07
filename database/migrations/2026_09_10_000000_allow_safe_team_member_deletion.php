<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var array<int, array{0:string,1:string}> */
    private array $employeeReferences = [
        ['clients', 'account_manager_id'],
        ['leads', 'assigned_employee_id'],
        ['opportunities', 'owner_id'],
        ['consultations', 'completed_by'],
        ['projects', 'manager_id'],
        ['project_tasks', 'assigned_employee_id'],
        ['time_entries', 'employee_id'],
        ['content_visits', 'assigned_employee_id'],
        ['media', 'created_by'],
        ['content_items', 'assigned_employee_id'],
    ];

    /** @var array<int, array{0:string,1:string}> */
    private array $userReferences = [
        ['payments', 'recorded_by'],
        ['notes', 'user_id'],
        ['activities', 'user_id'],
        ['lead_followups', 'user_id'],
    ];

    public function up(): void
    {
        foreach (array_merge($this->employeeReferences, $this->userReferences) as [$tableName, $column]) {
            Schema::table($tableName, function (Blueprint $table) use ($column): void {
                $table->dropForeign([$column]);
            });

            Schema::table($tableName, function (Blueprint $table) use ($column): void {
                $table->unsignedBigInteger($column)->nullable()->change();
            });

            $parentTable = in_array([$tableName, $column], $this->employeeReferences, true)
                ? 'employees'
                : 'users';
            Schema::table($tableName, function (Blueprint $table) use ($column, $parentTable): void {
                $table->foreign($column)->references('id')->on($parentTable)->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (array_merge($this->employeeReferences, $this->userReferences) as [$tableName, $column]) {
            Schema::table($tableName, function (Blueprint $table) use ($column): void {
                $table->dropForeign([$column]);
            });

            $parentTable = in_array([$tableName, $column], $this->employeeReferences, true)
                ? 'employees'
                : 'users';
            Schema::table($tableName, function (Blueprint $table) use ($column, $parentTable): void {
                $table->foreign($column)->references('id')->on($parentTable);
            });
        }
    }
};
