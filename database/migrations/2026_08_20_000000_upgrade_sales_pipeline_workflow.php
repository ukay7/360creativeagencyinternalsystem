<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('opportunities', function (Blueprint $table): void {
            $table->foreignId('previous_stage_id')->nullable()->after('stage_id')->constrained('pipeline_stages')->nullOnDelete();
            $table->string('stage_entered_at', 35)->nullable()->after('lost_reason');
            $table->string('closed_at', 35)->nullable()->after('stage_entered_at');
        });

        Schema::table('proposals', function (Blueprint $table): void {
            $table->unsignedBigInteger('client_id')->nullable()->change();
            $table->foreignId('lead_id')->nullable()->after('client_id')->constrained()->nullOnDelete();
        });

        DB::table('opportunities')->update([
            'stage_entered_at' => DB::raw('COALESCE(updated_at, created_at)'),
        ]);

        $stages = [
            'new' => ['New Lead', 1],
            'contacted' => ['Contacted', 2],
            'qualified' => ['Qualified', 3],
            'discovery' => ['Discovery', 4],
            'proposal' => ['Proposal Sent', 5],
            'negotiation' => ['Negotiation', 6],
            'won' => ['Won', 7],
            'lost' => ['Lost', 8],
        ];

        foreach ($stages as $slug => [$name, $position]) {
            DB::table('pipeline_stages')->where('slug', $slug)->update([
                'name' => $name,
                'position' => $position,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('proposal_items')->whereIn('proposal_id', function ($query): void {
            $query->select('id')->from('proposals')->whereNull('client_id');
        })->delete();
        DB::table('proposals')->whereNull('client_id')->delete();

        Schema::table('proposals', function (Blueprint $table): void {
            $table->dropForeign(['lead_id']);
            $table->dropColumn('lead_id');
            $table->unsignedBigInteger('client_id')->nullable(false)->change();
        });

        Schema::table('opportunities', function (Blueprint $table): void {
            $table->dropForeign(['previous_stage_id']);
            $table->dropColumn(['previous_stage_id', 'stage_entered_at', 'closed_at']);
        });

        DB::table('pipeline_stages')->where('slug', 'discovery')->update(['name' => 'Discovery Call', 'position' => 3]);
        DB::table('pipeline_stages')->where('slug', 'qualified')->update(['position' => 4]);
    }
};
