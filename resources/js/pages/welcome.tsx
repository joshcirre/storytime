import { Head, Link, usePage } from '@inertiajs/react';
import { CloudSun, MessageCircleHeart, Smile } from 'lucide-react';
import AppLogoIcon from '@/components/app-logo-icon';
import { Button } from '@/components/ui/button';
import { dashboard, login, register } from '@/routes';
import type { Auth } from '@/types';

type PageProps = {
    auth: Auth;
};

function CrayonCharacter({ drawing = false }: { drawing?: boolean }) {
    const body = drawing
        ? 'fill-none stroke-foreground/70'
        : 'fill-violet-400 stroke-violet-600';
    const belly = drawing
        ? 'fill-none stroke-foreground/70'
        : 'fill-violet-200 stroke-violet-600';
    const detail = drawing ? 'stroke-foreground/70' : 'stroke-violet-950';

    return (
        <svg
            viewBox="0 0 120 130"
            xmlns="http://www.w3.org/2000/svg"
            aria-hidden="true"
            className="size-full"
        >
            <g strokeWidth="3" strokeLinecap="round" strokeLinejoin="round">
                <path
                    className={body}
                    d="M60 18c24 0 38 16 38 40 0 15-3 26-1 38 1 7-4 12-10 10-5-2-9-3-14-2-8 2-18 2-26 0-5-1-9 0-14 2-6 2-11-3-10-10 2-12-1-23-1-38 0-24 14-16 38-40Z"
                />
                <path className={body} d="M42 24 30 8m48 16 12-16" />
                <ellipse className={belly} cx="60" cy="86" rx="20" ry="16" />
                <circle
                    className={detail}
                    cx="44"
                    cy="52"
                    r="4"
                    fill="currentcolor"
                />
                <circle
                    className={detail}
                    cx="76"
                    cy="52"
                    r="4"
                    fill="currentcolor"
                />
                <path
                    className={detail}
                    d="M46 66c4 5 9 7 14 7s10-2 14-7"
                    fill="none"
                />
            </g>
        </svg>
    );
}

function HeroVisual() {
    return (
        <div className="relative mx-auto w-full max-w-md">
            <div className="rounded-(--frame-radius) bg-linear-to-b from-violet-100 to-violet-50 p-(--frame-padding) ring-1 ring-black/5 [--frame-padding:--spacing(2)] [--frame-radius:var(--radius-3xl)] dark:from-violet-950/60 dark:to-violet-950/20 dark:ring-white/10">
                <div className="relative overflow-hidden rounded-[calc(var(--frame-radius)-var(--frame-padding))] bg-white/60 px-8 pt-8 pb-14 dark:bg-white/5">
                    <div className="mx-auto w-40 sm:w-48">
                        <CrayonCharacter />
                    </div>
                    <div className="absolute top-4 left-4 rounded-full bg-white/90 px-3 py-1 ring-1 ring-black/5 dark:bg-white/10 dark:ring-white/10">
                        <p className="font-display text-sm font-medium text-violet-700 dark:text-violet-300">
                            Sparkles
                        </p>
                    </div>
                    <div className="absolute right-4 bottom-4 max-w-44 rounded-2xl rounded-br-sm bg-violet-600 px-3.5 py-2.5">
                        <p className="text-sm/5 text-white">
                            Whoa, it's 108 degrees in Phoenix today!
                        </p>
                    </div>
                </div>
            </div>
            <div className="absolute -bottom-6 -left-4 w-24 rotate-[-8deg] rounded-lg bg-white p-2 pb-4 shadow-md ring-1 ring-black/5 sm:-left-10 sm:w-28 dark:bg-neutral-900 dark:shadow-none dark:ring-white/10">
                <CrayonCharacter drawing />
                <p className="mt-1 text-center font-display text-xs text-muted-foreground">
                    the original
                </p>
            </div>
        </div>
    );
}

const steps = [
    {
        name: 'Start with a drawing',
        description:
            'Snap a photo of any drawing and upload it, or just describe a character in a sentence.',
    },
    {
        name: 'Watch them come to life',
        description:
            'Runway paints a polished portrait of the character and gives it a voice and personality you choose.',
    },
    {
        name: 'Hop on a video call',
        description:
            'Your character answers a live video call, ready to chat, giggle, and ask about your day.',
    },
];

const talents = [
    {
        name: 'They know the weather',
        description:
            'Mention where you live and your character checks the real forecast, then reacts in character.',
        icon: CloudSun,
    },
    {
        name: 'They tell great jokes',
        description:
            'Ask for a joke and they fetch a fresh, family-friendly one every time.',
        icon: Smile,
    },
    {
        name: 'They stay themselves',
        description:
            'The name and personality you give a character shapes everything they say.',
        icon: MessageCircleHeart,
    },
];

