<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePaymentInvoiceItemsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('payment_invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('payment_invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_item_id')->constrained('invoice_items')->cascadeOnDelete();
            $table->decimal('allocated_amount', 10, 2);
            $table->decimal('total_amount', 10, 2)->nullable();
            $table->json('props')->nullable();
            $table->timestamps();

            // Indexes
            $table->index(['payment_invoice_id']);
            $table->index(['invoice_item_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('payment_invoice_items');
    }
}
