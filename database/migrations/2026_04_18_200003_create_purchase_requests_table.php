<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_requests', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('purchase_order_id')->nullable()->constrained('purchase_orders')->nullOnDelete();

            $table->string('number', 30)->unique();
            $table->string('department')->nullable();
            $table->string('justification')->nullable();

            $table->enum('status', [
                'brouillon',
                'soumis',
                'approuve',
                'rejete',
                'converti',
                'annule',
            ])->default('brouillon');

            $table->date('needed_at')->nullable();
            $table->text('notes')->nullable();
            $table->text('rejection_reason')->nullable();

            $table->decimal('total_estimated', 15, 0)->default(0);

            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['requested_by', 'status', 'needed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_requests');
    }
};
