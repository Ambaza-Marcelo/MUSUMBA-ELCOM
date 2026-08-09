<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateHrGroupeCotisationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('hr_groupe_cotisations', function (Blueprint $table) {
            $table->id();
            $table->string('designation')->nullable(true);
            $table->string('classe_inferieure')->nullable(true);
            $table->string('classe_superieure')->nullable(true);
            $table->string('created_by')->nullable(true);
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
        Schema::dropIfExists('hr_groupe_cotisations');
    }
}
