<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_deposits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();

            $table->string('number', 30)->unique();

            // Bank account receiving the deposit
            $table->foreignId('cash_account_id')->constrained('cash_accounts')->restrictOnDelete();

            // Optional: source caisse being emptied
            $table->foreignId('source_cash_account_id')->nullable()
                  ->constrained('cash_accounts')->nullOnDelete();

            $table->date('deposit_date');
            $table->decimal('total_amount', 15, 0)->default(0);

            $table->string('reference', 100)->nullable();
            $table->text('notes')->nullable();

            $table->enum('status', ['brouillon', 'valide'])->default('brouillon');

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('validated_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'deposit_date']);
        });

        Schema::create('bank_deposit_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_deposit_id')->constrained()->cascadeOnDelete();

            $table->enum('type', ['especes', 'cheque', 'effet', 'virement'])->default('especes');
            $table->decimal('amount', 15, 0);

            $table->string('reference', 100)->nullable();   // numéro de chèque, etc.
            $table->string('drawer', 150)->nullable();      // tireur (pour chèques/effets)
            $table->string('bank_name', 150)->nullable();   // banque tirée
            $table->date('due_date')->nullable();           // échéance pour effets

            // Optional link to a commercial effect
            $table->foreignId('commercial_effect_id')->nullable();

            $table->text('notes')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_deposit_items');
        Schema::dropIfExists('bank_deposits');
    }
};
