<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('gestioni', function (Blueprint $table) {
            $table->boolean('saldo_applicato')->default(false)->after('attiva');
            $table->text('nota_saldo')->nullable()->after('saldo_applicato');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gestioni', function (Blueprint $table) {
            $table->dropColumn(['saldo_applicato', 'nota_saldo']);
        });
    }
};
