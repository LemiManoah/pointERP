import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowLeft, Home, ShieldAlert } from 'lucide-react';
import { Button } from '@/components/ui/button';

type Props = {
    status: 403 | 404;
};

const copy = {
    403: {
        title: 'Access denied',
        message:
            'You do not have permission to open this page or perform this action.',
    },
    404: {
        title: 'Page not found',
        message:
            'The page or record may have moved, been archived, or never existed.',
    },
};

export default function ErrorShow({ status }: Props) {
    const { auth } = usePage().props;
    const details = copy[status] ?? copy[404];

    return (
        <main className="grid min-h-screen place-items-center bg-background p-6 text-foreground">
            <Head title={`${status} ${details.title}`} />

            <section className="w-full max-w-md text-center">
                <div className="mx-auto flex size-12 items-center justify-center rounded-full border bg-muted">
                    <ShieldAlert className="size-6 text-muted-foreground" />
                </div>
                <p className="mt-6 text-sm font-medium text-muted-foreground">
                    {status}
                </p>
                <h1 className="mt-2 text-3xl font-semibold tracking-tight">
                    {details.title}
                </h1>
                <p className="mt-3 text-sm leading-6 text-muted-foreground">
                    {details.message}
                </p>
                <div className="mt-8 flex flex-col justify-center gap-3 sm:flex-row">
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => history.back()}
                    >
                        <ArrowLeft />
                        Go back
                    </Button>
                    <Button asChild>
                        <Link href={auth.user ? '/dashboard' : '/login'}>
                            <Home />
                            {auth.user ? 'Dashboard' : 'Login'}
                        </Link>
                    </Button>
                </div>
            </section>
        </main>
    );
}
