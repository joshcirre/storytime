import { Head, Link } from '@inertiajs/react';
import { Plus, Sparkles } from 'lucide-react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { create, index, show } from '@/routes/characters';
import type { CharacterData } from '@/types';

const statusLabels: Record<string, string> = {
    pending: 'Waiting to start',
    generating_image: 'Painting portrait',
    creating_avatar: 'Coming to life',
    ready: 'Ready to chat',
    failed: 'Something went wrong',
};

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
                            <p className="font-medium">No characters yet</p>
                            <p className="text-sm text-muted-foreground">
                                Upload a drawing and it will come to life as
                                someone you can video-chat with.
                            </p>
                        </div>
                        <Button asChild>
                            <Link href={create()}>
                                Create your first character
                            </Link>
                        </Button>
                    </div>
                ) : (
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {characters.map((character) => (
                            <Link
                                key={character.id}
                                href={show(character.id)}
                                prefetch
                            >
                                <Card className="overflow-hidden pt-0 transition-shadow hover:shadow-md">
                                    <div className="aspect-square bg-muted">
                                        {character.imageUrl ? (
                                            <img
                                                src={character.imageUrl}
                                                alt={character.name}
                                                className="size-full object-cover"
                                            />
                                        ) : (
                                            <div
                                                className={`flex size-full items-center justify-center ${character.isProcessing ? 'animate-pulse' : ''}`}
                                            >
                                                <Sparkles className="size-10 text-muted-foreground" />
                                            </div>
                                        )}
                                    </div>
                                    <CardContent className="space-y-1">
                                        <div className="flex items-center justify-between gap-2">
                                            <span className="font-medium">
                                                {character.name}
                                            </span>
                                            <Badge
                                                variant={
                                                    character.status === 'ready'
                                                        ? 'default'
                                                        : character.status ===
                                                            'failed'
                                                          ? 'destructive'
                                                          : 'secondary'
                                                }
                                            >
                                                {statusLabels[
                                                    character.status
                                                ] ?? character.status}
                                            </Badge>
                                        </div>
                                        <p className="line-clamp-2 text-sm text-muted-foreground">
                                            {character.personality}
                                        </p>
                                    </CardContent>
                                </Card>
                            </Link>
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
