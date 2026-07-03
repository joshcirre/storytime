import { Form, Head, Link, usePoll } from '@inertiajs/react';
import { AvatarCall } from '@runwayml/avatars-react';
import { Phone, RotateCcw, Sparkles, Trash2 } from 'lucide-react';
import { useEffect, useState } from 'react';
import CallSessionController from '@/actions/App/Http/Controllers/CallSessionController';
import CharacterController from '@/actions/App/Http/Controllers/CharacterController';
import Heading from '@/components/heading';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Skeleton } from '@/components/ui/skeleton';
import { Spinner } from '@/components/ui/spinner';
import { index, show } from '@/routes/characters';
import type { CharacterData } from '@/types';

import '@runwayml/avatars-react/styles.css';

const processingSteps: Record<string, string> = {
    pending: 'Getting ready...',
    generating_image: 'Painting their portrait...',
    creating_avatar: 'Teaching them to talk...',
};

async function json<T>(
    url: string,
    method: 'GET' | 'POST' = 'GET',
): Promise<T> {
    const xsrf = document.cookie
        .split('; ')
        .find((row) => row.startsWith('XSRF-TOKEN='))
        ?.split('=')[1];

    const response = await fetch(url, {
        method,
        headers: {
            Accept: 'application/json',
            'X-XSRF-TOKEN': xsrf ? decodeURIComponent(xsrf) : '',
        },
    });

    if (!response.ok) {
        throw new Error(`Request failed with status ${response.status}`);
    }

    return response.json() as Promise<T>;
}

const sleep = (ms: number) => new Promise((resolve) => setTimeout(resolve, ms));

type Credentials = { sessionId: string; sessionKey: string };

function CallSection({ character }: { character: CharacterData }) {
    const [phase, setPhase] = useState<'idle' | 'connecting' | 'live'>('idle');
    const [credentials, setCredentials] = useState<Credentials | null>(null);
    const [error, setError] = useState<string | null>(null);

    async function startCall() {
        setPhase('connecting');
        setError(null);

        try {
            const { callSessionId } = await json<{ callSessionId: number }>(
                CallSessionController.store.url(character.id),
                'POST',
            );

            for (let attempt = 0; attempt < 45; attempt++) {
                await sleep(2000);

                const session = await json<{
                    status: string;
                    sessionId: string;
                    sessionKey: string | null;
                    failure: string | null;
                }>(CallSessionController.show.url(callSessionId));

                if (session.status === 'READY' && session.sessionKey) {
                    setCredentials({
                        sessionId: session.sessionId,
                        sessionKey: session.sessionKey,
                    });
                    setPhase('live');

                    return;
                }

                if (
                    ['FAILED', 'CANCELLED', 'COMPLETED'].includes(
                        session.status,
                    )
                ) {
                    throw new Error(
                        session.failure ?? 'The call could not be started.',
                    );
                }
            }

            throw new Error('Timed out waiting for the call to start.');
        } catch (caught) {
            setError(
                caught instanceof Error
                    ? caught.message
                    : 'The call could not be started.',
            );
            setPhase('idle');
        }
    }

    if (phase === 'live' && credentials && character.runwayAvatarId) {
        return (
            <AvatarCall
                avatarId={character.runwayAvatarId}
                sessionId={credentials.sessionId}
                sessionKey={credentials.sessionKey}
                avatarImageUrl={character.imageUrl ?? undefined}
                onEnd={() => setPhase('idle')}
                onError={(callError) => {
                    setError(callError.message);
                    setPhase('idle');
                }}
                className="aspect-video w-full overflow-hidden rounded-xl border bg-black"
            />
        );
    }

    return (
        <div className="flex flex-col items-start gap-3">
            {error && (
                <Alert variant="destructive">
                    <AlertTitle>Call failed</AlertTitle>
                    <AlertDescription>{error}</AlertDescription>
                </Alert>
            )}

            <Button
                size="lg"
                onClick={startCall}
                disabled={phase === 'connecting'}
            >
                {phase === 'connecting' ? (
                    <>
                        <Spinner />
                        Waking up {character.name}...
                    </>
                ) : (
                    <>
                        <Phone />
                        Start a video call
                    </>
                )}
            </Button>
        </div>
    );
}

