<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateVtsBillingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('vts_billings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vts_id')->constrained()->cascadeOnDelete()->unique();

            $table->decimal('monthly_fee', 10, 2);
            $table->decimal('actual_monthly_fee', 10, 2)->nullable();
            
            $table->date('service_start_date')->nullable();
            $table->date('service_expiry_date')->nullable();
            $table->date('next_billing_date')->nullable();

            $table->decimal('current_balance', 10, 2)->default(0);
            $table->foreignId('last_invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->date('last_pay_date')->nullable();
            
            $table->enum('status', ['active', 'suspended', 'cancelled'])->default('active');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('vts_billings');
    }
}
