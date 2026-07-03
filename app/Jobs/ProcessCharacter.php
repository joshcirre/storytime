<?php

namespace App\Jobs;

use App\CharacterStatus;
use App\Exceptions\PortraitRejectedException;
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
     * text-to-image, then create a conversational avatar from it. A stored
     * portrait is reused so retries and interrupted runs skip the slow,
     * credit-spending generation step.
     */
    public function handle(Runway $runway): void
    {
        $this->character->refresh();

        $imageUrl = $this->existingPortraitUrl();

        if ($imageUrl === null) {
            $this->character->update(['status' => CharacterStatus::GeneratingImage]);

            $imageUrl = $this->generatePortrait($runway);
            $this->storePortrait($imageUrl);
        }

        $this->character->update(['status' => CharacterStatus::CreatingAvatar]);

        try {
            $avatarId = $this->createAvatar($runway, $imageUrl);
        } catch (PortraitRejectedException) {
            // Runway's avatar service wants a face that dominates the frame.
            // Full-body portraits sometimes fail that bar, so fall back to a
            // close-up composition, which has always passed.
            $this->character->update(['status' => CharacterStatus::GeneratingImage]);

            $imageUrl = $this->generatePortrait($runway, closeUp: true);
            $this->storePortrait($imageUrl);

            $this->character->update(['status' => CharacterStatus::CreatingAvatar]);

            $avatarId = $this->createAvatar($runway, $imageUrl);
        }

        $this->character->update([
            'runway_avatar_id' => $avatarId,
            'status' => CharacterStatus::Ready,
        ]);
    }

    /**
     * An HTTPS URL for the already-stored portrait, if one exists.
     */
    protected function existingPortraitUrl(): ?string
    {
        $path = $this->character->image_path;

        if ($path === null || ! Storage::exists($path)) {
            return null;
        }

        $disk = Storage::disk();

        return $disk->providesTemporaryUrls()
            ? $disk->temporaryUrl($path, now()->addHour())
            : $disk->url($path);
    }

    public function failed(?Throwable $exception): void
    {
        $this->character->markFailed($exception?->getMessage() ?? 'Character processing failed.');
    }

    /**
     * Run the text-to-image task and return the Runway-hosted output URL.
     */
    protected function generatePortrait(Runway $runway, bool $closeUp = false): string
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

        $taskId = $runway->createImageTask($this->imagePrompt($closeUp), $references);

        return $this->waitForImageTask($runway, $taskId);
    }

    /**
     * The gpt_image_2 prompt that won a side-by-side bake-off: it keeps the
     * drawing's exact design (stick limbs, scribbles and all) while changing
     * the medium to a 3D film render, instead of redesigning the character.
     */
    protected function imagePrompt(bool $closeUp = false): string
    {
        $render = $closeUp
            ? 'A head-and-shoulders close-up portrait: the character face is large, front-facing, '
                .'and fills most of the frame, with two big expressive eyes and a clear smiling '
                .'mouth, fully visible and unobstructed, looking straight at the camera. '
            : 'Keep the whole character and composition — full body is fine — but give the '
                .'character stylized big-head proportions: the head is large and prominent, and the '
                .'front-facing face with two big expressive eyes and a clear smiling mouth takes up a '
                .'large part of the frame, fully visible, unobstructed, looking straight at the camera. ';

        $render .= 'It must be a fully 3D CGI render, not a flat illustration: volumetric forms, '
            .'detailed textures, soft global illumination, glossy expressive eyes. '
            .'Softly blurred cheerful background.';

        if ($this->character->drawing_path !== null) {
            return 'Recreate the character from @drawing as a high-quality 3D animated film character, '
                .'like a still from a modern Pixar movie. '.$render
                .' Faithfully keep the drawing\'s colors, shapes, outfit, and charm.';
        }

        return 'A high-quality 3D animated film character, like a still from a modern Pixar movie: '
            .trim((string) $this->character->prompt).'. '.$render;
    }

    /**
     * Poll the task until it succeeds and return the first output URL.
     * Runway asks that tasks are polled no faster than every 5 seconds.
     */
    protected function waitForImageTask(Runway $runway, string $taskId): string
    {
        foreach (range(1, 90) as $attempt) {
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
     * Runway rejects avatars for opaque reasons (the "text cannot be used"
     * error also fires for image problems), so retry with the full
     * personality, then the base persona, before declaring the portrait
     * rejected — which triggers the close-up regeneration in handle().
     */
    protected function createAvatar(Runway $runway, string $referenceImageUrl): string
    {
        $attempts = [true, false];
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
                    throw new PortraitRejectedException;
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
            .'Whenever someone mentions the weather, the temperature, a city, or where they live, '
            .'always use the get_weather tool and react to the real result with personality — never '
            .'make up weather. When someone asks for a joke, always use the tell_joke tool and perform '
            .'it as a proper knock-knock joke: say "Knock knock", wait for them to answer "who\'s there", '
            .'give the setup, wait again, then land the punchline. '
            .'If you get interrupted while speaking, briefly finish your thought before responding.';

        if (! $includeUserPersonality) {
            return $base;
        }

        return $base.' The artist describes you like this: "'
            .trim($this->character->personality).'" Stay true to that description.';
    }
}
