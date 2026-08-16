<?php

namespace Tests\Feature;

use App\Models\Hotel;
use App\Models\RoomImage;
use App\Models\RoomType;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\RefreshMongoDatabase;
use Tests\TestCase;

class AdminRoomImageUploadTest extends TestCase
{
    use RefreshMongoDatabase;

    private Hotel $hotel;

    private RoomType $roomType;

    private int $initialImageCount;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->hotel = Hotel::query()->firstOrFail();
        $this->roomType = RoomType::query()->where('hotel_id', $this->hotel->id)->firstOrFail();
        $this->initialImageCount = RoomImage::query()->count();
        Storage::fake('public');
        Sanctum::actingAs($this->manager($this->hotel->id));
    }

    public function test_manager_uploads_a_public_room_image_and_lists_its_metadata(): void
    {
        $response = $this->postJson("/api/v1/admin/room-types/{$this->roomType->id}/images", [
            'image' => $this->validImage('deluxe.png'),
            'sort_order' => 3,
        ])->assertCreated()
            ->assertJsonPath('data.sort_order', 3);

        $path = $response->json('data.path');
        $this->assertStringStartsWith("room-types/{$this->roomType->id}/", $path);
        $this->assertSame(Storage::disk('public')->url($path), $response->json('data.url'));
        Storage::disk('public')->assertExists($path);
        $this->assertDatabaseHas('room_images', ['room_type_id' => $this->roomType->id, 'path' => $path]);

        $this->getJson("/api/v1/admin/room-types/{$this->roomType->id}/images")
            ->assertOk()
            ->assertJsonFragment(['path' => $path]);
    }

    public function test_upload_rejects_invalid_mime_and_files_over_five_megabytes(): void
    {
        $this->postJson("/api/v1/admin/room-types/{$this->roomType->id}/images", [
            'image' => UploadedFile::fake()->create('not-an-image.jpg', 20, 'text/plain'),
        ])->assertUnprocessable()->assertJsonValidationErrors('image');

        $this->postJson("/api/v1/admin/room-types/{$this->roomType->id}/images", [
            'image' => UploadedFile::fake()->create('too-large.png', 5121, 'image/png'),
        ])->assertUnprocessable()->assertJsonValidationErrors('image');

        $this->assertDatabaseCount('room_images', $this->initialImageCount);
    }

    public function test_manager_cannot_upload_or_list_images_outside_their_hotel_scope(): void
    {
        $otherHotel = Hotel::query()->create([
            'slug' => 'other-upload-hotel', 'name' => 'Other Hotel', 'city' => 'Hue',
            'address' => '1 Main Street', 'checkin_time' => '15:00', 'checkout_time' => '12:00',
        ]);
        $otherType = RoomType::query()->create([
            'hotel_id' => $otherHotel->id, 'slug' => 'other-room', 'name' => 'Other Room',
            'max_adults' => 2, 'price_per_night' => 100000,
        ]);

        $this->postJson("/api/v1/admin/room-types/{$otherType->id}/images", [
            'image' => $this->validImage('outside.png'),
        ])->assertForbidden();
        $this->getJson("/api/v1/admin/room-types/{$otherType->id}/images")->assertForbidden();
        $this->assertDatabaseCount('room_images', $this->initialImageCount);
    }

    public function test_owner_updates_sort_order_and_delete_removes_the_local_file(): void
    {
        $created = $this->postJson("/api/v1/admin/room-types/{$this->roomType->id}/images", [
            'image' => $this->validImage('room.png'),
        ])->assertCreated();
        $id = $created->json('data.id');
        $path = $created->json('data.path');

        $this->patchJson("/api/v1/admin/room-images/{$id}", ['sort_order' => 8])
            ->assertOk()->assertJsonPath('data.sort_order', 8);

        $otherManager = $this->manager(Hotel::query()->create([
            'slug' => 'other-manager-hotel', 'name' => 'Other Hotel', 'city' => 'Hue',
            'address' => '2 Main Street', 'checkin_time' => '15:00', 'checkout_time' => '12:00',
        ])->id);
        Sanctum::actingAs($otherManager);
        $this->deleteJson("/api/v1/admin/room-images/{$id}")->assertForbidden();
        Storage::disk('public')->assertExists($path);

        Sanctum::actingAs($this->manager($this->hotel->id));
        $this->deleteJson("/api/v1/admin/room-images/{$id}")->assertNoContent();
        Storage::disk('public')->assertMissing($path);
        $this->assertDatabaseMissing('room_images', ['id' => $id]);
    }

    private function manager(string $hotelId): User
    {
        return User::factory()->create(['role' => 'hotel_manager', 'hotel_id' => $hotelId, 'status' => 'active']);
    }

    private function validImage(string $name): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            $name,
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='),
        );
    }
}
