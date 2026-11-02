<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * One analysis per request: the unique key is what makes repeated job runs idempotent.
     */
    public function up(): void
    {
        Schema::create('ai_analyses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operational_request_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('status', 16)->default('pending')->index();
            $table->string('provider', 32)->nullable();
            $table->string('category', 32)->nullable();
            $table->string('priority', 16)->nullable();
            $table->string('risk_level', 16)->nullable();
            $table->string('suggested_team', 32)->nullable();
            $table->string('summary', 500)->nullable();
            $table->string('suggested_action', 500)->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            // A stable machine code (e.g. provider_timeout), never a raw provider message.
            $table->string('error_code', 64)->nullable();
            $table->timestamp('analyzed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_analyses');
    }
};
