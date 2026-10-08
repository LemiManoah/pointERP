import { usePage } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';

export default function AppLogo() {
    const { branding } = usePage<{
        branding: { name: string; logo_url: string | null } | null;
    }>().props;
    return (
        <>
            <div className="flex aspect-square size-8 items-center justify-center rounded-md bg-sidebar-primary text-sidebar-primary-foreground">
                {branding?.logo_url ? (
                    <img
                        src={branding.logo_url}
                        alt=""
                        className="size-8 rounded-md bg-white object-contain p-1"
                    />
                ) : (
                    <AppLogoIcon className="size-5 fill-current text-white dark:text-black" />
                )}
            </div>
            <div className="ml-1 grid flex-1 text-left text-sm">
                <span className="mb-0.5 truncate leading-tight font-semibold">
                    {branding?.name ?? 'Point ERP'}
                </span>
            </div>
        </>
    );
}
