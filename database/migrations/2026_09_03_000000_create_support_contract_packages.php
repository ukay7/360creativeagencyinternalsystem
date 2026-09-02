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
            $table->foreignId('support_package_id')->nullable()->after('package_id')->constrained('packages')->nullOnDelete();
        });

        $services = DB::table('services')->where('active', 1)->where('quote_enabled', 1)
            ->orderBy('quote_sort')->orderBy('id')
            ->get(['id', 'name', 'description', 'description_ar', 'description_he', 'pricing_type'])->all();
        $service = collect($services)->first(static fn(object $row): bool => $row->name === 'Social Media Management')
            ?? collect($services)->first(static fn(object $row): bool => $row->pricing_type === 'monthly')
            ?? ($services[0] ?? null);

        $plans = DB::table('quote_memberships')->whereNotIn('code', ['none', 'custom'])->where('active', 1)
            ->orderBy('sort_order')->orderBy('id')->get()->all();
        $now = date('c');

        foreach ($plans as $position => $plan) {
            $packageId = DB::table('packages')->where('package_type', 'support_contract')->where('tier', $plan->code)->value('id');
            $values = [
                'name'=>$plan->name, 'name_ar'=>$plan->name_ar, 'name_he'=>$plan->name_he,
                'description'=>$plan->description, 'description_ar'=>$plan->description_ar, 'description_he'=>$plan->description_he,
                'featured'=>$plan->featured, 'display_order'=>$position + 1, 'active'=>1, 'updated_at'=>$now,
            ];
            if ($packageId) {
                DB::table('packages')->where('id', $packageId)->update($values);
            } else {
                $packageId = DB::table('packages')->insertGetId($values + [
                    'package_type'=>'support_contract', 'tier'=>$plan->code, 'created_at'=>$now,
                ]);
            }

            DB::table('proposals')->where('builder_version', 1)->where('membership_plan', $plan->code)
                ->whereNull('support_package_id')->update(['support_package_id'=>$packageId]);

            if (! $service || DB::table('package_items')->where('package_id', $packageId)->exists()) { continue; }

            DB::table('package_items')->insert([
                'package_id'=>$packageId, 'service_id'=>$service->id, 'quantity'=>1, 'unit_price'=>$plan->monthly_price,
                'scope_note'=>$plan->description ?: $service->description, 'description'=>$plan->description ?: $service->description,
                'description_ar'=>$plan->description_ar ?: $service->description_ar,
                'description_he'=>$plan->description_he ?: $service->description_he,
                'included'=>1, 'sort_order'=>1, 'estimated_cost'=>0, 'estimated_hours'=>0,
            ]);

            $workItems = DB::table('service_work_items')->where('service_id', $service->id)->where('active', 1)
                ->orderBy('sort_order')->orderBy('id')->get()->all();
            foreach ($workItems as $workPosition => $workItem) {
                DB::table('package_service_item_rules')->updateOrInsert(
                    ['package_id'=>$packageId, 'service_work_item_id'=>$workItem->id],
                    ['included'=>1, 'sort_order'=>$workPosition + 1, 'created_at'=>$now, 'updated_at'=>$now]
                );
            }
        }
    }

    public function down(): void
    {
        Schema::table('proposals', function (Blueprint $table): void {
            $table->dropForeign(['support_package_id']);
            $table->dropColumn('support_package_id');
        });
        DB::table('packages')->where('package_type', 'support_contract')->delete();
    }
};
