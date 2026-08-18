<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table): void {
            $table->id(); $table->string('name'); $table->string('slug')->unique(); $table->text('description')->nullable(); $table->string('created_at', 35)->nullable();
        });
        Schema::create('permissions', function (Blueprint $table): void {
            $table->id(); $table->string('name'); $table->string('slug')->unique();
        });
        Schema::create('role_permissions', function (Blueprint $table): void {
            $table->foreignId('role_id')->constrained()->cascadeOnDelete(); $table->foreignId('permission_id')->constrained()->cascadeOnDelete(); $table->primary(['role_id', 'permission_id']);
        });
        Schema::create('users', function (Blueprint $table): void {
            $table->id(); $table->foreignId('role_id')->constrained(); $table->string('name'); $table->string('email')->unique(); $table->string('password_hash'); $table->string('status')->default('active'); $table->string('last_login_at', 35)->nullable(); $table->string('created_at', 35)->nullable(); $table->string('updated_at', 35)->nullable();
        });
        Schema::create('employees', function (Blueprint $table): void {
            $table->id(); $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete(); $table->string('name'); $table->string('email')->unique(); $table->string('phone')->nullable(); $table->string('job_title')->nullable(); $table->string('department'); $table->text('skills')->nullable(); $table->decimal('hourly_cost', 12, 2)->default(0); $table->decimal('capacity_hours', 8, 2)->default(40); $table->string('status')->default('active'); $table->string('created_at', 35)->nullable();
        });
        Schema::create('business_sizes', function (Blueprint $table): void {
            $table->id(); $table->string('name')->unique(); $table->integer('min_employees')->nullable(); $table->integer('max_employees')->nullable(); $table->boolean('active')->default(true);
        });
        Schema::create('lead_sources', function (Blueprint $table): void {
            $table->id(); $table->string('name')->unique(); $table->boolean('active')->default(true);
        });
        Schema::create('pipeline_stages', function (Blueprint $table): void {
            $table->id(); $table->string('name'); $table->string('slug')->unique(); $table->integer('position'); $table->integer('win_probability')->default(10); $table->string('color')->default('info'); $table->boolean('is_closed')->default(false);
        });
        Schema::create('businesses', function (Blueprint $table): void {
            $table->id(); $table->foreignId('business_size_id')->nullable()->constrained(); $table->string('name'); $table->string('legal_name')->nullable(); $table->string('industry')->nullable(); $table->string('website')->nullable(); $table->integer('employee_count')->nullable(); $table->integer('years_in_business')->nullable(); $table->string('tax_number')->nullable(); $table->string('created_at', 35)->nullable();
        });
        Schema::create('clients', function (Blueprint $table): void {
            $table->id(); $table->foreignId('business_id')->constrained(); $table->foreignId('account_manager_id')->nullable()->constrained('employees'); $table->string('name'); $table->string('email')->nullable(); $table->string('phone')->nullable(); $table->string('status')->default('active'); $table->string('health')->default('healthy'); $table->text('health_notes')->nullable(); $table->date('joined_at'); $table->string('created_at', 35)->nullable(); $table->index(['status', 'health']);
        });
        Schema::create('leads', function (Blueprint $table): void {
            $table->id(); $table->string('first_name'); $table->string('last_name'); $table->string('company_name'); $table->string('phone')->nullable(); $table->string('email')->nullable(); $table->string('website')->nullable(); $table->text('address')->nullable(); $table->string('industry')->nullable(); $table->integer('employee_count')->nullable(); $table->foreignId('source_id')->nullable()->constrained('lead_sources'); $table->string('status')->default('new'); $table->integer('lead_score')->default(50); $table->foreignId('assigned_employee_id')->nullable()->constrained('employees'); $table->decimal('estimated_budget', 12, 2)->default(0); $table->text('services_interested')->nullable(); $table->text('notes')->nullable(); $table->string('next_follow_up_at', 35)->nullable(); $table->string('last_contact_at', 35)->nullable(); $table->foreignId('converted_client_id')->nullable()->constrained('clients'); $table->string('created_at', 35)->nullable(); $table->string('updated_at', 35)->nullable(); $table->index(['status', 'next_follow_up_at']);
        });
        Schema::create('business_locations', function (Blueprint $table): void {
            $table->id(); $table->foreignId('business_id')->constrained()->cascadeOnDelete(); $table->string('name'); $table->text('address'); $table->string('city')->nullable(); $table->string('state')->nullable(); $table->string('postal_code')->nullable(); $table->boolean('primary_location')->default(false);
        });
        Schema::create('contacts', function (Blueprint $table): void {
            $table->id(); $table->foreignId('client_id')->constrained()->cascadeOnDelete(); $table->string('name'); $table->string('title')->nullable(); $table->string('email')->nullable(); $table->string('phone')->nullable(); $table->boolean('primary_contact')->default(false);
        });
        Schema::create('service_categories', function (Blueprint $table): void {
            $table->id(); $table->string('name')->unique(); $table->boolean('active')->default(true);
        });
        Schema::create('services', function (Blueprint $table): void {
            $table->id(); $table->foreignId('category_id')->constrained('service_categories'); $table->string('name')->unique(); $table->text('description')->nullable(); $table->string('pricing_type'); $table->decimal('default_price', 12, 2)->default(0); $table->decimal('cost_estimate', 12, 2)->default(0); $table->decimal('estimated_hours', 8, 2)->default(0); $table->boolean('taxable')->default(true); $table->boolean('active')->default(true); $table->text('notes')->nullable(); $table->string('created_at', 35)->nullable();
        });
        Schema::create('packages', function (Blueprint $table): void {
            $table->id(); $table->string('name'); $table->string('package_type'); $table->text('description')->nullable(); $table->boolean('featured')->default(false); $table->boolean('active')->default(true); $table->string('created_at', 35)->nullable(); $table->string('updated_at', 35)->nullable();
        });
        Schema::create('package_pricing', function (Blueprint $table): void {
            $table->id(); $table->foreignId('package_id')->constrained()->cascadeOnDelete(); $table->foreignId('business_size_id')->nullable()->constrained(); $table->decimal('base_price', 12, 2)->default(0); $table->decimal('monthly_fee', 12, 2)->default(0); $table->decimal('setup_fee', 12, 2)->default(0); $table->decimal('minimum_price', 12, 2)->nullable(); $table->decimal('maximum_price', 12, 2)->nullable(); $table->decimal('discount_percent', 6, 2)->default(0); $table->decimal('tax_percent', 6, 2)->default(0); $table->decimal('deposit_percent', 6, 2)->default(0); $table->date('effective_from'); $table->date('effective_to')->nullable();
        });
        Schema::create('package_items', function (Blueprint $table): void {
            $table->id(); $table->foreignId('package_id')->constrained()->cascadeOnDelete(); $table->foreignId('service_id')->constrained(); $table->decimal('quantity', 10, 2)->default(1); $table->text('scope_note')->nullable(); $table->decimal('estimated_cost', 12, 2)->default(0); $table->decimal('estimated_hours', 8, 2)->default(0);
        });
        Schema::create('package_limits', function (Blueprint $table): void {
            $table->id(); $table->foreignId('package_id')->constrained()->cascadeOnDelete(); $table->string('limit_key'); $table->string('label'); $table->decimal('included_quantity', 10, 2)->default(0); $table->string('unit'); $table->decimal('overage_price', 12, 2)->default(0); $table->string('period')->default('month'); $table->unique(['package_id', 'limit_key']);
        });
        Schema::create('add_ons', function (Blueprint $table): void {
            $table->id(); $table->string('name')->unique(); $table->string('pricing_type'); $table->decimal('price', 12, 2); $table->decimal('cost_estimate', 12, 2)->default(0); $table->boolean('active')->default(true);
        });
        Schema::create('opportunities', function (Blueprint $table): void {
            $table->id(); $table->foreignId('lead_id')->nullable()->constrained(); $table->foreignId('client_id')->nullable()->constrained(); $table->foreignId('stage_id')->constrained('pipeline_stages'); $table->foreignId('owner_id')->nullable()->constrained('employees'); $table->string('title'); $table->decimal('estimated_value', 12, 2)->default(0); $table->text('services')->nullable(); $table->integer('probability')->default(10); $table->date('expected_close_date')->nullable(); $table->text('next_action')->nullable(); $table->text('notes')->nullable(); $table->text('lost_reason')->nullable(); $table->string('created_at', 35)->nullable(); $table->string('updated_at', 35)->nullable(); $table->index(['stage_id', 'expected_close_date']);
        });
        Schema::create('consultations', function (Blueprint $table): void {
            $table->id(); $table->foreignId('lead_id')->nullable()->constrained(); $table->foreignId('client_id')->nullable()->constrained(); $table->longText('business_goals')->nullable(); $table->longText('marketing_snapshot')->nullable(); $table->longText('target_customer')->nullable(); $table->longText('current_problems')->nullable(); $table->longText('desired_outcomes')->nullable(); $table->text('notes')->nullable(); $table->foreignId('completed_by')->nullable()->constrained('employees'); $table->string('completed_at', 35)->nullable();
        });
        Schema::create('proposals', function (Blueprint $table): void {
            $table->id(); $table->string('proposal_number')->unique(); $table->foreignId('client_id')->constrained(); $table->foreignId('opportunity_id')->nullable()->constrained(); $table->foreignId('package_id')->nullable()->constrained(); $table->string('title'); $table->text('summary')->nullable(); $table->decimal('subtotal', 12, 2)->default(0); $table->decimal('discount', 12, 2)->default(0); $table->decimal('tax', 12, 2)->default(0); $table->decimal('total', 12, 2)->default(0); $table->decimal('deposit', 12, 2)->default(0); $table->string('status')->default('draft'); $table->date('valid_until')->nullable(); $table->string('created_at', 35)->nullable();
        });
        Schema::create('proposal_items', function (Blueprint $table): void {
            $table->id(); $table->foreignId('proposal_id')->constrained()->cascadeOnDelete(); $table->foreignId('service_id')->nullable()->constrained(); $table->text('description'); $table->decimal('quantity', 10, 2)->default(1); $table->decimal('unit_price', 12, 2)->default(0); $table->decimal('total', 12, 2)->default(0);
        });
        Schema::create('projects', function (Blueprint $table): void {
            $table->id(); $table->foreignId('client_id')->constrained(); $table->foreignId('package_id')->nullable()->constrained(); $table->foreignId('manager_id')->nullable()->constrained('employees'); $table->string('name'); $table->string('project_type'); $table->date('start_date'); $table->date('deadline')->nullable(); $table->decimal('budget', 12, 2)->default(0); $table->decimal('estimated_hours', 8, 2)->default(0); $table->decimal('actual_hours', 8, 2)->default(0); $table->string('status')->default('planning'); $table->string('priority')->default('medium'); $table->text('notes')->nullable(); $table->string('created_at', 35)->nullable(); $table->index(['client_id', 'status']);
        });
        Schema::create('contracts', function (Blueprint $table): void {
            $table->id(); $table->foreignId('client_id')->constrained(); $table->foreignId('package_id')->nullable()->constrained(); $table->foreignId('project_id')->nullable()->constrained(); $table->date('start_date'); $table->date('end_date')->nullable(); $table->text('payment_terms')->nullable(); $table->string('renewal_type')->nullable(); $table->text('cancellation_terms')->nullable(); $table->text('scope')->nullable(); $table->string('status')->default('draft');
        });
        Schema::create('project_tasks', function (Blueprint $table): void {
            $table->id(); $table->foreignId('project_id')->constrained()->cascadeOnDelete(); $table->foreignId('client_id')->constrained(); $table->foreignId('assigned_employee_id')->nullable()->constrained('employees'); $table->string('title'); $table->text('description')->nullable(); $table->date('due_date')->nullable(); $table->string('priority')->default('medium'); $table->string('status')->default('todo'); $table->decimal('estimated_hours', 8, 2)->default(0); $table->decimal('actual_hours', 8, 2)->default(0); $table->string('completed_at', 35)->nullable(); $table->string('created_at', 35)->nullable(); $table->index(['assigned_employee_id', 'status', 'due_date']);
        });
        Schema::create('time_entries', function (Blueprint $table): void {
            $table->id(); $table->foreignId('employee_id')->constrained(); $table->foreignId('client_id')->constrained(); $table->foreignId('project_id')->constrained(); $table->foreignId('task_id')->nullable()->constrained('project_tasks'); $table->date('entry_date'); $table->decimal('hours', 8, 2); $table->text('description'); $table->boolean('billable')->default(true); $table->string('created_at', 35)->nullable(); $table->index(['project_id', 'entry_date']);
        });
        Schema::create('subscriptions', function (Blueprint $table): void {
            $table->id(); $table->foreignId('client_id')->constrained(); $table->foreignId('package_id')->constrained(); $table->decimal('monthly_price', 12, 2)->default(0); $table->date('start_date'); $table->date('renewal_date')->nullable(); $table->date('contract_end_date')->nullable(); $table->string('billing_frequency')->default('monthly'); $table->decimal('deposit', 12, 2)->default(0); $table->decimal('discount_percent', 6, 2)->default(0); $table->decimal('tax_percent', 6, 2)->default(0); $table->string('status')->default('active'); $table->date('cancellation_date')->nullable(); $table->string('created_at', 35)->nullable(); $table->index(['status', 'renewal_date']);
        });
        Schema::create('content_visits', function (Blueprint $table): void {
            $table->id(); $table->foreignId('client_id')->constrained(); $table->foreignId('business_location_id')->nullable()->constrained(); $table->foreignId('subscription_id')->nullable()->constrained(); $table->foreignId('assigned_employee_id')->nullable()->constrained('employees'); $table->date('visit_date'); $table->time('start_time')->nullable(); $table->time('end_time')->nullable(); $table->string('visit_type'); $table->string('status')->default('scheduled'); $table->text('purpose')->nullable(); $table->text('equipment')->nullable(); $table->text('content_captured')->nullable(); $table->text('notes')->nullable(); $table->boolean('is_additional')->default(false); $table->decimal('additional_charge', 12, 2)->default(0); $table->string('created_at', 35)->nullable(); $table->index(['subscription_id', 'visit_date', 'status']);
        });
        Schema::create('media', function (Blueprint $table): void {
            $table->id(); $table->foreignId('client_id')->constrained(); $table->foreignId('project_id')->nullable()->constrained(); $table->foreignId('visit_id')->nullable()->constrained('content_visits'); $table->string('content_type'); $table->text('file_path'); $table->string('original_name'); $table->string('mime_type'); $table->unsignedBigInteger('file_size'); $table->string('captured_at', 35)->nullable(); $table->foreignId('created_by')->nullable()->constrained('employees'); $table->text('tags')->nullable(); $table->text('usage_rights')->nullable(); $table->string('status')->default('raw'); $table->string('created_at', 35)->nullable();
        });
        Schema::create('content_items', function (Blueprint $table): void {
            $table->id(); $table->foreignId('client_id')->constrained(); $table->foreignId('project_id')->nullable()->constrained(); $table->foreignId('assigned_employee_id')->nullable()->constrained('employees'); $table->string('title'); $table->string('platform'); $table->string('content_type'); $table->longText('caption')->nullable(); $table->text('hashtags')->nullable(); $table->string('scheduled_at', 35)->nullable(); $table->string('status')->default('idea'); $table->string('approval_status')->default('pending'); $table->text('approval_comments')->nullable(); $table->string('created_at', 35)->nullable(); $table->index(['client_id', 'status', 'scheduled_at']);
        });
        Schema::create('invoices', function (Blueprint $table): void {
            $table->id(); $table->string('invoice_number')->unique(); $table->foreignId('client_id')->constrained(); $table->foreignId('project_id')->nullable()->constrained(); $table->foreignId('package_id')->nullable()->constrained(); $table->date('issue_date'); $table->date('due_date'); $table->decimal('subtotal', 12, 2)->default(0); $table->decimal('discount', 12, 2)->default(0); $table->decimal('tax', 12, 2)->default(0); $table->decimal('total', 12, 2)->default(0); $table->decimal('amount_paid', 12, 2)->default(0); $table->string('status')->default('draft'); $table->text('notes')->nullable(); $table->string('created_at', 35)->nullable(); $table->index(['client_id', 'status', 'due_date']);
        });
        Schema::create('invoice_items', function (Blueprint $table): void {
            $table->id(); $table->foreignId('invoice_id')->constrained()->cascadeOnDelete(); $table->text('description'); $table->decimal('quantity', 10, 2)->default(1); $table->decimal('unit_price', 12, 2)->default(0); $table->decimal('total', 12, 2)->default(0);
        });
        Schema::create('payments', function (Blueprint $table): void {
            $table->id(); $table->foreignId('invoice_id')->constrained(); $table->decimal('amount', 12, 2); $table->date('payment_date'); $table->string('method'); $table->string('reference')->nullable(); $table->text('notes')->nullable(); $table->foreignId('recorded_by')->nullable()->constrained('users'); $table->string('created_at', 35)->nullable(); $table->index(['invoice_id', 'payment_date']);
        });
        Schema::create('notes', function (Blueprint $table): void {
            $table->id(); $table->foreignId('client_id')->nullable()->constrained()->cascadeOnDelete(); $table->foreignId('project_id')->nullable()->constrained()->cascadeOnDelete(); $table->foreignId('user_id')->constrained(); $table->text('body'); $table->string('created_at', 35)->nullable();
        });
        Schema::create('activities', function (Blueprint $table): void {
            $table->id(); $table->foreignId('client_id')->nullable()->constrained()->cascadeOnDelete(); $table->foreignId('user_id')->nullable()->constrained(); $table->string('type'); $table->text('description'); $table->string('entity_type')->nullable(); $table->unsignedBigInteger('entity_id')->nullable(); $table->string('created_at', 35)->nullable(); $table->index(['client_id', 'created_at']);
        });
        Schema::create('notifications', function (Blueprint $table): void {
            $table->id(); $table->foreignId('user_id')->constrained()->cascadeOnDelete(); $table->string('type'); $table->string('title'); $table->text('body')->nullable(); $table->string('link')->nullable(); $table->string('read_at', 35)->nullable(); $table->string('created_at', 35)->nullable();
        });
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id(); $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); $table->string('action'); $table->string('entity_type'); $table->unsignedBigInteger('entity_id')->nullable(); $table->longText('old_values')->nullable(); $table->longText('new_values')->nullable(); $table->string('ip_address', 45)->nullable(); $table->string('created_at', 35)->nullable(); $table->index(['entity_type', 'entity_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        foreach (['audit_logs','notifications','activities','notes','payments','invoice_items','invoices','content_items','media','content_visits','subscriptions','time_entries','project_tasks','contracts','projects','proposal_items','proposals','consultations','opportunities','add_ons','package_limits','package_items','package_pricing','packages','services','service_categories','contacts','business_locations','leads','clients','businesses','pipeline_stages','lead_sources','business_sizes','employees','users','role_permissions','permissions','roles'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::enableForeignKeyConstraints();
    }
};
