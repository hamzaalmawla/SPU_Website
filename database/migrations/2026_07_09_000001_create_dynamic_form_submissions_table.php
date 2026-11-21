<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dynamic_form_submissions', function (Blueprint $table): void {
            $table->id();
            $table->string('form_id', 120)->index();
            $table->string('locale', 5)->index();
            $table->string('applicant_name')->nullable()->index();
            $table->string('applicant_email')->nullable()->index();
            $table->string('status')->default('new')->index();
            $table->json('payload_json');
            $table->json('files_json')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index(['form_id', 'status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dynamic_form_submissions');
    }
};
