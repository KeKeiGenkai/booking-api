<?php

namespace Database\Factories;

use App\Models\BookingSlot;
use App\Models\Booking;
use Illuminate\Database\Eloquent\Factories\Factory;
use Carbon\Carbon;

class BookingSlotFactory extends Factory
{
    protected $model = BookingSlot::class;

    public function definition(): array
    {
        $startTime = Carbon::tomorrow()->addHours(fake()->numberBetween(8, 18));
        $endTime = $startTime->copy()->addHour();

        return [
            'booking_id' => Booking::factory(),
            'start_time' => $startTime,
            'end_time' => $endTime,
        ];
    }
}
