<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('external_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('internal_reference')->unique(); // ERP internal ref
            $table->string('external_reference')->nullable()->index(); // provider reference
            $table->foreignId('api_integration_id')->nullable()->constrained('api_integrations')->nullOnDelete();
            $table->string('provider');                // orange_money|moov_money|bank|etc
            $table->string('type');                    // payment|refund|withdrawal|transfer
            $table->decimal('amount', 15, 2);
            $table->string('currency', 3)->default('XOF');
            $table->string('status')->default('pending'); // pending|confirmed|failed|cancelled
            $table->string('phone_number')->nullable();
            $table->json('provider_data')->nullable();  // raw provider response
            // Relations
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->unsignedBigInteger('client_payment_id')->nullable(); // linked ERP payment
            $table->string('direction')->default('inbound'); // inbound|outbound
            $table->text('notes')->nullable();
            $table->timestamp('transacted_at')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('external_transactions'); }
};
