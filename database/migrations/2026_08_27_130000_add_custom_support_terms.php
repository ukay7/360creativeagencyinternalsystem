<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proposals', function (Blueprint $table): void {
            $table->string('support_name')->nullable()->after('support_plan');
            $table->integer('support_duration_months')->default(0)->after('support_name');
            $table->decimal('support_rate_percent', 6, 2)->default(0)->after('support_duration_months');
        });

        DB::table('quote_support_plans')->where('code', 'custom')->update([
            'description'=>'Enter a plan name, duration, and percentage. The amount is calculated from the setup subtotal.',
            'description_ar'=>'أدخل اسم الخطة والمدة والنسبة، ويتم احتساب المبلغ من مجموع الإعداد.',
            'description_he'=>'הזינו שם תוכנית, משך ואחוז. הסכום יחושב מסכום ההקמה.',
        ]);

        DB::table('proposals')->whereNotNull('builder_version')->orderBy('id')->get()->each(function (object $proposal): void {
            $code = (string)($proposal->support_plan ?? 'none');
            if ($code === 'none') {
                DB::table('proposals')->where('id', $proposal->id)->update(['support_name'=>null,'support_duration_months'=>0,'support_rate_percent'=>0]);
                return;
            }

            $plan = DB::table('quote_support_plans')->where('code', $code)->first();
            $duration = $code === 'custom' ? 3 : (int)($plan->duration_months ?? 0);
            $rate = (float)($plan->rate_percent ?? 0);
            if ($code === 'custom') {
                $setupSubtotal = (float)DB::table('proposal_items')->where('proposal_id', $proposal->id)->where('item_type', 'service')->sum('total');
                $rate = $setupSubtotal > 0 ? round(((float)$proposal->support_amount / $setupSubtotal) * 100, 2) : 0;
            }
            DB::table('proposals')->where('id', $proposal->id)->update([
                'support_name'=>$plan->name ?? 'Custom support',
                'support_duration_months'=>$duration,
                'support_rate_percent'=>$rate,
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('proposals', function (Blueprint $table): void {
            $table->dropColumn(['support_name','support_duration_months','support_rate_percent']);
        });
    }
};
