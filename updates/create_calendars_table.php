<?php namespace JumpLink\Events\Updates;

use Schema;
use Winter\Storm\Database\Updates\Migration;

class CreateCalendarsTable extends Migration
{
    public function up()
    {
        Schema::create('jumplink_events_calendars', function ($table) {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->string('name');                 // Kurz-Identifier, z.B. "Watt"
            $table->string('title')->nullable();
            $table->string('subtitle')->nullable();
            $table->text('description')->nullable();
            $table->text('note')->nullable();
            $table->string('color', 32)->nullable(); // Hex-Farbe für Frontend-Akzent
            $table->string('css_class')->nullable();  // optionale Bootstrap-State-Klasse
            $table->string('type')->default('events');
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->nullable();
            $table->string('firestore_id')->nullable()->index(); // Import-Idempotenz
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('jumplink_events_calendars');
    }
}
