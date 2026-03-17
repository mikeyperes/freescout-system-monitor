<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCronLogsTable extends Migration
{
    public function up()
    {
        Schema::create('cron_logs', function (Blueprint $table) {
            $table->increments('id');
            $table->string('command', 255);
            $table->string('status', 20)->default('started');
            $table->text('output')->nullable();
            $table->integer('duration_ms')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->index('command');
            $table->index('started_at');
            $table->index('status');
        });
    }

    public function down()
    {
        Schema::dropIfExists('cron_logs');
    }
}
