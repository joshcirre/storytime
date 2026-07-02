import { Link } from '@inertiajs/react';
import { Sparkles } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { show } from '@/routes/characters';
import type { CharacterData } from '@/types';

const statusLabels: Record<string, string> = {
    pending: 'Waiting to start',
    generating_image: 'Painting portrait',
    creating_avatar: 'Coming to life',
    ready: 'Ready to chat',
    failed: 'Something went wrong',
};

export default function CharacterCard({
    character,
}: {
    character: CharacterData;
}) {
    return (
        <Link href={show(character.id)} prefetch>
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
                        <span className="min-w-0 truncate font-display font-medium">
                            {character.name}
                        </span>
                        <Badge
                            variant={
                                character.status === 'ready'
                                    ? 'default'
                                    : character.status === 'failed'
                                      ? 'destructive'
                                      : 'secondary'
                            }
                        >
                            {statusLabels[character.status] ?? character.status}
                        </Badge>
                    </div>
                    <p className="line-clamp-2 text-sm text-muted-foreground">
                        {character.personality}
                    </p>
                </CardContent>
            </Card>
        </Link>
    );
}
