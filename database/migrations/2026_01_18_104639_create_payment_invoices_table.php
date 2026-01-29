<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePaymentInvoicesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('payment_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vts_account_id')->nullable()->constrained()->cascadeOnDelete();
            $table->decimal('allocated_amount', 10, 2);
            $table->decimal('total_amount', 10, 2)->nullable();
            $table->json('props')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('payment_id');
            $table->index('invoice_id');
            $table->index('vts_account_id');
            $table->index(['payment_id', 'invoice_id']);
            $table->index(['vts_account_id', 'invoice_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('payment_invoices');
    }
}
