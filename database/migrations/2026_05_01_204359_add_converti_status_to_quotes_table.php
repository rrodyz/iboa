<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // SQLite stores status as plain text and does not enforce ENUMs — skip.
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        // Add 'converti' value to quotes.status ENUM
        DB::statement("ALTER TABLE quotes MODIFY COLUMN status ENUM('brouillon','envoye','accepte','refuse','expire','annule','converti') NOT NULL DEFAULT 'brouillon'");
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        // Revert any converted quotes to 'accepte' before removing the value
        DB::table('quotes')->where('status', 'converti')->update(['status' => 'accepte']);
        DB::statement("ALTER TABLE quotes MODIFY COLUMN status ENUM('brouillon','envoye','accepte','refuse','expire','annule') NOT NULL DEFAULT 'brouillon'");
    }
};
