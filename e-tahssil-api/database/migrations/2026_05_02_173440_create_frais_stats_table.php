<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
{
    Schema::create('frais_stats', function (Blueprint $table) {
        $table->id();

        // Période
        $table->string('month', 2); // Ex: '01', '12'
        $table->string('year', 4);  // Ex: '2026'

        // 1. المختصرات (Extraits)
        $table->integer('extraits_dossiers')->default(0);
        $table->decimal('extraits_montant', 15, 2)->default(0.00);

        // 2. الرسوم التكميلية (Frais)
        $table->integer('frais_dossiers')->default(0);
        $table->decimal('frais_montant', 15, 2)->default(0.00);

        // 3. المساعدة القضائية (Assistance)
        $table->integer('assist_dossiers')->default(0);
        $table->decimal('assist_montant', 15, 2)->default(0.00);

        // 4. الأوامر بالدفع (Injonctions)
        $table->integer('injonc_dossiers')->default(0);
        $table->decimal('injonc_montant', 15, 2)->default(0.00);

        // 5. السندات (Titres)
        $table->integer('titres_dossiers')->default(0);
        $table->decimal('titres_montant', 15, 2)->default(0.00);

        $table->timestamps();

        // Sécurité : Empêcher de créer deux statistiques pour le même mois/année
        $table->unique(['month', 'year']);
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('frais_stats');
    }
};
