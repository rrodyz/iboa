<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('api_integrations', function (Blueprint $table) {
            $table->id();
            $table->string('name');                    // "Orange Money Burkina"
            $table->string('slug')->unique();          // "orange-money-bf"
            $table->string('type');                    // payment|sms|email|bank|ecommerce|fiscal
            $table->string('provider');                // orange_money|moov_money|nexah|twilio|etc
            $table->string('base_url')->nullable();
            $table->text('api_key')->nullable();       // encrypted
            $table->text('secret_key')->nullable();    // encrypted
            $table->text('client_id')->nullable();     // encrypted
            $table->text('client_secret')->nullable(); // encrypted
            $table->text('token')->nullable();          // encrypted
            $table->text('webhook_secret')->nullable(); // encrypted
            $table->json('extra_config')->nullable();   // {merchant_id, callback_url, etc.}
            $table->enum('mode', ['sandbox', 'production'])->default('sandbox');
            $table->boolean('is_active')->default(false);
            $table->string('status')->default('unconfigured'); // unconfigured|ok|error
            $table->text('last_error')->nullable();
            $table->timestamp('last_tested_at')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('api_integrations'); }
};
