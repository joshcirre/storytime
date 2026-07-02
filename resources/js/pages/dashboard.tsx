import { Head, Link, usePage } from '@inertiajs/react';
import { Plus, Sparkles } from 'lucide-react';
import CharacterCard from '@/components/character-card';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import { create, index as charactersIndex } from '@/routes/characters';
import type { Auth, CharacterData } from '@/types';

type PageProps = {
    auth: Auth;
};

type Stats = {
    characters: number;
    ready: number;
    calls: number;
};

const statLabels: Record<keyof Stats, string> = {
    characters: 'Characters created',
    ready: 'Ready to call',
    calls: 'Video calls started',
};

export default function Dashboard({
    characters,
    stats,
}: {
    characters: CharacterData[];
    stats: Stats;
}) {
    const { auth } = usePage<PageProps>().props;
    const firstName = auth.user.name.split(' ')[0];

    return (
        <>
            <Head title="Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-10 p-4 sm:p-6">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <h1 className="font-display text-3xl font-medium tracking-tight text-balance">
                            Hey, {firstName}.
                        </h1>
                        <p className="mt-1 text-base text-pretty text-muted-foreground">
                            {stats.ready > 0
                                ? 'Your characters are waiting by the phone.'
                                : 'Got a drawing on the fridge? Bring it to life.'}
                        </p>
                    </div>
                    <Button asChild>
                        <Link href={create()}>
                            <Plus />
                            New character
                        </Link>
                    </Button>
                </div>

                <dl className="grid grid-cols-3">
                    {(
                        Object.entries(statLabels) as Array<
                            [keyof Stats, string]
                        >
                    ).map(([key, label], statIndex) => (
                        <div
                            key={key}
                            className={
                                statIndex === 0
                                    ? 'pr-6'
                                    : statIndex === 2
                                      ? 'border-l border-foreground/10 pl-6'
                                      : 'border-l border-foreground/10 px-6'
                            }
                        >
                            <dt className="truncate text-sm text-muted-foreground">
                                {label}
                            </dt>
                            <dd className="mt-1 font-display text-3xl font-medium tabular-nums">
                                {stats[key]}
                            </dd>
                        </div>
                    ))}
                </dl>

                {characters.length === 0 ? (
                    <div className="flex flex-1 flex-col items-center justify-center gap-4 rounded-xl border border-dashed p-12 text-center">
                        <Sparkles className="size-10 text-muted-foreground" />
                        <div>
                            <p className="font-display text-lg font-medium">
                                No characters yet
                            </p>
                            <p className="mx-auto mt-1 max-w-[48ch] text-base text-pretty text-muted-foreground sm:text-sm">
                                Upload a drawing or describe a character, and in
                                a couple of minutes you can video-chat with
                                them.
                            </p>
                        </div>
                        <Button asChild variant="outline">
                            <Link href={create()}>
                                Create your first character
                            </Link>
                        </Button>
                    </div>
                ) : (
                    <div>
                        <div className="flex items-center justify-between gap-4">
                            <h2 className="font-display text-lg font-medium">
                                Recent characters
                            </h2>
                            <Button asChild variant="ghost" size="sm">
                                <Link href={charactersIndex()}>View all</Link>
                            </Button>
                        </div>
                        <div className="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            {characters.map((character) => (
                                <CharacterCard
                                    key={character.id}
                                    character={character}
                                />
                            ))}
                        </div>
                    </div>
                )}
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