function DeleteCharacter({ character }: { character: CharacterData }) {
    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button variant="ghost" size="sm">
                    <Trash2 />
                    Delete
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Delete {character.name}?</DialogTitle>
                <DialogDescription>
                    This removes the character, their portrait, and the uploaded
                    drawing for good.
                </DialogDescription>
                <Form {...CharacterController.destroy.form(character.id)}>
                    {({ processing }) => (
                        <DialogFooter className="gap-2">
                            <DialogClose asChild>
                                <Button variant="secondary">Cancel</Button>
                            </DialogClose>
                            <Button
                                variant="destructive"
                                disabled={processing}
                                asChild
                            >
                                <button type="submit">Delete character</button>
                            </Button>
                        </DialogFooter>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

export default function CharactersShow({
    character,
}: {
    character: CharacterData;
}) {
    const { start, stop } = usePoll(
        3000,
        { only: ['character'] },
        { autoStart: false },
    );

    useEffect(() => {
        if (character.isProcessing) {
            start();
        } else {
            stop();
        }
    }, [character.isProcessing, start, stop]);

    return (
        <>
            <Head title={character.name} />

            <div className="flex flex-col gap-6 p-4">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title={character.name}
                        description={character.personality}
                    />
                    <DeleteCharacter character={character} />
                </div>

                <div className="grid gap-6 lg:grid-cols-[320px_1fr]">
                    <div className="space-y-4">
                        <div className="aspect-square overflow-hidden rounded-xl border bg-muted">
                            {character.imageUrl ? (
                                <img
                                    src={character.imageUrl}
                                    alt={character.name}
                                    className="size-full object-cover"
                                />
                            ) : (
                                <Skeleton className="flex size-full items-center justify-center rounded-none">
                                    <Sparkles className="size-10 text-muted-foreground" />
                                </Skeleton>
                            )}
                        </div>

                        {character.drawingUrl && (
                            <div className="space-y-1">
                                <p className="text-sm font-medium">
                                    The original drawing
                                </p>
                                <img
                                    src={character.drawingUrl}
                                    alt={`Original drawing of ${character.name}`}
                                    className="max-h-40 w-fit rounded-lg border object-contain"
                                />
                            </div>
                        )}
                    </div>

                    <div>
                        {character.isProcessing && (
                            <div className="flex items-center gap-3 rounded-xl border p-6">
                                <Spinner />
                                <div>
                                    <p className="font-medium">
                                        {processingSteps[character.status] ??
                                            'Working on it...'}
                                    </p>
                                    <p className="text-sm text-muted-foreground">
                                        The 3D render takes a few minutes. It
                                        keeps working in the background, so
                                        you can leave this page and come back.
                                    </p>
                                </div>
                            </div>
                        )}

                        {character.status === 'failed' && (
                            <div className="space-y-4">
                                <Alert variant="destructive">
                                    <AlertTitle>
                                        We couldn't bring this character to life
                                    </AlertTitle>
                                    <AlertDescription>
                                        {character.failureReason ??
                                            'Something went wrong. Try creating them again.'}
                                    </AlertDescription>
                                </Alert>
                                <Button asChild variant="secondary">
                                    <Link
                                        href={CharacterController.retry.url(
                                            character.id,
                                        )}
                                        method="post"
                                        as="button"
                                    >
                                        <RotateCcw />
                                        Try again
                                    </Link>
                                </Button>
                            </div>
                        )}

                        {character.status === 'ready' && (
                            <div className="space-y-4">
                                <p className="text-sm text-muted-foreground">
                                    {character.name} is ready to talk! Ask about
                                    the weather where you live, or ask for a
                                    joke.
                                </p>
                                <CallSection character={character} />
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}

CharactersShow.layout = ({ character }: { character: CharacterData }) => ({
    breadcrumbs: [
        {
            title: 'Characters',
            href: index(),
        },
        {
            title: character.name,
            href: show(character.id),
        },
    ],
});
