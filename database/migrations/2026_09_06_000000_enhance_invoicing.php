<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_profiles', function (Blueprint $table): void {
            $table->id();
            $table->string('agency_name');
            $table->string('address_line_1')->nullable();
            $table->string('address_line_2')->nullable();
            $table->string('city')->nullable();
            $table->string('province')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('country')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('website')->nullable();
            $table->string('tax_number')->nullable();
            $table->string('authorized_signatory_name')->nullable();
            $table->string('authorized_signatory_title')->nullable();
            $table->string('signature_path', 500)->nullable();
            $table->text('default_payment_terms')->nullable();
            $table->string('updated_at', 35)->nullable();
        });

        Schema::create('bank_accounts', function (Blueprint $table): void {
            $table->id();
            $table->string('account_name');
            $table->string('bank_name');
            $table->string('account_holder')->nullable();
            $table->string('account_number')->nullable();
            $table->string('transit_number')->nullable();
            $table->string('institution_number')->nullable();
            $table->string('swift_code')->nullable();
            $table->string('iban')->nullable();
            $table->string('currency', 10)->default('CAD');
            $table->text('payment_instructions')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('active')->default(true);
            $table->string('created_at', 35)->nullable();
            $table->string('updated_at', 35)->nullable();
        });

        Schema::table('invoices', function (Blueprint $table): void {
            $table->foreignId('bank_account_id')->nullable()->after('package_id')->constrained('bank_accounts')->nullOnDelete();
            $table->string('currency', 10)->default('CAD')->after('bank_account_id');
            $table->string('purchase_order_number')->nullable()->after('currency');
            $table->string('milestone_title')->nullable()->after('purchase_order_number');
            $table->text('milestone_description')->nullable()->after('milestone_title');
            $table->text('payment_terms')->nullable()->after('milestone_description');
        });

        $company = (array) config('quote_studio.company', []);
        $address = trim((string) ($company['address'] ?? ''));
        DB::table('invoice_profiles')->insert([
            'agency_name'=>(string) ($company['name'] ?? '360 Creative Agency'),
            'address_line_1'=>$address !== '' ? $address : null,
            'email'=>$company['email'] ?? null,
            'phone'=>$company['phone'] ?? null,
            'website'=>$company['website'] ?? null,
            'country'=>'Canada',
            'default_payment_terms'=>'Payment is due by the date shown. Please include the invoice number with your transfer.',
            'updated_at'=>date('c'),
        ]);
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('bank_account_id');
            $table->dropColumn(['currency', 'purchase_order_number', 'milestone_title', 'milestone_description', 'payment_terms']);
        });
        Schema::dropIfExists('bank_accounts');
        Schema::dropIfExists('invoice_profiles');
    }
};
