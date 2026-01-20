<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateVtsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('vts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vts_account_id')->constrained('vts_accounts')->cascadeOnDelete();
            $table->date('activation_date')->nullable();
            $table->string('imei')->unique()->nullable();
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
        Schema::dropIfExists('vts');
    }
}
