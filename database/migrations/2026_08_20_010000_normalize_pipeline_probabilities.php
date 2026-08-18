<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['discovery'=>65, 'proposal'=>75, 'negotiation'=>90] as $slug => $probability) {
            $stageId = DB::table('pipeline_stages')->where('slug', $slug)->value('id');
            if ($stageId) {
                DB::table('pipeline_stages')->where('id', $stageId)->update(['win_probability'=>$probability]);
                DB::table('opportunities')->where('stage_id', $stageId)->update(['probability'=>$probability]);
            }
        }
    }

    public function down(): void
    {
        foreach (['discovery'=>35, 'proposal'=>70, 'negotiation'=>85] as $slug => $probability) {
            $stageId = DB::table('pipeline_stages')->where('slug', $slug)->value('id');
            if ($stageId) {
                DB::table('pipeline_stages')->where('id', $stageId)->update(['win_probability'=>$probability]);
                DB::table('opportunities')->where('stage_id', $stageId)->update(['probability'=>$probability]);
            }
        }
    }
};
