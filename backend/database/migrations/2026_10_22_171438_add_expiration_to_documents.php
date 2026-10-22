<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->date('expires_on')->nullable();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE documents ADD CONSTRAINT documents_expiration_range CHECK (expires_on BETWEEN DATE '1900-01-01' AND DATE '2100-12-31')");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE documents DROP CONSTRAINT documents_expiration_range');
        }
        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn('expires_on');
        });
    }
};
