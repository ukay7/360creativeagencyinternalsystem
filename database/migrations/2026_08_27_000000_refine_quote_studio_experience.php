<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $supportPlans = [
            ['code'=>'none','name'=>'No support','name_ar'=>'بدون دعم','name_he'=>'ללא תמיכה','description'=>'No technical support commitment.','description_ar'=>'بدون التزام بالدعم الفني.','description_he'=>'ללא התחייבות לתמיכה טכנית.','rate_percent'=>0,'multiplier'=>0,'duration_months'=>0,'active'=>1,'sort_order'=>1],
            ['code'=>'3_months','name'=>'3 months','name_ar'=>'3 أشهر','name_he'=>'3 חודשים','description'=>'Priority technical support for three months.','description_ar'=>'دعم فني ذو أولوية لمدة ثلاثة أشهر.','description_he'=>'תמיכה טכנית בעדיפות לשלושה חודשים.','rate_percent'=>25,'multiplier'=>1,'duration_months'=>3,'active'=>1,'sort_order'=>2],
            ['code'=>'6_months','name'=>'6 months','name_ar'=>'6 أشهر','name_he'=>'6 חודשים','description'=>'Extended technical support for six months.','description_ar'=>'دعم فني ممتد لمدة ستة أشهر.','description_he'=>'תמיכה טכנית מורחבת לשישה חודשים.','rate_percent'=>20,'multiplier'=>2,'duration_months'=>6,'active'=>1,'sort_order'=>3],
            ['code'=>'8_months','name'=>'8 months','name_ar'=>'8 أشهر','name_he'=>'8 חודשים','description'=>'Continuity support across an eight-month delivery period.','description_ar'=>'دعم استمراري خلال فترة تنفيذ مدتها ثمانية أشهر.','description_he'=>'תמיכת המשכיות לאורך תקופת ביצוע של שמונה חודשים.','rate_percent'=>18,'multiplier'=>3,'duration_months'=>8,'active'=>1,'sort_order'=>4],
            ['code'=>'12_months','name'=>'12 months','name_ar'=>'12 شهراً','name_he'=>'12 חודשים','description'=>'Annual technical support and continuity coverage.','description_ar'=>'دعم فني سنوي وتغطية استمرارية.','description_he'=>'תמיכה טכנית שנתית וכיסוי המשכיות.','rate_percent'=>18,'multiplier'=>4,'duration_months'=>12,'active'=>1,'sort_order'=>5],
        ];
        foreach ($supportPlans as $plan) {
            DB::table('quote_support_plans')->updateOrInsert(['code'=>$plan['code']], $plan);
        }

        $memberships = [
            ['code'=>'none','name'=>'No membership','name_ar'=>'بدون عضوية','name_he'=>'ללא חברות','description'=>'Setup-only engagement.','description_ar'=>'تنفيذ الإعداد فقط.','description_he'=>'התקשרות להקמה בלבד.','monthly_price'=>0,'featured'=>0,'active'=>1,'sort_order'=>1],
            ['code'=>'starter','name'=>'Website Care','name_ar'=>'العناية بالموقع','name_he'=>'תחזוקת אתר','description'=>'Updates, backups, uptime monitoring, security checks, and up to two minor content changes monthly.','description_ar'=>'تحديثات ونسخ احتياطي ومراقبة وتشخيص أمان وتعديلان بسيطان للمحتوى شهرياً.','description_he'=>'עדכונים, גיבויים, ניטור, בדיקות אבטחה ועד שני שינויי תוכן קטנים בחודש.','monthly_price'=>495,'featured'=>0,'active'=>1,'sort_order'=>2],
            ['code'=>'growth','name'=>'Social Media Essentials','name_ar'=>'أساسيات التواصل الاجتماعي','name_he'=>'סושיאל בסיסי','description'=>'Two platforms, 12 monthly posts, scheduling, community monitoring, and a performance report.','description_ar'=>'منصتان و12 منشوراً شهرياً وجدولة ومتابعة المجتمع وتقرير أداء.','description_he'=>'שתי פלטפורמות, 12 פוסטים בחודש, תזמון, ניטור קהילה ודוח ביצועים.','monthly_price'=>1495,'featured'=>1,'active'=>1,'sort_order'=>3],
            ['code'=>'premium','name'=>'Social Media Growth','name_ar'=>'نمو التواصل الاجتماعي','name_he'=>'צמיחת סושיאל','description'=>'Three platforms, 20 monthly posts, short-form video, community management, and monthly optimization.','description_ar'=>'ثلاث منصات و20 منشوراً شهرياً وفيديو قصير وإدارة مجتمع وتحسين شهري.','description_he'=>'שלוש פלטפורמות, 20 פוסטים בחודש, וידאו קצר, ניהול קהילה ואופטימיזציה חודשית.','monthly_price'=>2495,'featured'=>0,'active'=>1,'sort_order'=>4],
            ['code'=>'seo_reputation','name'=>'SEO & Reputation','name_ar'=>'تحسين البحث والسمعة','name_he'=>'SEO ומוניטין','description'=>'Local SEO, keyword tracking, Google Business updates, review monitoring, and monthly reporting.','description_ar'=>'تحسين محلي وتتبع كلمات وتحديث ملف Google ومتابعة التقييمات وتقارير شهرية.','description_he'=>'SEO מקומי, מעקב מילות מפתח, עדכוני Google Business, ניטור ביקורות ודוח חודשי.','monthly_price'=>995,'featured'=>0,'active'=>1,'sort_order'=>5],
            ['code'=>'content_creation','name'=>'Content Creation','name_ar'=>'إنشاء المحتوى','name_he'=>'יצירת תוכן','description'=>'A monthly bank of branded copy, graphics, and platform-ready creative assets.','description_ar'=>'حزمة شهرية من النصوص والتصاميم والأصول الإبداعية الجاهزة للنشر.','description_he'=>'בנק חודשי של קופי, גרפיקה ונכסים יצירתיים מוכנים לפרסום.','monthly_price'=>1295,'featured'=>0,'active'=>1,'sort_order'=>6],
        ];
        foreach ($memberships as $plan) {
            DB::table('quote_memberships')->updateOrInsert(['code'=>$plan['code']], $plan);
        }
    }

    public function down(): void
    {
        DB::table('quote_support_plans')->where('code', '8_months')->delete();
        DB::table('quote_memberships')->whereIn('code', ['seo_reputation','content_creation'])->delete();
    }
};
