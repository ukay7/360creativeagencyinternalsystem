<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table): void {
            $table->string('name_ar')->nullable()->after('name');
            $table->string('name_he')->nullable()->after('name_ar');
            $table->text('description_ar')->nullable()->after('description');
            $table->text('description_he')->nullable()->after('description_ar');
            $table->boolean('quote_enabled')->default(true)->after('active');
            $table->integer('quote_sort')->default(0)->after('quote_enabled');
        });

        Schema::table('packages', function (Blueprint $table): void {
            $table->string('tier', 20)->nullable()->after('package_type')->index();
            $table->string('name_ar')->nullable()->after('name');
            $table->string('name_he')->nullable()->after('name_ar');
            $table->text('description_ar')->nullable()->after('description');
            $table->text('description_he')->nullable()->after('description_ar');
            $table->integer('display_order')->default(0)->after('featured');
        });

        Schema::table('package_items', function (Blueprint $table): void {
            $table->decimal('unit_price', 12, 2)->default(0)->after('quantity');
            $table->text('description')->nullable()->after('scope_note');
            $table->text('description_ar')->nullable()->after('description');
            $table->text('description_he')->nullable()->after('description_ar');
            $table->boolean('included')->default(true)->after('description_he');
            $table->integer('sort_order')->default(0)->after('included');
        });

        Schema::table('proposals', function (Blueprint $table): void {
            $table->integer('builder_version')->nullable()->after('proposal_number');
            $table->string('locale', 5)->default('en')->after('builder_version');
            $table->string('business_name')->nullable()->after('title');
            $table->string('contact_name')->nullable()->after('business_name');
            $table->string('contact_email')->nullable()->after('contact_name');
            $table->string('contact_phone')->nullable()->after('contact_email');
            $table->string('website')->nullable()->after('contact_phone');
            $table->string('business_stage')->nullable()->after('website');
            $table->integer('years_operating')->nullable()->after('business_stage');
            $table->text('internal_notes')->nullable()->after('summary');
            $table->longText('assessment_data')->nullable()->after('internal_notes');
            $table->integer('assessment_score')->default(0)->after('assessment_data');
            $table->string('recommended_tier', 20)->nullable()->after('assessment_score');
            $table->string('selected_tier', 20)->nullable()->after('recommended_tier');
            $table->string('support_plan', 30)->default('none')->after('selected_tier');
            $table->decimal('support_amount', 12, 2)->default(0)->after('support_plan');
            $table->string('membership_plan', 30)->default('none')->after('support_amount');
            $table->decimal('membership_amount', 12, 2)->default(0)->after('membership_plan');
            $table->string('currency', 3)->default('CAD')->after('membership_amount');
            $table->decimal('tax_percent', 6, 2)->default(13)->after('currency');
            $table->integer('validity_days')->default(7)->after('valid_until');
            $table->integer('revision')->default(1)->after('validity_days');
            $table->unsignedBigInteger('parent_proposal_id')->nullable()->after('revision');
            $table->integer('sent_count')->default(0)->after('parent_proposal_id');
            $table->string('last_sent_at', 35)->nullable()->after('sent_count');
            $table->string('share_token', 64)->nullable()->unique()->after('last_sent_at');
            $table->string('updated_at', 35)->nullable()->after('created_at');
        });

        Schema::table('proposal_items', function (Blueprint $table): void {
            $table->string('item_type', 30)->default('service')->after('service_id');
            $table->string('title')->nullable()->after('item_type');
            $table->string('title_ar')->nullable()->after('title');
            $table->string('title_he')->nullable()->after('title_ar');
            $table->text('description_ar')->nullable()->after('title_he');
            $table->text('description_he')->nullable()->after('description_ar');
            $table->string('category')->nullable()->after('description_he');
            $table->decimal('discount_percent', 6, 2)->default(0)->after('unit_price');
            $table->integer('sort_order')->default(0)->after('total');
        });

        Schema::create('quote_support_plans', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('name_ar')->nullable();
            $table->string('name_he')->nullable();
            $table->text('description')->nullable();
            $table->text('description_ar')->nullable();
            $table->text('description_he')->nullable();
            $table->decimal('rate_percent', 6, 2)->default(0);
            $table->decimal('multiplier', 6, 2)->default(0);
            $table->integer('duration_months')->default(0);
            $table->boolean('active')->default(true);
            $table->integer('sort_order')->default(0);
        });

        Schema::create('quote_memberships', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('name_ar')->nullable();
            $table->string('name_he')->nullable();
            $table->text('description')->nullable();
            $table->text('description_ar')->nullable();
            $table->text('description_he')->nullable();
            $table->decimal('monthly_price', 12, 2)->default(0);
            $table->boolean('featured')->default(false);
            $table->boolean('active')->default(true);
            $table->integer('sort_order')->default(0);
        });

        Schema::create('proposal_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('proposal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('recipient');
            $table->string('channel', 20)->default('email');
            $table->string('locale', 5)->default('en');
            $table->string('status', 20)->default('recorded');
            $table->string('sent_at', 35);
            $table->text('notes')->nullable();
        });

        DB::table('quote_support_plans')->insert([
            ['code'=>'none','name'=>'No support','name_ar'=>'بدون دعم','name_he'=>'ללא תמיכה','description'=>'No technical support commitment.','description_ar'=>'بدون التزام بالدعم الفني.','description_he'=>'ללא התחייבות לתמיכה טכנית.','rate_percent'=>0,'multiplier'=>0,'duration_months'=>0,'active'=>1,'sort_order'=>1],
            ['code'=>'3_months','name'=>'3 months','name_ar'=>'3 أشهر','name_he'=>'3 חודשים','description'=>'Priority technical support for three months.','description_ar'=>'دعم فني ذو أولوية لمدة ثلاثة أشهر.','description_he'=>'תמיכה טכנית בעדיפות לשלושה חודשים.','rate_percent'=>25,'multiplier'=>1,'duration_months'=>3,'active'=>1,'sort_order'=>2],
            ['code'=>'6_months','name'=>'6 months','name_ar'=>'6 أشهر','name_he'=>'6 חודשים','description'=>'Extended technical support for six months.','description_ar'=>'دعم فني ممتد لمدة ستة أشهر.','description_he'=>'תמיכה טכנית מורחבת לשישה חודשים.','rate_percent'=>20,'multiplier'=>2,'duration_months'=>6,'active'=>1,'sort_order'=>3],
            ['code'=>'8_months','name'=>'8 months','name_ar'=>'8 أشهر','name_he'=>'8 חודשים','description'=>'Continuity support across an eight-month delivery period.','description_ar'=>'دعم استمراري خلال فترة تنفيذ مدتها ثمانية أشهر.','description_he'=>'תמיכת המשכיות לאורך תקופת ביצוע של שמונה חודשים.','rate_percent'=>18,'multiplier'=>3,'duration_months'=>8,'active'=>1,'sort_order'=>4],
            ['code'=>'12_months','name'=>'12 months','name_ar'=>'12 شهراً','name_he'=>'12 חודשים','description'=>'Annual technical support and continuity coverage.','description_ar'=>'دعم فني سنوي وتغطية استمرارية.','description_he'=>'תמיכה טכנית שנתית וכיסוי המשכיות.','rate_percent'=>18,'multiplier'=>4,'duration_months'=>12,'active'=>1,'sort_order'=>5],
        ]);

        DB::table('quote_memberships')->insert([
            ['code'=>'none','name'=>'No membership','name_ar'=>'بدون عضوية','name_he'=>'ללא חברות','description'=>'Setup-only engagement.','description_ar'=>'تنفيذ الإعداد فقط.','description_he'=>'התקשרות להקמה בלבד.','monthly_price'=>0,'featured'=>0,'active'=>1,'sort_order'=>1],
            ['code'=>'starter','name'=>'Website Care','name_ar'=>'العناية بالموقع','name_he'=>'תחזוקת אתר','description'=>'Updates, backups, uptime monitoring, security checks, and up to two minor content changes monthly.','description_ar'=>'تحديثات ونسخ احتياطي ومراقبة وتشخيص أمان وتعديلان بسيطان للمحتوى شهرياً.','description_he'=>'עדכונים, גיבויים, ניטור, בדיקות אבטחה ועד שני שינויי תוכן קטנים בחודש.','monthly_price'=>495,'featured'=>0,'active'=>1,'sort_order'=>2],
            ['code'=>'growth','name'=>'Social Media Essentials','name_ar'=>'أساسيات التواصل الاجتماعي','name_he'=>'סושיאל בסיסי','description'=>'Two platforms, 12 monthly posts, scheduling, community monitoring, and a performance report.','description_ar'=>'منصتان و12 منشوراً شهرياً وجدولة ومتابعة المجتمع وتقرير أداء.','description_he'=>'שתי פלטפורמות, 12 פוסטים בחודש, תזמון, ניטור קהילה ודוח ביצועים.','monthly_price'=>1495,'featured'=>1,'active'=>1,'sort_order'=>3],
            ['code'=>'premium','name'=>'Social Media Growth','name_ar'=>'نمو التواصل الاجتماعي','name_he'=>'צמיחת סושיאל','description'=>'Three platforms, 20 monthly posts, short-form video, community management, and monthly optimization.','description_ar'=>'ثلاث منصات و20 منشوراً شهرياً وفيديو قصير وإدارة مجتمع وتحسين شهري.','description_he'=>'שלוש פלטפורמות, 20 פוסטים בחודש, וידאו קצר, ניהול קהילה ואופטימיזציה חודשית.','monthly_price'=>2495,'featured'=>0,'active'=>1,'sort_order'=>4],
            ['code'=>'seo_reputation','name'=>'SEO & Reputation','name_ar'=>'تحسين البحث والسمعة','name_he'=>'SEO ומוניטין','description'=>'Local SEO, keyword tracking, Google Business updates, review monitoring, and monthly reporting.','description_ar'=>'تحسين محلي وتتبع كلمات وتحديث ملف Google ومتابعة التقييمات وتقارير شهرية.','description_he'=>'SEO מקומי, מעקב מילות מפתח, עדכוני Google Business, ניטור ביקורות ודוח חודשי.','monthly_price'=>995,'featured'=>0,'active'=>1,'sort_order'=>5],
            ['code'=>'content_creation','name'=>'Content Creation','name_ar'=>'إنشاء المحتوى','name_he'=>'יצירת תוכן','description'=>'A monthly bank of branded copy, graphics, and platform-ready creative assets.','description_ar'=>'حزمة شهرية من النصوص والتصاميم والأصول الإبداعية الجاهزة للنشر.','description_he'=>'בנק חודשי של קופי, גרפיקה ונכסים יצירתיים מוכנים לפרסום.','monthly_price'=>1295,'featured'=>0,'active'=>1,'sort_order'=>6],
        ]);

        if (Schema::hasTable('navigation_groups') && Schema::hasTable('navigation_items')) {
            $groupId = DB::table('navigation_groups')->where('slug', 'quote_onboarding')->value('id');
            if (! $groupId) {
                $groupId = DB::table('navigation_groups')->insertGetId([
                    'slug'=>'quote_onboarding','label'=>'Quote & Onboarding','icon'=>'fal fa-file-invoice-dollar','position'=>20,'active'=>1,
                ]);
            }

            foreach ([
                ['quote_studio','New Quote','quote_studio','fal fa-file-plus',10,'proposals.access'],
                ['saved_quotes','Saved Quotes','saved_quotes','fal fa-list-check',20,'proposals.access'],
                ['quote_settings','Quote Settings','quote_settings','fal fa-sliders-h',40,'settings.access'],
            ] as [$key,$label,$route,$icon,$position,$permission]) {
                DB::table('navigation_items')->updateOrInsert(
                    ['module'=>$key],
                    ['label'=>$label,'route'=>$route,'icon'=>$icon,'position'=>$position,'permission_slug'=>$permission,'group_id'=>$groupId,'active'=>1,'created_at'=>now(),'updated_at'=>now()]
                );
            }

            DB::table('navigation_items')->where('module', 'clients')->update(['group_id'=>$groupId,'position'=>30,'active'=>1,'updated_at'=>now()]);
            DB::table('navigation_items')->whereIn('module', ['leads','pipeline','discovery','packages','services','proposals'])->update(['active'=>0,'updated_at'=>now()]);
            DB::table('navigation_groups')->whereIn('slug', ['pre_sale','client_management'])->update(['active'=>0]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('navigation_items')) {
            DB::table('navigation_items')->whereIn('module', ['quote_studio','saved_quotes','quote_settings'])->delete();
            DB::table('navigation_items')->whereIn('module', ['leads','pipeline','discovery','packages','services','proposals'])->update(['active'=>1,'updated_at'=>now()]);
        }
        if (Schema::hasTable('navigation_groups')) {
            DB::table('navigation_groups')->where('slug', 'quote_onboarding')->delete();
            DB::table('navigation_groups')->whereIn('slug', ['pre_sale','client_management'])->update(['active'=>1]);
        }

        Schema::dropIfExists('proposal_deliveries');
        Schema::dropIfExists('quote_memberships');
        Schema::dropIfExists('quote_support_plans');

        Schema::table('proposal_items', function (Blueprint $table): void {
            $table->dropColumn(['item_type','title','title_ar','title_he','description_ar','description_he','category','discount_percent','sort_order']);
        });
        Schema::table('proposals', function (Blueprint $table): void {
            $table->dropUnique(['share_token']);
            $table->dropColumn(['builder_version','locale','business_name','contact_name','contact_email','contact_phone','website','business_stage','years_operating','internal_notes','assessment_data','assessment_score','recommended_tier','selected_tier','support_plan','support_amount','membership_plan','membership_amount','currency','tax_percent','validity_days','revision','parent_proposal_id','sent_count','last_sent_at','share_token','updated_at']);
        });
        Schema::table('package_items', function (Blueprint $table): void {
            $table->dropColumn(['unit_price','description','description_ar','description_he','included','sort_order']);
        });
        Schema::table('packages', function (Blueprint $table): void {
            $table->dropIndex(['tier']);
            $table->dropColumn(['tier','name_ar','name_he','description_ar','description_he','display_order']);
        });
        Schema::table('services', function (Blueprint $table): void {
            $table->dropColumn(['name_ar','name_he','description_ar','description_he','quote_enabled','quote_sort']);
        });
    }
};
