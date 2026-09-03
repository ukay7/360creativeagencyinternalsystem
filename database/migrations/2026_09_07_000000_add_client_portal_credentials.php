<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table): void {
            $table->text('portal_password_encrypted')->nullable()->after('portal_user_id');
            $table->string('portal_password_reset_at', 35)->nullable()->after('portal_password_encrypted');
            $table->string('portal_password_changed_at', 35)->nullable()->after('portal_password_reset_at');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table): void {
            $table->dropColumn([
                'portal_password_encrypted',
                'portal_password_reset_at',
                'portal_password_changed_at',
            ]);
        });
    }
};
