import {
    createContext,
    type ReactNode,
    useContext,
    useMemo,
    useState,
} from 'react';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';

type ConfirmDialogOptions = {
    title: string;
    description?: string;
    confirmLabel?: string;
    cancelLabel?: string;
    variant?: 'default' | 'destructive';
    onConfirm: () => void | Promise<void>;
};

type ConfirmDialogContextValue = {
    confirm: (options: ConfirmDialogOptions) => void;
};

const ConfirmDialogContext = createContext<ConfirmDialogContextValue | null>(
    null,
);

export function ConfirmDialogProvider({ children }: { children: ReactNode }) {
    const [options, setOptions] = useState<ConfirmDialogOptions | null>(null);
    const open = options !== null;

    const value = useMemo<ConfirmDialogContextValue>(
        () => ({
            confirm: (nextOptions) => setOptions(nextOptions),
        }),
        [],
    );

    async function handleConfirm() {
        await options?.onConfirm();
        setOptions(null);
    }

    return (
        <ConfirmDialogContext.Provider value={value}>
            {children}
            <AlertDialog
                open={open}
                onOpenChange={(nextOpen) => {
                    if (!nextOpen) {
                        setOptions(null);
                    }
                }}
            >
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>{options?.title}</AlertDialogTitle>
                        {options?.description && (
                            <AlertDialogDescription>
                                {options.description}
                            </AlertDialogDescription>
                        )}
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>
                            {options?.cancelLabel ?? 'Cancel'}
                        </AlertDialogCancel>
                        <AlertDialogAction
                            variant={options?.variant ?? 'default'}
                            onClick={(event) => {
                                event.preventDefault();
                                void handleConfirm();
                            }}
                        >
                            {options?.confirmLabel ?? 'Continue'}
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </ConfirmDialogContext.Provider>
    );
}

export function useConfirmDialog() {
    const context = useContext(ConfirmDialogContext);

    if (!context) {
        throw new Error(
            'useConfirmDialog must be used within ConfirmDialogProvider.',
        );
    }

    return context.confirm;
}
