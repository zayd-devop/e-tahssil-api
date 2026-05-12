<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // 1. جدول الشُعب (Sections)
        Schema::create('sections', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        // 2. جدول فئات الإجراءات (Categories)
        Schema::create('action_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_id')->constrained('sections')->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();
        });

        // 3. جدول الإجراءات (Actions)
        Schema::create('actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('action_category_id')->constrained('action_categories')->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('actions');
        Schema::dropIfExists('action_categories');
        Schema::dropIfExists('sections');
    }
};
