<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('api_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('api_integration_id')->nullable()->constrained('api_integrations')->nullOnDelete();
            $table->string('service');       // orange_money, sms, etc.
            $table->string('endpoint');
            $table->string('method', 10)->default('POST');
            $table->json('payload')->nullable();
            $table->json('response')->nullable();
            $table->integer('status_code')->nullable();
            $table->boolean('success')->default(false);
            $table->float('duration_ms')->nullable(); // response time in ms
            $table->string('reference')->nullable();  // external reference
            $table->string('direction')->default('outbound'); // outbound|inbound (webhooks)
            $table->text('error_message')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }
    public function down(): void { Schema::dropIfExists('api_logs'); }
};
