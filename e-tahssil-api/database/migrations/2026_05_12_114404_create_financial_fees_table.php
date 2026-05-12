<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('financial_fees', function (Blueprint $table) {
            $table->id();
            // نوع الملف: 'complementary' (رسوم تكميلية) أو 'legal_aid' (مساعدة قضائية)
            $table->enum('type', ['complementary', 'legal_aid']);

            $table->string('registry_number')->nullable(); // رقم سجل التصفية
            $table->date('execution_order_date')->nullable(); // تاريخ الأمر التنفيذي
            $table->string('execution_order_number')->nullable(); // رقم الأمر التنفيذي
            $table->string('debtor_name'); // الإسم الكامل للمدين
            $table->decimal('judicial_fees', 10, 2)->default(0); // مبلغ الرسوم القضائية
            $table->decimal('pleading_rights', 10, 2)->default(0); // حقوق المرافعة
            $table->decimal('total_amount', 10, 2)->default(0); // المجموع (محسوب)
            $table->text('debtor_address')->nullable(); // عنوان المدين

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('financial_fees');
    }
};
