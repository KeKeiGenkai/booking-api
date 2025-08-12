<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\BookingSlot;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

class BookingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $bookings = $request->user()->bookings()->with('slots')->get();
        
        return response()->json([
            'data' => $bookings
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'slots' => 'required|array|min:1',
            'slots.*.start_time' => 'required|date|after:now',
            'slots.*.end_time' => 'required|date|after:slots.*.start_time',
        ]);

        try {
            return DB::transaction(function () use ($request) {
                $booking = $request->user()->bookings()->create();
                
                $slotsData = $request->input('slots');
                
                $this->validateSlotsWithinBooking($slotsData);
                
                $this->validateSlotsConflicts($slotsData);
                
                foreach ($slotsData as $slotData) {
                    $booking->slots()->create([
                        'start_time' => $slotData['start_time'],
                        'end_time' => $slotData['end_time'],
                    ]);
                }
                
                $booking->load('slots');
                
                return response()->json([
                    'message' => 'Бронирование успешно создано',
                    'data' => $booking
                ], 201);
            });
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Ошибка валидации',
                'errors' => $e->errors()
            ], 422);
        }
    }

    public function updateSlot(Request $request, Booking $booking, BookingSlot $slot): JsonResponse
    {
        if ($booking->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Нет доступа к данному бронированию'
            ], 403);
        }

        if ($slot->booking_id !== $booking->id) {
            return response()->json([
                'message' => 'Слот не принадлежит данному бронированию'
            ], 400);
        }

        $request->validate([
            'start_time' => 'required|date|after:now',
            'end_time' => 'required|date|after:start_time',
        ]);

        try {
            return DB::transaction(function () use ($request, $booking, $slot) {
                $newStartTime = Carbon::parse($request->input('start_time'));
                $newEndTime = Carbon::parse($request->input('end_time'));
                
                $this->validateSlotConflictWithinBooking($booking, $slot->id, $newStartTime, $newEndTime);
                
                $this->validateSlotConflictWithSystem($slot->id, $newStartTime, $newEndTime);
                
                $slot->update([
                    'start_time' => $newStartTime,
                    'end_time' => $newEndTime,
                ]);
                
                $booking->load('slots');
                
                return response()->json([
                    'message' => 'Слот успешно обновлен',
                    'data' => $booking
                ]);
            });
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Ошибка валидации',
                'errors' => $e->errors()
            ], 422);
        }
    }

    public function addSlot(Request $request, Booking $booking): JsonResponse
    {
        if ($booking->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Нет доступа к данному бронированию'
            ], 403);
        }

        $request->validate([
            'start_time' => 'required|date|after:now',
            'end_time' => 'required|date|after:start_time',
        ]);

        try {
            return DB::transaction(function () use ($request, $booking) {
                $newStartTime = Carbon::parse($request->input('start_time'));
                $newEndTime = Carbon::parse($request->input('end_time'));
                
                $this->validateSlotConflictWithinBooking($booking, null, $newStartTime, $newEndTime);
                
                $this->validateSlotConflictWithSystem(null, $newStartTime, $newEndTime);
                
                $booking->slots()->create([
                    'start_time' => $newStartTime,
                    'end_time' => $newEndTime,
                ]);
                
                $booking->load('slots');
                
                return response()->json([
                    'message' => 'Слот успешно добавлен',
                    'data' => $booking
                ]);
            });
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Ошибка валидации',
                'errors' => $e->errors()
            ], 422);
        }
    }

    public function destroy(Request $request, Booking $booking): JsonResponse
    {
        if ($booking->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Нет доступа к данному бронированию'
            ], 403);
        }

        $booking->delete();

        return response()->json([
            'message' => 'Бронирование успешно удалено'
        ]);
    }

    private function validateSlotsWithinBooking(array $slots): void
    {
        for ($i = 0; $i < count($slots); $i++) {
            for ($j = $i + 1; $j < count($slots); $j++) {
                $slot1Start = Carbon::parse($slots[$i]['start_time']);
                $slot1End = Carbon::parse($slots[$i]['end_time']);
                $slot2Start = Carbon::parse($slots[$j]['start_time']);
                $slot2End = Carbon::parse($slots[$j]['end_time']);

                if ($slot1Start < $slot2End && $slot1End > $slot2Start) {
                    throw ValidationException::withMessages([
                        'slots' => ['Слоты не должны пересекаться внутри одного заказа']
                    ]);
                }
            }
        }
    }

    private function validateSlotsConflicts(array $slots): void
    {
        foreach ($slots as $slotData) {
            $startTime = Carbon::parse($slotData['start_time']);
            $endTime = Carbon::parse($slotData['end_time']);
            
            $conflictingSlots = BookingSlot::where(function ($query) use ($startTime, $endTime) {
                $query->where('start_time', '<', $endTime)
                      ->where('end_time', '>', $startTime);
            })->exists();

            if ($conflictingSlots) {
                throw ValidationException::withMessages([
                    'slots' => ['Один или несколько слотов пересекаются с существующими бронированиями']
                ]);
            }
        }
    }

    private function validateSlotConflictWithinBooking(Booking $booking, ?int $excludeSlotId, Carbon $startTime, Carbon $endTime): void
    {
        $query = $booking->slots();
        
        if ($excludeSlotId) {
            $query->where('id', '!=', $excludeSlotId);
        }
        
        $conflictingSlots = $query->where(function ($query) use ($startTime, $endTime) {
            $query->where('start_time', '<', $endTime)
                  ->where('end_time', '>', $startTime);
        })->exists();

        if ($conflictingSlots) {
            throw ValidationException::withMessages([
                'start_time' => ['Слот пересекается с другими слотами в этом бронировании']
            ]);
        }
    }

    private function validateSlotConflictWithSystem(?int $excludeSlotId, Carbon $startTime, Carbon $endTime): void
    {
        $query = BookingSlot::query();
        
        if ($excludeSlotId) {
            $query->where('id', '!=', $excludeSlotId);
        }
        
        $conflictingSlots = $query->where(function ($query) use ($startTime, $endTime) {
            $query->where('start_time', '<', $endTime)
                  ->where('end_time', '>', $startTime);
        })->exists();

        if ($conflictingSlots) {
            throw ValidationException::withMessages([
                'start_time' => ['Слот пересекается с существующими бронированиями в системе']
            ]);
        }
    }
}
