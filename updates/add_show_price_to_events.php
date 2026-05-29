<?php namespace JumpLink\Events\Updates;

use Schema;
use Winter\Storm\Database\Updates\Migration;

class AddShowPriceToEvents extends Migration
{
    public function up()
    {
        Schema::table('jumplink_events_events', function ($table) {
            $table->boolean('show_price')->default(true)->after('pricetext');
        });
    }

    public function down()
    {
        Schema::table('jumplink_events_events', function ($table) {
            $table->dropColumn('show_price');
        });
    }
}
