<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateHrImpotsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('hr_impots', function (Blueprint $table) {
            $table->id();
            $table->string('pourcentage_impot')->nullable(true);
            $table->string('created_by')->nullable(true);
            $table->bigInteger('groupe_impot_id')->unsigned()->nullable(true);
            $table->foreign('groupe_impot_id')
                    ->references('id')
                    ->on('hr_groupe_impots')
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
        Schema::dropIfExists('hr_impots');
    }
}
