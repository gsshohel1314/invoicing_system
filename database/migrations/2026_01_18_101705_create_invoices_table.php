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
            $table->foreignId('vts_account_id')->constrained()->cascadeOnDelete();
            $table->string('invoice_number')->nullable()->unique();

            $table->char('billing_month', 7)->nullable(); // YYYY-MM
            $table->date('billing_period_start')->nullable();
            $table->date('billing_period_end')->nullable();

            $table->date('issued_date')->nullable();
            $table->date('due_date')->nullable();
            $table->decimal('subtotal', 10, 2);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('total_amount', 10, 2);
            $table->decimal('paid_amount', 10, 2)->default(0);
            $table->decimal('due_amount', 10, 2)->virtual()->storedAs('total_amount - paid_amount');
            $table->enum('status', ['draft', 'unpaid', 'partially_paid', 'paid', 'overdue', 'cancelled'])->default('draft');
            $table->boolean('is_consolidated')->default(true); // is multiple device invoice or not

            $table->boolean('is_advance_billed')->default(false);
            $table->timestamp('sent_at')->nullable(); // notification sent
            $table->tinyInteger('reminder_sent_count')->unsigned()->default(0); // reminder count
            $table->enum('generated_by', ['cron', 'manual'])->default('cron');

            $table->text('notes')->nullable();
            $table->string('pdf_path')->nullable();
            $table->json('props')->nullable();
            $table->timestamps();

            // add index
            $table->index('vts_account_id');
            $table->index('status');
            $table->index('issued_date');
            $table->index('due_date');
            $table->index('invoice_number');
            $table->index(['vts_account_id', 'status']);
            $table->index(['vts_account_id', 'billing_month']);
            $table->index(['vts_account_id', 'issued_date']);
            $table->index(['status', 'due_date']);
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
