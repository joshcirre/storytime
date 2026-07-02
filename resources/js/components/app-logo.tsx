import AppLogoIcon from '@/components/app-logo-icon';

export default function AppLogo() {
    return (
        <>
            <div className="flex aspect-square size-8 items-center justify-center rounded-lg bg-sidebar-primary text-sidebar-primary-foreground">
                <AppLogoIcon className="size-5 shrink-0 fill-current" />
            </div>
            <div className="ml-1 grid flex-1 text-left">
                <span className="truncate font-display text-base font-medium">
                    Storytime
                </span>
            </div>
        </>
    );
}
