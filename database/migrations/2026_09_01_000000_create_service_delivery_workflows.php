<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_work_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('name_ar')->nullable();
            $table->string('name_he')->nullable();
            $table->text('task_description')->nullable();
            $table->text('task_description_ar')->nullable();
            $table->text('task_description_he')->nullable();
            $table->string('schedule_type', 20)->default('one_time');
            $table->string('frequency', 20)->nullable();
            $table->unsignedSmallInteger('interval_count')->default(1);
            $table->unsignedInteger('starts_after_days')->default(0);
            $table->unsignedInteger('due_after_days')->default(7);
            $table->unsignedInteger('occurrence_count')->nullable();
            $table->unsignedInteger('end_after_months')->nullable();
            $table->decimal('estimated_hours', 8, 2)->default(0);
            $table->integer('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->string('created_at', 35)->nullable();
            $table->string('updated_at', 35)->nullable();
            $table->index(['service_id', 'active', 'sort_order']);
        });

        Schema::create('package_service_item_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('package_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_work_item_id')->constrained()->cascadeOnDelete();
            $table->boolean('included')->default(true);
            $table->string('value_label')->nullable();
            $table->string('value_label_ar')->nullable();
            $table->string('value_label_he')->nullable();
            $table->string('schedule_type', 20)->nullable();
            $table->string('frequency', 20)->nullable();
            $table->unsignedSmallInteger('interval_count')->nullable();
            $table->unsignedInteger('starts_after_days')->nullable();
            $table->unsignedInteger('due_after_days')->nullable();
            $table->unsignedInteger('occurrence_count')->nullable();
            $table->unsignedInteger('end_after_months')->nullable();
            $table->decimal('estimated_hours', 8, 2)->nullable();
            $table->string('created_at', 35)->nullable();
            $table->string('updated_at', 35)->nullable();
            $table->unique(['package_id', 'service_work_item_id'], 'package_service_work_item_unique');
        });

        Schema::create('proposal_work_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('proposal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('proposal_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_work_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('title_ar')->nullable();
            $table->string('title_he')->nullable();
            $table->text('description')->nullable();
            $table->text('description_ar')->nullable();
            $table->text('description_he')->nullable();
            $table->string('scope_value')->nullable();
            $table->string('scope_value_ar')->nullable();
            $table->string('scope_value_he')->nullable();
            $table->string('schedule_type', 20)->default('one_time');
            $table->string('frequency', 20)->nullable();
            $table->unsignedSmallInteger('interval_count')->default(1);
            $table->unsignedInteger('starts_after_days')->default(0);
            $table->unsignedInteger('due_after_days')->default(7);
            $table->unsignedInteger('occurrence_count')->nullable();
            $table->unsignedInteger('end_after_months')->nullable();
            $table->decimal('estimated_hours', 8, 2)->default(0);
            $table->integer('sort_order')->default(0);
            $table->index(['proposal_id', 'proposal_item_id']);
        });

        Schema::create('project_task_recurrences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained();
            $table->foreignId('proposal_work_item_id')->nullable()->constrained('proposal_work_items')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('frequency', 20);
            $table->unsignedSmallInteger('interval_count')->default(1);
            $table->date('starts_on');
            $table->date('next_run_date')->nullable();
            $table->date('end_date')->nullable();
            $table->unsignedInteger('occurrence_limit')->nullable();
            $table->unsignedInteger('occurrences_created')->default(0);
            $table->unsignedInteger('due_after_days')->default(7);
            $table->decimal('estimated_hours', 8, 2)->default(0);
            $table->boolean('active')->default(true);
            $table->date('last_generated_on')->nullable();
            $table->string('created_at', 35)->nullable();
            $table->string('updated_at', 35)->nullable();
            $table->unique(['project_id', 'proposal_work_item_id'], 'project_work_item_recurrence_unique');
            $table->index(['active', 'next_run_date']);
        });

        Schema::table('projects', function (Blueprint $table): void {
            $table->foreignId('source_proposal_id')->nullable()->after('package_id')->constrained('proposals')->nullOnDelete();
            $table->foreignId('source_proposal_item_id')->nullable()->after('source_proposal_id')->constrained('proposal_items')->nullOnDelete();
            $table->unique('source_proposal_item_id');
        });

        Schema::table('project_tasks', function (Blueprint $table): void {
            $table->foreignId('recurrence_id')->nullable()->after('assigned_employee_id')->constrained('project_task_recurrences')->nullOnDelete();
            $table->foreignId('proposal_work_item_id')->nullable()->after('recurrence_id')->constrained('proposal_work_items')->nullOnDelete();
            $table->date('occurrence_date')->nullable()->after('due_date');
            $table->unique(['recurrence_id', 'occurrence_date']);
        });

        $this->seedExampleWorkflows();
    }

    private function seedExampleWorkflows(): void
    {
        $packages = DB::table('packages')->where('package_type', 'quote_setup')->get(['id', 'tier'])->all();
        $now = date('c');

        $photographyId = DB::table('services')->where('name', 'Photography')->value('id');
        if ($photographyId) {
            $definitions = [
                ['Shooting Hours', 'Plan and complete the client photography session.', ['basic'=>'1 Hour','medium'=>'3 Hours','pro'=>'6 Hours']],
                ['Locations', 'Confirm and cover the approved shoot locations.', ['basic'=>'1','medium'=>'2','pro'=>'3+']],
                ['Edited Photos', 'Edit and prepare the agreed number of final photographs.', ['basic'=>'15','medium'=>'40','pro'=>'80+']],
                ['Product Photos', 'Capture and edit the agreed number of product photographs.', ['basic'=>'10','medium'=>'25','pro'=>'50+']],
                ['Lifestyle Photos', 'Produce lifestyle photographs for the approved campaign.', ['basic'=>'Not included','medium'=>'10','pro'=>'25+']],
                ['Basic Retouching', 'Apply colour, crop, exposure, and basic retouching.', ['basic'=>'Included','medium'=>'Included','pro'=>'Included']],
                ['Advanced Retouching', 'Apply advanced compositing and detailed retouching where required.', ['basic'=>'Not included','medium'=>'Limited','pro'=>'Included']],
                ['Photo Selection', 'Curate and approve the final photograph selection.', ['basic'=>'Basic','medium'=>'Advanced','pro'=>'Full']],
                ['Revisions', 'Complete the included revision rounds.', ['basic'=>'1','medium'=>'2','pro'=>'Unlimited']],
                ['Delivery', 'Deliver final approved files in the required formats.', ['basic'=>'5 Days','medium'=>'7 Days','pro'=>'10 Days']],
            ];

            foreach ($definitions as $sort => [$name, $description, $values]) {
                $workItemId = DB::table('service_work_items')->insertGetId([
                    'service_id'=>$photographyId,'name'=>$name,'task_description'=>$description,'schedule_type'=>'one_time',
                    'frequency'=>null,'interval_count'=>1,'starts_after_days'=>0,'due_after_days'=>7,
                    'occurrence_count'=>null,'end_after_months'=>null,'estimated_hours'=>$name === 'Shooting Hours' ? 3 : 1,
                    'sort_order'=>$sort + 1,'active'=>1,'created_at'=>$now,'updated_at'=>$now,
                ]);
                foreach ($packages as $package) {
                    $tier = in_array($package->tier, ['basic','medium','pro'], true) ? $package->tier : 'medium';
                    $included = ! ($tier === 'basic' && in_array($name, ['Lifestyle Photos','Advanced Retouching'], true));
                    $dueDays = $name === 'Delivery' ? ['basic'=>5,'medium'=>7,'pro'=>10][$tier] : null;
                    DB::table('package_service_item_rules')->insert([
                        'package_id'=>$package->id,'service_work_item_id'=>$workItemId,'included'=>$included,
                        'value_label'=>$values[$tier],'due_after_days'=>$dueDays,'created_at'=>$now,'updated_at'=>$now,
                    ]);
                }
            }
        }

        $socialId = DB::table('services')->where('name', 'Social Media Management')->value('id');
        if ($socialId) {
            foreach ([
                ['Content Creation & Scheduling','Create, approve, and schedule the package content batch.','weekly',['basic'=>'2 posts per week','medium'=>'4 posts per week','pro'=>'7 posts per week'],4],
                ['Performance Report','Prepare a channel performance report with next actions.','monthly',['basic'=>'Monthly summary','medium'=>'Monthly report','pro'=>'Monthly report + strategy'],2],
            ] as $sort => [$name,$description,$frequency,$values,$hours]) {
                $workItemId = DB::table('service_work_items')->insertGetId([
                    'service_id'=>$socialId,'name'=>$name,'task_description'=>$description,'schedule_type'=>'recurring',
                    'frequency'=>$frequency,'interval_count'=>1,'starts_after_days'=>0,'due_after_days'=>5,
                    'occurrence_count'=>null,'end_after_months'=>12,'estimated_hours'=>$hours,'sort_order'=>$sort + 1,
                    'active'=>1,'created_at'=>$now,'updated_at'=>$now,
                ]);
                foreach ($packages as $package) {
                    $tier = in_array($package->tier, ['basic','medium','pro'], true) ? $package->tier : 'medium';
                    DB::table('package_service_item_rules')->insert([
                        'package_id'=>$package->id,'service_work_item_id'=>$workItemId,'included'=>1,
                        'value_label'=>$values[$tier],'created_at'=>$now,'updated_at'=>$now,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::table('project_tasks', function (Blueprint $table): void {
            $table->dropUnique(['recurrence_id', 'occurrence_date']);
            $table->dropForeign(['recurrence_id']);
            $table->dropForeign(['proposal_work_item_id']);
            $table->dropColumn(['recurrence_id', 'proposal_work_item_id', 'occurrence_date']);
        });
        Schema::table('projects', function (Blueprint $table): void {
            $table->dropUnique(['source_proposal_item_id']);
            $table->dropForeign(['source_proposal_id']);
            $table->dropForeign(['source_proposal_item_id']);
            $table->dropColumn(['source_proposal_id', 'source_proposal_item_id']);
        });
        Schema::dropIfExists('project_task_recurrences');
        Schema::dropIfExists('proposal_work_items');
        Schema::dropIfExists('package_service_item_rules');
        Schema::dropIfExists('service_work_items');
    }
};
