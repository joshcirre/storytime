import { Link } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

export default function AuthSimpleLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    return (
        <div className="flex min-h-svh flex-col items-center justify-center gap-6 bg-background p-6 md:p-10">
            <div className="w-full max-w-xs">
                <div className="flex flex-col gap-8">
                    <div className="flex flex-col items-center gap-4">
                        <Link
                            href={home()}
                            aria-label="Homepage"
                            className="flex flex-col items-center gap-2"
                        >
                            <div className="mb-1 flex size-10 items-center justify-center rounded-xl bg-primary">
                                <AppLogoIcon className="size-6 shrink-0 fill-white" />
                            </div>
                            <span className="sr-only">Storytime</span>
                        </Link>

                        <div className="space-y-2 text-center">
                            <h1 className="font-display text-2xl font-medium tracking-tight text-balance">
                                {title}
                            </h1>
                            <p className="text-center text-base text-pretty text-muted-foreground sm:text-sm">
                                {description}
                            </p>
                        </div>
                    </div>
                    {children}
                </div>
            </div>
        </div>
    );
}
