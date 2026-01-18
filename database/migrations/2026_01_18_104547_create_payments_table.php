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

            // $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->integer('customer_id');

            $table->decimal('amount', 10, 2);
            $table->enum('method', ['bank','bkash','nagad','cash']);
            $table->string('reference')->nullable()->comment('Txn ID or Reference Number');

            $table->enum('status', ['pending','success','failed'])->default('pending');

            $table->text('notes')->nullable();

            $table->integer('paid_at')->nullable();

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
        Schema::dropIfExists('payments');
    }
}
