<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('costing_kitchen_rentals', function (Blueprint $table) {
            $table->id();
            $table->string('booking_title');
            $table->string('booked_for')->nullable();
            $table->string('status')->nullable();
            $table->string('venue_name')->nullable();
            $table->string('space_name');
            $table->json('equipment')->nullable();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->decimal('booking_length', 4, 1)->nullable();
            // Calendar day the slot falls on, from the Space row's Start
            // Time -- not derivable as a stable upsert key from starts_at
            // alone, since FoodCorridor's Equipment rows in the same real
            // booking can carry a narrower/offset time range than the Space
            // row (e.g. space books 08:00-15:00, an attached blast chiller
            // books 08:00-13:00). Grouping/upserting by exact start+end
            // would then see them as different slots.
            $table->date('booking_date');
            // nullOnDelete, not cascade -- deleting a production run should
            // unlink the rental slot, not erase the real-world booking data
            // it came from.
            $table->foreignId('production_run_id')->nullable()->constrained('costing_production_runs')->nullOnDelete();
            $table->timestamps();

            // Natural key from the FoodCorridor export, used to upsert on
            // re-import instead of duplicating rows every time the CSV is
            // re-downloaded.
            $table->unique(['booking_title', 'venue_name', 'booking_date'], 'costing_kitchen_rentals_slot_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('costing_kitchen_rentals');
    }
};
