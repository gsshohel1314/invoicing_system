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
            $table->foreignId('vts_id')->constrained()->cascadeOnDelete();

            // billing config
            // $table->enum('bill_type', ['prepaid', 'postpaid']);
            $table->decimal('monthly_fee', 10, 2);
            $table->decimal('actual_monthly_fee', 10, 2)->nullable();
            $table->integer('billing_cycle_days')->default(30);
            
            // Lifecycle / billing schedule
            $table->date('billing_start_date')->nullable();
            $table->date('billing_end_date')->nullable();
            $table->date('next_billing_date')->nullable();

            // financial snapshot
            $table->decimal('current_balance', 10, 2)->default(0);
            $table->foreignId('last_invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->date('last_pay_date')->nullable();
            
            // Status for this device's billing
            $table->enum('status', ['active', 'suspended', 'cancelled'])->default('active');
            $table->timestamps();

            $table->unique('vts_id');
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
