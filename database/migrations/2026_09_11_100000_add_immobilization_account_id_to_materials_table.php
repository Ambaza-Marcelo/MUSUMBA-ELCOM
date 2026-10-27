<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddImmobilizationAccountIdToMaterialsTable extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('materials')) {
            return;
        }

        Schema::table('materials', function (Blueprint $table) {
            if (!Schema::hasColumn('materials', 'immobilization_account_id')) {
                $table->unsignedBigInteger('immobilization_account_id')->nullable()->after('default_account_id');
            }
        });
    }

    public function down()
    {
        if (!Schema::hasTable('materials')) {
            return;
        }

        Schema::table('materials', function (Blueprint $table) {
            if (Schema::hasColumn('materials', 'immobilization_account_id')) {
                $table->dropColumn('immobilization_account_id');
            }
        });
    }
}
