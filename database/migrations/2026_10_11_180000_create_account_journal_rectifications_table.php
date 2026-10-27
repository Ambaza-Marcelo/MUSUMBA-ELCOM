<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAccountJournalRectificationsTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('account_journal_rectifications')) {
            return;
        }

        Schema::create('account_journal_rectifications', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('journal_entry_detail_id');
            $table->unsignedBigInteger('source_journal_entry_detail_id')->nullable();
            $table->string('module', 50);
            $table->string('document_no')->nullable();
            $table->unsignedBigInteger('old_account_id');
            $table->unsignedBigInteger('new_account_id');
            $table->decimal('amount', 20, 2);
            $table->string('reason')->nullable();
            $table->string('rectified_by')->nullable();
            $table->timestamps();

            $table->index('document_no');
            $table->index('journal_entry_detail_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('account_journal_rectifications');
    }
}
