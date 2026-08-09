<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateHrIndemnitesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('hr_indemnites', function (Blueprint $table) {
            $table->id();
            $table->string('code')->nullable(true);
            $table->string('pourcentage_sb')->nullable(true);
            $table->string('somme_indemnite')->nullable(true);
            $table->string('created_by')->nullable(true);
            $table->string('auteur')->nullable(true);
            $table->bigInteger('type_indemnite_id')->unsigned()->nullable(true);
            $table->foreign('type_indemnite_id')
                    ->references('id')
                    ->on('hr_type_indemnites')
                    ->onUpdate('cascade')
                    ->onDelete('cascade');
            $table->bigInteger('groupe_indemnite_id')->unsigned()->nullable(true);
            $table->foreign('groupe_indemnite_id')
                    ->references('id')
                    ->on('hr_groupe_indemnites')
                    ->onUpdate('cascade')
                    ->onDelete('cascade');
            $table->bigInteger('contrat_id')->unsigned()->nullable(true);
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
        Schema::dropIfExists('hr_indemnites');
    }
}
