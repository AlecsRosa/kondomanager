<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budget_movements', function (Blueprint $table) {
            $table->id();
            
            // Relazioni Core
            $table->foreignId('piano_rate_id')->constrained('piani_rate')->onDelete('cascade');
            $table->foreignId('source_conto_id')->constrained('conti')->onDelete('cascade');
            $table->foreignId('destination_conto_id')->constrained('conti')->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete(); // Audit: Chi è stato?

            // Dati Economici
            $table->integer('amount'); // In centesimi (es. 50000 = 500€)
            
            // Snapshot Pre-Spostamento (Per Audit e Rollback sicuro)
            $table->integer('source_old_amount')->nullable()->comment('Importo pivot prima dello spostamento');
            $table->integer('destination_old_amount')->nullable()->comment('Importo pivot prima dello spostamento');

            // Metadati e Roadmap Futura
            $table->string('type')->default('reallocation'); // 'reallocation', 'treasury_fix', 'fiscal_adjustment'
            $table->string('reason')->nullable(); // Es. "Rottura Cancello Urgente"
            $table->json('metadata')->nullable(); // Per V 1.13 Alert Fiscali (es. "detraibile_warning": true)

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_movements');
    }
};