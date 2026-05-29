<?php namespace JumpLink\Events\Updates;

use Schema;
use Winter\Storm\Database\Updates\Migration;

class CreateEventsTable extends Migration
{
    public function up()
    {
        Schema::create('jumplink_events_events', function ($table) {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->integer('calendar_id')->unsigned()->nullable()->index();
            $table->string('title');
            $table->string('subtitle')->nullable();
            $table->string('slug')->index();                 // entspricht "handle"
            $table->mediumText('description')->nullable();
            $table->string('type')->default('fix');          // fix | variable
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->boolean('show_times')->default(true);
            $table->string('offer')->nullable();
            $table->string('location')->nullable();
            $table->text('equipment')->nullable();
            $table->text('note')->nullable();
            $table->string('pricetext')->nullable();
            $table->mediumText('prices')->nullable();         // jsonable: Staffelpreise
            $table->text('notifications')->nullable();        // jsonable: Empfänger
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->nullable();
            $table->string('firestore_id')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('jumplink_events_events');
    }
}
