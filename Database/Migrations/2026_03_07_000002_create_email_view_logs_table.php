<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateEmailViewLogsTable extends Migration
{
    public function up()
    {
        Schema::create('email_view_logs', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('conversation_id');
            $table->integer('duration_seconds')->default(0);
            $table->timestamp('viewed_at')->nullable();
            $table->index(['user_id', 'viewed_at']);
            $table->index('conversation_id');
            $table->index('viewed_at');
        });
    }

    public function down()
    {
        Schema::dropIfExists('email_view_logs');
    }
}
