<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Booking;
use App\Models\BookingSlot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Carbon\Carbon;

class BookingApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private string $apiToken;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create([
            'name' => 'Test User',
            'api_token' => 'test-api-token',
        ]);
        
        $this->apiToken = 'test-api-token';
    }

    /** @test */
    public function test_unauthorized_request_is_rejected()
    {
        $response = $this->getJson('/api/bookings');
        
        $response->assertStatus(401)
                ->assertJson([
                    'message' => 'API токен не предоставлен'
                ]);
    }

    /** @test */
    public function test_invalid_token_is_rejected()
    {
        $response = $this->getJson('/api/bookings', [
            'Authorization' => 'Bearer invalid-token'
        ]);
        
        $response->assertStatus(401)
                ->assertJson([
                    'message' => 'Неверный API токен'
                ]);
    }

    /** @test */
    public function test_can_create_booking_with_multiple_slots()
    {
        $tomorrow = Carbon::tomorrow();
        
        $data = [
            'slots' => [
                [
                    'start_time' => $tomorrow->copy()->setTime(12, 0)->toISOString(),
                    'end_time' => $tomorrow->copy()->setTime(13, 0)->toISOString(),
                ],
                [
                    'start_time' => $tomorrow->copy()->setTime(14, 0)->toISOString(),
                    'end_time' => $tomorrow->copy()->setTime(15, 0)->toISOString(),
                ],
            ]
        ];

        $response = $this->postJson('/api/bookings', $data, [
            'Authorization' => 'Bearer ' . $this->apiToken
        ]);

        $response->assertStatus(201)
                ->assertJson([
                    'message' => 'Бронирование успешно создано'
                ])
                ->assertJsonStructure([
                    'data' => [
                        'id',
                        'user_id',
                        'slots' => [
                            '*' => ['id', 'start_time', 'end_time']
                        ]
                    ]
                ]);

        $this->assertDatabaseHas('bookings', [
            'user_id' => $this->user->id
        ]);

        $this->assertDatabaseCount('booking_slots', 2);
    }

    /** @test */
    public function test_cannot_create_overlapping_slots_within_booking()
    {
        $tomorrow = Carbon::tomorrow();
        
        $data = [
            'slots' => [
                [
                    'start_time' => $tomorrow->copy()->setTime(12, 0)->toISOString(),
                    'end_time' => $tomorrow->copy()->setTime(13, 0)->toISOString(),
                ],
                [
                    'start_time' => $tomorrow->copy()->setTime(12, 30)->toISOString(),
                    'end_time' => $tomorrow->copy()->setTime(13, 30)->toISOString(),
                ],
            ]
        ];

        $response = $this->postJson('/api/bookings', $data, [
            'Authorization' => 'Bearer ' . $this->apiToken
        ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['slots']);
    }

    /** @test */
    public function test_cannot_add_slot_with_conflict()
    {
        $tomorrow = Carbon::tomorrow();
        $startTime = $tomorrow->copy()->setTime(12, 0);
        $endTime = $tomorrow->copy()->setTime(13, 0);
        
        $existingBooking = Booking::factory()->create(['user_id' => $this->user->id]);
        BookingSlot::factory()->create([
            'booking_id' => $existingBooking->id,
            'start_time' => $startTime,
            'end_time' => $endTime,
        ]);

        $newBooking = Booking::factory()->create(['user_id' => $this->user->id]);

        $data = [
            'start_time' => $startTime->copy()->addMinutes(30)->toISOString(),
            'end_time' => $endTime->copy()->addMinutes(30)->toISOString(),
        ];

        $response = $this->postJson("/api/bookings/{$newBooking->id}/slots", $data, [
            'Authorization' => 'Bearer ' . $this->apiToken
        ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['start_time']);
    }

    /** @test */
    public function test_can_update_slot_successfully()
    {
        $tomorrow = Carbon::tomorrow();
        
        $booking = Booking::factory()->create(['user_id' => $this->user->id]);
        $slot = BookingSlot::factory()->create([
            'booking_id' => $booking->id,
            'start_time' => $tomorrow->copy()->setTime(12, 0),
            'end_time' => $tomorrow->copy()->setTime(13, 0),
        ]);

        $data = [
            'start_time' => $tomorrow->copy()->setTime(14, 0)->toISOString(),
            'end_time' => $tomorrow->copy()->setTime(15, 0)->toISOString(),
        ];

        $response = $this->patchJson("/api/bookings/{$booking->id}/slots/{$slot->id}", $data, [
            'Authorization' => 'Bearer ' . $this->apiToken
        ]);

        $response->assertStatus(200)
                ->assertJson([
                    'message' => 'Слот успешно обновлен'
                ]);

        $slot->refresh();
        $this->assertEquals($tomorrow->copy()->setTime(14, 0), $slot->start_time);
        $this->assertEquals($tomorrow->copy()->setTime(15, 0), $slot->end_time);
    }

    /** @test */
    public function test_cannot_update_other_users_booking()
    {
        $otherUser = User::factory()->create(['api_token' => 'other-token']);
        $booking = Booking::factory()->create(['user_id' => $otherUser->id]);
        $slot = BookingSlot::factory()->create(['booking_id' => $booking->id]);

        $data = [
            'start_time' => Carbon::tomorrow()->setTime(14, 0)->toISOString(),
            'end_time' => Carbon::tomorrow()->setTime(15, 0)->toISOString(),
        ];

        $response = $this->patchJson("/api/bookings/{$booking->id}/slots/{$slot->id}", $data, [
            'Authorization' => 'Bearer ' . $this->apiToken
        ]);

        $response->assertStatus(403)
                ->assertJson([
                    'message' => 'Нет доступа к данному бронированию'
                ]);
    }

    /** @test */
    public function test_can_delete_booking()
    {
        $booking = Booking::factory()->create(['user_id' => $this->user->id]);
        BookingSlot::factory()->create(['booking_id' => $booking->id]);

        $response = $this->deleteJson("/api/bookings/{$booking->id}", [], [
            'Authorization' => 'Bearer ' . $this->apiToken
        ]);

        $response->assertStatus(200)
                ->assertJson([
                    'message' => 'Бронирование успешно удалено'
                ]);

        $this->assertDatabaseMissing('bookings', ['id' => $booking->id]);
        $this->assertDatabaseCount('booking_slots', 0);
    }

    /** @test */
    public function test_cannot_delete_other_users_booking()
    {
        $otherUser = User::factory()->create(['api_token' => 'other-token']);
        $booking = Booking::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->deleteJson("/api/bookings/{$booking->id}", [], [
            'Authorization' => 'Bearer ' . $this->apiToken
        ]);

        $response->assertStatus(403)
                ->assertJson([
                    'message' => 'Нет доступа к данному бронированию'
                ]);
    }

    /** @test */
    public function test_can_get_user_bookings()
    {
        $booking = Booking::factory()->create(['user_id' => $this->user->id]);
        BookingSlot::factory()->create(['booking_id' => $booking->id]);

        $otherUser = User::factory()->create(['api_token' => 'other-token']);
        $otherBooking = Booking::factory()->create(['user_id' => $otherUser->id]);
        BookingSlot::factory()->create(['booking_id' => $otherBooking->id]);

        $response = $this->getJson('/api/bookings', [
            'Authorization' => 'Bearer ' . $this->apiToken
        ]);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'data' => [
                        '*' => [
                            'id',
                            'user_id',
                            'slots' => [
                                '*' => ['id', 'start_time', 'end_time']
                            ]
                        ]
                    ]
                ]);

        $responseData = $response->json()['data'];
        $responseData = $response->json()['data'];
        $this->assertCount(1, $responseData);
        $this->assertEquals($this->user->id, $responseData[0]['user_id']);
    }

    /** @test */
    public function test_cannot_create_booking_with_past_time()
    {
        $data = [
            'slots' => [
                [
                    'start_time' => Carbon::yesterday()->toISOString(),
                    'end_time' => Carbon::yesterday()->addHour()->toISOString(),
                ]
            ]
        ];

        $response = $this->postJson('/api/bookings', $data, [
            'Authorization' => 'Bearer ' . $this->apiToken
        ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['slots.0.start_time']);
    }

    /** @test */
    public function test_cannot_create_booking_with_invalid_time_range()
    {
        $tomorrow = Carbon::tomorrow();
        
        $data = [
            'slots' => [
                [
                    'start_time' => $tomorrow->copy()->setTime(13, 0)->toISOString(),
                    'end_time' => $tomorrow->copy()->setTime(12, 0)->toISOString(),
                ]
            ]
        ];

        $response = $this->postJson('/api/bookings', $data, [
            'Authorization' => 'Bearer ' . $this->apiToken
        ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['slots.0.end_time']);
    }
}
