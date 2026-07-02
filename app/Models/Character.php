<?php

namespace App\Models;

use App\CharacterStatus;
use Database\Factories\CharacterFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string $personality
 * @property string $voice
 * @property string|null $prompt
 * @property string|null $drawing_path
 * @property string|null $image_path
 * @property string|null $runway_avatar_id
 * @property CharacterStatus $status
 * @property string|null $failure_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'personality', 'voice', 'prompt', 'drawing_path', 'image_path', 'runway_avatar_id', 'status', 'failure_reason'])]
class Character extends Model
{
    /** @use HasFactory<CharacterFactory> */
    use HasFactory;

    /**
     * Runway live voice presets offered at character creation.
     *
     * @var array<string, string>
     */
    public const VOICES = [
        'ruby' => 'Ruby — bright & bubbly',
        'max' => 'Max — goofy & upbeat',
        'luna' => 'Luna — gentle & dreamy',
        'felix' => 'Felix — silly & squeaky',
        'summer' => 'Summer — sunny & sweet',
        'leo' => 'Leo — bold & adventurous',
        'clara' => 'Clara — kind & calm',
        'drew' => 'Drew — friendly & curious',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<CallSession, $this>
     */
    public function callSessions(): HasMany
    {
        return $this->hasMany(CallSession::class);
    }

    public function imageUrl(): ?string
    {
        return $this->image_path ? $this->storageUrl($this->image_path) : null;
    }

    public function drawingUrl(): ?string
    {
        return $this->drawing_path ? $this->storageUrl($this->drawing_path) : null;
    }

    /**
     * Cloud buckets (R2) are private, so signed URLs are used when the
     * default disk supports them; the local public disk falls back to url().
     */
    protected function storageUrl(string $path): string
    {
        $disk = Storage::disk();

        return $disk->providesTemporaryUrls()
            ? $disk->temporaryUrl($path, now()->addHour())
            : $disk->url($path);
    }

    public function markFailed(string $reason): void
    {
        $this->update([
            'status' => CharacterStatus::Failed,
            'failure_reason' => $reason,
        ]);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CharacterStatus::class,
        ];
    }
}
