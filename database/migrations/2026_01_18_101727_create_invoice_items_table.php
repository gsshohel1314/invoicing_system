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
            $table->foreignId('vts_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vts_id')->constrained('vts')->cascadeOnDelete();
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->boolean('is_prorated')->default(false);
            $table->decimal('quantity', 10, 4)->default(1.0000); // Quantity in months (1.0000 = 1 month, 0.5000 = 15 days etc)
            $table->decimal('unit_price', 10, 2)->default(0);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('amount', 10, 2);
            $table->decimal('paid_amount', 10, 2)->default(0);
            $table->decimal('due_amount', 10, 2)->virtual()->storedAs('amount - paid_amount');
            $table->enum('status', ['draft', 'unpaid', 'partially_paid', 'paid', 'overdue', 'cancelled'])->default('draft');
            $table->string('description')->nullable();
            $table->json('props')->nullable();
            $table->timestamps();

            // add index
            $table->index('invoice_id');
            $table->index('vts_id');
            $table->index(['invoice_id', 'vts_id']);
            $table->index(['vts_account_id', 'invoice_id']);
            $table->index(['period_start', 'period_end']);
            $table->index('status');
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
