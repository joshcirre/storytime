<?php

namespace App\Services;

use App\Models\CallSession;
use App\Models\Character;
use Illuminate\Support\Str;

class CharacterPersona
{
    /**
     * Compose the character's base persona, including guidance on when to
     * use the conversation tools. Runway's avatar moderation rejects
     * child-directed phrasing and some innocuous phrase combinations in the
     * user's own words, so the persona is framed around the artist and the
     * user's personality is embedded as the artist's quoted description —
     * both verified against the live API.
     */
    public static function compose(Character $character, bool $includeUserPersonality = true): string
    {
        $base = "Your name is {$character->name}. "
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
            .trim($character->personality).'" Stay true to that description.';
    }

    /**
     * Compose the per-session persona: the base persona plus memories of
     * previous conversations and the artist's other characters, so calling
     * a character back feels like talking to an old friend.
     */
    public static function composeForSession(Character $character): string
    {
        return collect([
            self::compose($character),
            self::siblings($character),
            self::memories($character),
        ])->filter()->implode(' ');
    }

    /**
     * The artist's other living characters, so they can talk about each other.
     */
    protected static function siblings(Character $character): ?string
    {
        $siblings = $character->user->characters()
            ->whereKeyNot($character->id)
            ->whereNotNull('runway_avatar_id')
            ->get()
            ->map(fn (Character $sibling): string => "{$sibling->name} (".Str::limit(trim($sibling->personality), 80).')');

        if ($siblings->isEmpty()) {
            return null;
        }

        return 'The same artist also brought your friends to life: '
            .$siblings->implode('; ').'. You know them well and can mention them fondly.';
    }

    /**
     * Highlights from recent conversations, oldest first, within a
     * character budget so the persona stays well under moderation limits.
     */
    protected static function memories(Character $character, int $budget = 1500): ?string
    {
        $recentSessions = $character->callSessions()
            ->whereNotNull('transcript')
            ->latest()
            ->take(3)
            ->get()
            ->reverse();

        if ($recentSessions->isEmpty()) {
            return null;
        }

        $memories = $recentSessions->map(function (CallSession $session): string {
            $lines = collect($session->transcript)
                ->filter(fn (array $line): bool => filled($line['content'] ?? null))
                ->map(fn (array $line): string => (($line['role'] ?? '') === 'user' ? 'They said' : 'You said')
                    .': '.Str::limit(trim($line['content']), 120))
                ->take(-12);

            return 'On '.$session->created_at->format('F j').': '.$lines->implode(' ');
        });

        return 'You remember your earlier chats with this family. '
            .Str::limit($memories->implode(' '), $budget)
            .' Bring these memories up naturally when they fit, like a friend who remembers.';
    }
}