export default function Welcome() {
    const { auth } = usePage<PageProps>().props;

    return (
        <>
            <Head title="Bring drawings to life" />
            <div className="min-h-svh bg-background text-foreground">
                <header>
                    <div className="mx-auto flex max-w-6xl items-center justify-between px-6 py-5 lg:px-8">
                        <a
                            href="/"
                            aria-label="Homepage"
                            className="flex items-center gap-2"
                        >
                            <div className="flex size-8 items-center justify-center rounded-lg bg-primary">
                                <AppLogoIcon className="size-5 shrink-0 fill-white" />
                            </div>
                            <p className="font-display text-lg font-medium">
                                Storytime
                            </p>
                        </a>
                        <nav className="flex items-center gap-2">
                            {auth.user ? (
                                <Button asChild variant="outline">
                                    <Link href={dashboard()}>Dashboard</Link>
                                </Button>
                            ) : (
                                <>
                                    <Button asChild variant="ghost">
                                        <Link href={login()}>Log in</Link>
                                    </Button>
                                    <Button asChild variant="outline">
                                        <Link href={register()}>Sign up</Link>
                                    </Button>
                                </>
                            )}
                        </nav>
                    </div>
                </header>

                <main>
                    <section className="pt-16 pb-20 sm:pt-24">
                        <div className="mx-auto max-w-6xl px-6 lg:px-8">
                            <div className="grid items-center gap-x-8 gap-y-16 lg:grid-cols-2">
                                <div className="text-center lg:text-left">
                                    <h1 className="mx-auto max-w-[24ch] font-display text-5xl font-medium text-balance sm:text-6xl lg:mx-0">
                                        Their drawings come to life.
                                    </h1>
                                    <p className="mx-auto mt-6 max-w-[48ch] text-lg text-pretty text-muted-foreground lg:mx-0">
                                        Upload a drawing, give it a name and a
                                        personality, and hop on a live video
                                        call with the character your kid
                                        imagined.
                                    </p>
                                    <div className="mt-8 flex items-center justify-center gap-4 lg:justify-start">
                                        <Button asChild size="lg">
                                            <Link
                                                href={
                                                    auth.user
                                                        ? dashboard()
                                                        : register()
                                                }
                                            >
                                                Bring a drawing to life
                                            </Link>
                                        </Button>
                                        <Button
                                            asChild
                                            variant="link"
                                            size="lg"
                                        >
                                            <a href="#how-it-works">
                                                See how it works
                                            </a>
                                        </Button>
                                    </div>
                                </div>
                                <HeroVisual />
                            </div>
                        </div>
                    </section>

                    <section id="how-it-works" className="py-20">
                        <div className="mx-auto max-w-6xl px-6 lg:px-8">
                            <p className="font-display text-base font-medium text-primary">
                                How it works
                            </p>
                            <h2 className="mt-2 max-w-[35ch] font-display text-4xl font-medium tracking-tight text-balance">
                                From refrigerator door to video call in minutes.
                            </h2>
                            <dl className="mt-12 grid gap-x-8 gap-y-10 sm:grid-cols-3">
                                {steps.map((step, index) => (
                                    <div key={step.name}>
                                        <dt className="flex items-center gap-3">
                                            <span className="flex size-7 shrink-0 items-center justify-center rounded-full bg-accent font-display text-sm font-medium text-accent-foreground">
                                                {index + 1}
                                            </span>
                                            <span className="font-medium">
                                                {step.name}
                                            </span>
                                        </dt>
                                        <dd className="mt-3 text-base/7 text-pretty text-muted-foreground sm:text-sm/6">
                                            {step.description}
                                        </dd>
                                    </div>
                                ))}
                            </dl>
                        </div>
                    </section>

                    <section className="py-20">
                        <div className="mx-auto max-w-6xl px-6 lg:px-8">
                            <p className="font-display text-base font-medium text-primary">
                                Not just a pretty face
                            </p>
                            <h2 className="mt-2 max-w-[35ch] font-display text-4xl font-medium tracking-tight text-balance">
                                Characters with real talents.
                            </h2>
                            <p className="mt-4 max-w-[56ch] text-base text-pretty text-muted-foreground">
                                Mid-conversation, your character can reach out
                                to the real world and bring back something true.
                            </p>
                            <dl className="mt-12 grid gap-x-8 gap-y-10 sm:grid-cols-3">
                                {talents.map((talent) => (
                                    <div key={talent.name}>
                                        <dt className="flex items-center gap-2 font-medium">
                                            <talent.icon
                                                aria-hidden="true"
                                                className="size-5 shrink-0 text-primary"
                                            />
                                            {talent.name}
                                        </dt>
                                        <dd className="mt-3 text-base/7 text-pretty text-muted-foreground sm:text-sm/6">
                                            {talent.description}
                                        </dd>
                                    </div>
                                ))}
                            </dl>
                        </div>
                    </section>

                    <section className="py-20">
                        <div className="mx-auto max-w-6xl px-6 lg:px-8">
                            <div className="rounded-3xl bg-accent px-6 py-16 text-center dark:bg-accent/40">
                                <h2 className="mx-auto max-w-[35ch] font-display text-4xl font-medium tracking-tight text-balance">
                                    Someone very silly is waiting to meet you.
                                </h2>
                                <p className="mx-auto mt-4 max-w-[48ch] text-lg text-pretty text-muted-foreground">
                                    It takes about two minutes to bring a
                                    drawing to life.
                                </p>
                                <div className="mt-8">
                                    <Button asChild size="lg" variant="outline">
                                        <Link
                                            href={
                                                auth.user
                                                    ? dashboard()
                                                    : register()
                                            }
                                        >
                                            Create a character
                                        </Link>
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </section>
                </main>

                <footer className="border-t border-foreground/10">
                    <div className="mx-auto flex max-w-6xl flex-col items-center justify-between gap-4 px-6 py-10 sm:flex-row lg:px-8">
                        <div className="flex items-center gap-2">
                            <AppLogoIcon className="size-5 shrink-0 fill-primary" />
                            <p className="font-display text-base font-medium">
                                Storytime
                            </p>
                        </div>
                        <p className="text-sm text-muted-foreground">
                            A demo built with Laravel and the Runway API.
                        </p>
                    </div>
                </footer>
            </div>
        </>
    );
}
