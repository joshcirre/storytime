import { Head, Link } from '@inertiajs/react';
import { Plus, Sparkles } from 'lucide-react';
import CharacterCard from '@/components/character-card';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { create, index } from '@/routes/characters';
import type { CharacterData } from '@/types';

export default function CharactersIndex({
    characters,
}: {
    characters: CharacterData[];
}) {
    return (
        <>
            <Head title="Characters" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="flex items-center justify-between">
                    <Heading
                        title="Characters"
                        description="Bring a drawing to life and talk to it"
                    />

                    <Button asChild>
                        <Link href={create()}>
                            <Plus />
                            New character
                        </Link>
                    </Button>
                </div>

                {characters.length === 0 ? (
                    <div className="flex flex-1 flex-col items-center justify-center gap-4 rounded-xl border border-dashed p-12 text-center">
                        <Sparkles className="size-10 text-muted-foreground" />
                        <div>
                            <p className="font-display text-lg font-medium">
                                No characters yet
                            </p>
                            <p className="mx-auto mt-1 max-w-[48ch] text-base text-pretty text-muted-foreground sm:text-sm">
                                Upload a drawing and it will come to life as
                                someone you can video-chat with.
                            </p>
                        </div>
                        <Button asChild variant="outline">
                            <Link href={create()}>
                                Create your first character
                            </Link>
                        </Button>
                    </div>
                ) : (
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {characters.map((character) => (
                            <CharacterCard
                                key={character.id}
                                character={character}
                            />
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}

CharactersIndex.layout = {
    breadcrumbs: [
        {
            title: 'Characters',
            href: index(),
        },
    ],
};
