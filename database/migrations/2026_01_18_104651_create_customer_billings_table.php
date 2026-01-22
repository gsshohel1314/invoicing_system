<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCustomerBillingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('customer_billings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vts_account_id')->constrained()->cascadeOnDelete()->unique();

            $table->enum('bill_type', ['prepaid', 'postpaid']);
            $table->unsignedTinyInteger('invoice_generation_day')->nullable()->default(1);
            $table->enum('billing_mode', ['calendar', 'activation'])->default('calendar');

            $table->decimal('current_balance', 10, 2)->default(0);
            $table->foreignId('last_invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->date('last_pay_date')->nullable();

            $table->tinyInteger('status')->default(1)->comment('1 = active, 0 = inactive');
            $table->timestamps();

            // add index
            $table->index(['billing_mode', 'bill_type', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('customer_billings');
    }
}
