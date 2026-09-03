<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_profiles', function (Blueprint $table): void {
            $table->string('ocn_bin')->nullable()->after('tax_number');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_profiles', function (Blueprint $table): void {
            $table->dropColumn('ocn_bin');
        });
    }
};
