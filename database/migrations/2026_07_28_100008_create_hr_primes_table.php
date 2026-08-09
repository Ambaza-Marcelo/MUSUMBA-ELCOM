<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateHrPrimesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('hr_primes', function (Blueprint $table) {
            $table->id();
            $table->string('pourcentage_prime')->nullable(true);
            $table->string('somme_prime')->nullable(true);
            $table->string('created_by')->nullable(true);
            $table->bigInteger('type_prime_id')->unsigned()->nullable(true);
            $table->foreign('type_prime_id')
                    ->references('id')
                    ->on('hr_type_primes')
                    ->onUpdate('cascade')
                    ->onDelete('cascade');
            $table->bigInteger('employe_id')->unsigned()->nullable(true);
            $table->foreign('employe_id')
                    ->references('id')
                    ->on('hr_employes')
                    ->onUpdate('cascade')
                    ->onDelete('cascade');
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
        Schema::dropIfExists('hr_primes');
    }
}
