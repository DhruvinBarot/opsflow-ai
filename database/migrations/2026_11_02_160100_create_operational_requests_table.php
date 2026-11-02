<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Enum-like columns are stored as short strings and cast to PHP backed enums,
     * so adding a category or status never requires an ALTER on a large table.
     */
    public function up(): void
    {
        Schema::create('operational_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 160);
            $table->text('description');
            $table->string('category', 32);
            $table->string('priority', 16)->default('medium');
            $table->string('status', 24)->default('draft');
            $table->timestamp('due_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            // "My requests" list: filter by owner, newest first.
            $table->index(['created_by', 'created_at']);
            // Review queues and dashboards: filter by status, newest first.
            $table->index(['status', 'created_at']);
            // "Assigned to me" work queue, usually narrowed by status.
            $table->index(['assigned_to', 'status']);
            $table->index('priority');
            $table->index('category');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('operational_requests');
    }
};
