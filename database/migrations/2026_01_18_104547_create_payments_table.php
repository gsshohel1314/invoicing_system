<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePaymentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vts_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->date('payment_date');
            $table->enum('method', ['bank','bkash','nagad','cash']);
            $table->string('reference')->nullable()->comment('Txn ID or Reference Number');
            $table->enum('status', ['pending','success','failed'])->default('pending');
            $table->text('notes')->nullable();
            $table->json('props')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('vts_account_id');
            $table->index('invoice_id');
            $table->index('payment_date');
            $table->index(['status', 'payment_date']);
            $table->index(['vts_account_id', 'payment_date']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('payments');
    }
}
