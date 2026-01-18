<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInvoiceItemsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            // $table->foreignId('subscription_id')->constrained()->restrictOnDelete();
            $table->integer('subscription_id');
            // $table->foreignId('vts_id')->constrained()->restrictOnDelete();
            $table->integer('vts_id');

            $table->date('cycle_start');
            $table->date('cycle_end');

            $table->boolean('is_prorated')->default(false);
            $table->integer('prorated_days')->nullable();

            $table->integer('quantity')->nullable()->default(0);
            $table->decimal('unit_price', 10, 2);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('amount', 10, 2);

            $table->string('description')->nullable();

            $table->json('props')->nullable();

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
        Schema::dropIfExists('invoice_items');
    }
}
