<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_payment_schedules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('invoice_id');
            $table->unsignedTinyInteger('installment_number')->default(1);
            $table->date('due_date');
            $table->decimal('amount', 15, 0);
            $table->decimal('paid_amount', 15, 0)->default(0);
            $table->enum('status', ['en_attente', 'partiel', 'paye', 'annule'])->default('en_attente');
            $table->string('label', 255)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('invoice_id')->references('id')->on('invoices')->onDelete('cascade');
        });

        // Add is_acompte flag to client_payments (marks it as an advance/deposit)
        Schema::table('client_payments', function (Blueprint $table) {
            $table->boolean('is_acompte')->default(false)->after('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_payment_schedules');
        Schema::table('client_payments', function (Blueprint $table) {
            $table->dropColumn('is_acompte');
        });
    }
};
