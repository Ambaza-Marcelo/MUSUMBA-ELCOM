<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateHrCotisationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('hr_cotisations', function (Blueprint $table) {
            $table->id();
            $table->string('code')->nullable(true);
            $table->string('etat')->nullable(true);
            $table->string('pourcentage_sb')->nullable(true);
            $table->string('pourcentage_employeur')->nullable(true);
            $table->string('created_by')->nullable(true);
            $table->bigInteger('type_cotisation_id')->unsigned()->nullable(true);
            $table->foreign('type_cotisation_id')
                    ->references('id')
                    ->on('hr_type_cotisations')
                    ->onUpdate('cascade')
                    ->onDelete('cascade');
            $table->bigInteger('groupe_cotisation_id')->unsigned()->nullable(true);
            $table->foreign('groupe_cotisation_id')
                    ->references('id')
                    ->on('hr_groupe_cotisations')
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
        Schema::dropIfExists('hr_cotisations');
    }
}
