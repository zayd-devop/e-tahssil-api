<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('correspondences', function (Blueprint $table) {
            $table->id();

            // Lien avec l'utilisateur qui a généré la lettre
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');

            // Les données de la lettre
            $table->string('registration_number')->nullable();
            $table->string('sender_from');
            $table->string('recipient_to');
            $table->json('recipient_supervisors')->nullable(); // JSON pour stocker le tableau
            $table->text('subject');
            $table->integer('attachments_count')->default(0);
            $table->text('notes')->nullable();

            // Sauvegarde de l'identité du signataire au moment de la création
            $table->string('signer_name');
            $table->string('signer_role');

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('correspondences');
    }
};
