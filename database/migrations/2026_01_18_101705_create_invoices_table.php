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

            // $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->integer('customer_id');
            $table->string('invoice_number')->unique();

            $table->char('billing_month', 6)->nullable()->comment('YYYY-MM');
            $table->date('period_start');
            $table->date('period_end');

            $table->decimal('subtotal', 10, 2);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('total_amount', 10, 2);
            $table->decimal('paid_amount', 10, 2)->default(0);
            $table->decimal('due_amount', 10, 2);

            $table->enum('status', ['draft','unpaid','paid','overdue','cancelled'])->default('draft');

            $table->date('issued_date');
            $table->date('due_date')->nullable();

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
