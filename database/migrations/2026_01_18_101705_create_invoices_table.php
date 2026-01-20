<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInvoicesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vts_account_id')->constrained()->cascadeOnDelete()->index();
            $table->string('invoice_number')->unique()->index();
            $table->date('issued_date')->nullable()->index();
            $table->date('due_date')->nullable()->index();
            $table->decimal('subtotal', 10, 2);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('total_amount', 10, 2);
            $table->decimal('paid_amount', 10, 2)->default(0);
            $table->decimal('due_amount', 10, 2)->virtual()->storedAs('total_amount - paid_amount');
            $table->enum('status', ['draft', 'unpaid', 'partially_paid', 'paid', 'overdue', 'cancelled'])->default('draft')->index();
            $table->boolean('is_consolidated')->default(true); // is multiple device invoice or not 
            $table->text('notes')->nullable();
            $table->string('pdf_path')->nullable();
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
        Schema::dropIfExists('invoices');
    }
}
