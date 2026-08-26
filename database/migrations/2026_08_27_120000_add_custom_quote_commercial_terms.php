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
            $table->string('membership_name')->nullable()->after('membership_plan');
            $table->integer('membership_duration_months')->default(0)->after('membership_name');
            $table->decimal('membership_monthly_price', 12, 2)->default(0)->after('membership_duration_months');
        });

        DB::table('quote_support_plans')->updateOrInsert(
            ['code'=>'custom'],
            ['name'=>'Custom support','name_ar'=>'دعم مخصص','name_he'=>'תמיכה מותאמת','description'=>'Enter a fixed support amount for this quote.','description_ar'=>'أدخل مبلغ دعم ثابتاً لهذا العرض.','description_he'=>'הזינו סכום תמיכה קבוע להצעה זו.','rate_percent'=>0,'multiplier'=>0,'duration_months'=>0,'active'=>1,'sort_order'=>99]
        );

        $memberships = [
            ['code'=>'starter','name'=>'Social Media Essential','name_ar'=>'التواصل الاجتماعي الأساسي','name_he'=>'סושיאל Essential','description'=>'One platform, 8 monthly posts, scheduling, community monitoring, and a monthly report.','description_ar'=>'منصة واحدة و8 منشورات شهرياً وجدولة ومتابعة المجتمع وتقرير شهري.','description_he'=>'פלטפורמה אחת, 8 פוסטים בחודש, תזמון, ניטור קהילה ודוח חודשי.','monthly_price'=>995,'featured'=>0,'active'=>1,'sort_order'=>2],
            ['code'=>'growth','name'=>'Social Media Growth','name_ar'=>'نمو التواصل الاجتماعي','name_he'=>'סושיאל Growth','description'=>'Two platforms, 12 monthly posts, short-form content, community management, and optimization.','description_ar'=>'منصتان و12 منشوراً شهرياً ومحتوى قصير وإدارة مجتمع وتحسين مستمر.','description_he'=>'שתי פלטפורמות, 12 פוסטים בחודש, תוכן קצר, ניהול קהילה ואופטימיזציה.','monthly_price'=>1495,'featured'=>1,'active'=>1,'sort_order'=>3],
            ['code'=>'premium','name'=>'Social Media Pro','name_ar'=>'التواصل الاجتماعي الاحترافي','name_he'=>'סושיאל Pro','description'=>'Three platforms, 20 monthly posts, video, active community management, campaigns, and strategy reporting.','description_ar'=>'ثلاث منصات و20 منشوراً شهرياً وفيديو وإدارة مجتمع نشطة وحملات وتقارير استراتيجية.','description_he'=>'שלוש פלטפורמות, 20 פוסטים בחודש, וידאו, ניהול קהילה פעיל, קמפיינים ודוחות אסטרטגיים.','monthly_price'=>2495,'featured'=>0,'active'=>1,'sort_order'=>4],
            ['code'=>'custom','name'=>'Custom social media plan','name_ar'=>'باقة تواصل اجتماعي مخصصة','name_he'=>'תוכנית סושיאל מותאמת','description'=>'Enter a custom plan name, monthly price, and term for this client.','description_ar'=>'أدخل اسم الباقة والسعر الشهري والمدة لهذا العميل.','description_he'=>'הזינו שם תוכנית, מחיר חודשי ותקופה מותאמים ללקוח.','monthly_price'=>0,'featured'=>0,'active'=>1,'sort_order'=>99],
        ];
        foreach ($memberships as $plan) {
            DB::table('quote_memberships')->updateOrInsert(['code'=>$plan['code']], $plan);
        }
        DB::table('quote_memberships')->whereIn('code', ['seo_reputation','content_creation'])->update(['active'=>0]);

        DB::table('proposals')->whereNotNull('builder_version')->orderBy('id')->get()->each(function (object $proposal): void {
            if (($proposal->membership_plan ?? 'none') === 'none') {
                DB::table('proposals')->where('id', $proposal->id)->update(['membership_name'=>null,'membership_duration_months'=>0,'membership_monthly_price'=>0]);
                return;
            }
            $plan = DB::table('quote_memberships')->where('code', $proposal->membership_plan)->first();
            DB::table('proposals')->where('id', $proposal->id)->update([
                'membership_name'=>$plan->name ?? null,
                'membership_duration_months'=>1,
                'membership_monthly_price'=>(float)($proposal->membership_amount ?? 0),
            ]);
        });
    }

    public function down(): void
    {
        DB::table('quote_support_plans')->where('code', 'custom')->delete();
        DB::table('quote_memberships')->where('code', 'custom')->delete();
        DB::table('quote_memberships')->whereIn('code', ['seo_reputation','content_creation'])->update(['active'=>1]);
        Schema::table('proposals', function (Blueprint $table): void {
            $table->dropColumn(['membership_name','membership_duration_months','membership_monthly_price']);
        });
    }
};
