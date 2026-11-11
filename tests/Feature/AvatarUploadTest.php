<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AvatarUploadTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('avatarFormats')]
    public function test_user_can_upload_an_avatar_with_webp_conversions(string $format): void
    {
        Storage::fake('minio-public');
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/users/'.$user->id.'/avatar', [
                'avatar' => UploadedFile::fake()->image('avatar.'.$format, 200, 200),
            ])
            ->assertSuccessful();

        $media = $user->fresh()->getFirstMedia('avatars');
        $this->assertNotNull($media);
        $this->assertTrue($media->hasGeneratedConversion('optimized'));
        $this->assertTrue($media->hasGeneratedConversion('thumbnail'));
        Storage::disk('minio-public')->assertExists($media->getPathRelativeToRoot());
        Storage::disk('minio-public')->assertExists($media->getPathRelativeToRoot('optimized'));
        Storage::disk('minio-public')->assertExists($media->getPathRelativeToRoot('thumbnail'));
        $this->assertSame(IMAGETYPE_WEBP, exif_imagetype($media->getPath('thumbnail')));
    }

    public static function avatarFormats(): array
    {
        return [
            'JPEG' => ['jpeg'],
            'PNG' => ['png'],
            'WebP' => ['webp'],
        ];
    }
}
