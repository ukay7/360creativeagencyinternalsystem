<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_followups', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lead_id')->constrained('leads')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->string('type');
            $table->string('outcome')->nullable();
            $table->string('subject')->nullable();
            $table->text('notes');
            $table->string('followed_up_at', 35);
            $table->string('next_follow_up_at', 35)->nullable();
            $table->string('created_at', 35)->nullable();
            $table->index(['lead_id', 'followed_up_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_followups');
    }
};
