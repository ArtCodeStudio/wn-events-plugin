<?php namespace JumpLink\Events\Updates;

use Schema;
use Winter\Storm\Database\Updates\Migration;

class CreateBookingsTable extends Migration
{
    public function up()
    {
        Schema::create('jumplink_events_bookings', function ($table) {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->integer('event_id')->unsigned()->nullable()->index();
            $table->string('calendar')->nullable();
            $table->string('event_title')->nullable();
            $table->dateTime('event_date')->nullable();      // gebuchter Termin
            $table->integer('quantity')->unsigned()->default(1);
            $table->decimal('total', 10, 2)->nullable();
            $table->string('firstname');
            $table->string('lastname')->nullable();
            $table->string('email');
            $table->string('phone')->nullable();
            $table->string('street')->nullable();
            $table->string('zip')->nullable();
            $table->text('message')->nullable();
            $table->string('status')->default('new');        // new | confirmed | cancelled
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('jumplink_events_bookings');
    }
}
