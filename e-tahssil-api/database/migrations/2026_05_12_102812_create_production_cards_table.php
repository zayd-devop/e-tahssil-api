<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('production_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // Lien avec le greffier/employé
            $table->string('employee_name');
            $table->string('section');
            $table->string('registre')->nullable();
            $table->json('selected_actions')->nullable(); // Stocke le tableau des tâches

            // Statistiques numériques
            $table->integer('dossiers_notifies')->nullable();
            $table->integer('dossiers_executes')->nullable();
            $table->decimal('montant_recouvre', 15, 2)->nullable();

            // PVs et Contraintes
            $table->boolean('pv_positif')->default(false);
            $table->integer('pv_positif_count')->nullable();
            $table->boolean('pv_negatif')->default(false);
            $table->integer('pv_negatif_count')->nullable();
            $table->integer('contrainte')->nullable();

            // Annulations et Délégations
            $table->integer('dossiers_annulation')->nullable();
            $table->integer('dossiers_iskatat')->nullable();
            $table->decimal('montant_delegations', 15, 2)->nullable();

            // Recouvrement Personnes/Sociétés
            $table->boolean('contre_personnes')->default(false);
            $table->decimal('montant_personnes', 15, 2)->nullable();
            $table->boolean('contre_societes')->default(false);
            $table->decimal('montant_societes', 15, 2)->nullable();

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('production_cards');
    }
};
