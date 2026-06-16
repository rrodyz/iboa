<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coils', function (Blueprint $table) {
            $table->foreignId('reception_id')->nullable()->after('supplier_id')
                ->constrained('receptions')->nullOnDelete();
            $table->index('reception_id');
        });
    }

    public function down(): void
    {
        Schema::table('coils', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reception_id');
        });
    }
};
