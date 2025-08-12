<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class BookingSlot extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id',
        'start_time',
        'end_time',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
    ];

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function overlapsWith(BookingSlot $other): bool
    {
        return $this->start_time < $other->end_time && $this->end_time > $other->start_time;
    }

    public function overlapsWithTime(Carbon $startTime, Carbon $endTime): bool
    {
        return $this->start_time < $endTime && $this->end_time > $startTime;
    }

    public function isValidTimeSlot(): bool
    {
        return $this->start_time < $this->end_time;
    }
}
