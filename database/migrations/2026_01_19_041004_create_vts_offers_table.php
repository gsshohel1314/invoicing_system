<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateVtsOffersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('vts_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vts_id')->constrained('vts')->cascadeOnDelete();
            $table->foreignId('offer_id')->constrained('offers')->restrictOnDelete();
            $table->date('applied_from');
            $table->date('applied_to')->nullable();
            $table->enum('status', ['active','expired', 'cancelled'])->default('active');
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
        Schema::dropIfExists('vts_offers');
    }
}
