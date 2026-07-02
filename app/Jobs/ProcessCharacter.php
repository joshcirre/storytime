<?php

namespace App\Jobs;

use App\CharacterStatus;
use App\Models\Character;
use App\Services\Runway;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class ProcessCharacter implements ShouldQueue
{
    use Queueable;

    public int $timeout = 900;

    public int $tries = 1;

    /**
     * Create a new job instance.
     */
    public function __construct(public Character $character) {}

    /**
     * Execute the job: generate the character portrait with Runway
     * text-to-image, then create a conversational avatar from it.
     */
    public function handle(Runway $runway): void
    {
        $this->character->update(['status' => CharacterStatus::GeneratingImage]);

        $imageUrl = $this->generatePortrait($runway);
        $this->storePortrait($imageUrl);

        $this->character->update(['status' => CharacterStatus::CreatingAvatar]);

        $avatarId = $this->createAvatar($runway, $imageUrl);

        $this->character->update([
            'runway_avatar_id' => $avatarId,
            'status' => CharacterStatus::Ready,
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        $this->character->markFailed($exception?->getMessage() ?? 'Character processing failed.');
    }

    /**
     * Run the text-to-image task and return the Runway-hosted output URL.
     */
    protected function generatePortrait(Runway $runway): string
    {
        $references = [];

        if ($this->character->drawing_path !== null) {
            $disk = Storage::disk();

            if (! $disk->exists($this->character->drawing_path)) {
                throw new RuntimeException('The original drawing file is missing. Please create the character again.');
            }

            $references[] = [
                'uri' => 'data:'.$disk->mimeType($this->character->drawing_path).';base64,'
                    .base64_encode($disk->get($this->character->drawing_path)),
                'tag' => 'drawing',
            ];
        }

        $taskId = $runway->createImageTask($this->imagePrompt(), $references);

        return $this->waitForImageTask($runway, $taskId);
    }

    protected function imagePrompt(): string
    {
        $subject = $this->character->drawing_path !== null
            ? 'the character drawn in @drawing, faithfully keeping its colors, shapes, and unique details'
            : trim((string) $this->character->prompt);

        return "A friendly 3D animated movie character portrait of {$subject}. "
            .'Rendered in a polished 3D animation film style with soft rounded shapes and '
            .'warm cinematic lighting. Front-facing with the face clearly visible and centered, '
            .'big warm smile, simple cheerful background.';
    }

    /**
     * Poll the task until it succeeds and return the first output URL.
     * Runway asks that tasks are polled no faster than every 5 seconds.
     */
    protected function waitForImageTask(Runway $runway, string $taskId): string
    {
        foreach (range(1, 60) as $attempt) {
            Sleep::for(5)->seconds();

            $task = $runway->getTask($taskId);

            if ($task['status'] === 'SUCCEEDED') {
                return $task['output'][0]
                    ?? throw new RuntimeException('Image task succeeded but returned no output.');
            }

            if (in_array($task['status'], ['FAILED', 'CANCELLED'])) {
                throw new RuntimeException('Image generation failed: '.($task['failure'] ?? $task['status']));
            }
        }

        throw new RuntimeException('Timed out waiting for image generation.');
    }

    /**
     * Download the generated portrait, since Runway output URLs expire within 24-48 hours.
     */
    protected function storePortrait(string $imageUrl): void
    {
        $path = 'characters/'.Str::uuid().'.png';

        Storage::put($path, Http::timeout(60)->get($imageUrl)->throw()->body());

        $this->character->update(['image_path' => $path]);
    }

    /**
     * Runway's text moderation is non-deterministic: byte-identical payloads
     * have been observed failing and then passing minutes apart. Retry with
     * the full personality before falling back to the base persona so a
     * flaky rejection doesn't strip the character's custom flavor.
     */
    protected function createAvatar(Runway $runway, string $referenceImageUrl): string
    {
        $attempts = [true, true, false];
        $lastAttempt = array_key_last($attempts);

        foreach ($attempts as $index => $includeUserPersonality) {
            $avatar = $runway->createAvatar(
                $this->character->name,
                $this->avatarPersonality($includeUserPersonality),
                $referenceImageUrl,
                $this->character->voice,
            );

            try {
                return $this->waitForAvatar($runway, $avatar);
            } catch (RuntimeException $exception) {
                if (! str_contains($exception->getMessage(), 'text cannot be used')) {
                    throw $exception;
                }

                if ($index === $lastAttempt) {
                    throw new RuntimeException(
                        "Runway's safety filter kept rejecting this character's description. "
                        .'Try rewording the personality and retry.',
                    );
                }

                Sleep::for(10)->seconds();
            }
        }

        throw new RuntimeException('Avatar creation failed.');
    }

    /**
     * @param  array{id: string, status: string, failureReason?: string}  $avatar
     */
    protected function waitForAvatar(Runway $runway, array $avatar): string
    {
        foreach (range(1, 60) as $attempt) {
            if ($avatar['status'] === 'READY') {
                return $avatar['id'];
            }

            if ($avatar['status'] === 'FAILED') {
                throw new RuntimeException('Avatar creation failed: '.($avatar['failureReason'] ?? 'unknown reason'));
            }

            Sleep::for(5)->seconds();

            $avatar = $runway->getAvatar($avatar['id']);
        }

        throw new RuntimeException('Timed out waiting for avatar creation.');
    }

    /**
     * Compose the avatar's system prompt, including guidance on when to use
     * the conversation tools. Runway's avatar moderation rejects
     * child-directed phrasing and some innocuous phrase combinations in the
     * user's own words ("cute ... playing with friends"), so the persona is
     * framed around the artist and the user's personality is embedded as the
     * artist's quoted description — both verified against the live API.
     */
    protected function avatarPersonality(bool $includeUserPersonality = true): string
    {
        $base = "Your name is {$this->character->name}. "
            .'You are a cheerful storybook character brought to life from a hand-drawn picture, '
            .'chatting with the artist who drew you. Be warm, playful, and encouraging. '
            .'Keep replies short, upbeat, and family-friendly. '
            .'When someone mentions a city or asks about the weather, use the get_weather tool '
            .'and react to the result with personality. When someone asks for a joke, use the '
            .'tell_joke tool and deliver it with enthusiasm.';

        if (! $includeUserPersonality) {
            return $base;
        }

        return $base.' The artist describes you like this: "'
            .trim($this->character->personality).'" Stay true to that description.';
    }
}
