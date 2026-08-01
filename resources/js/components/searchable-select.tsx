import { Check, ChevronsUpDown } from 'lucide-react';
import { useMemo, useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from '@/components/ui/command';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { cn } from '@/lib/utils';

export type SearchableSelectOption = {
    value: string;
    label: string;
    description?: string;
    disabled?: boolean;
};

type Props = {
    value: string;
    options: SearchableSelectOption[];
    onValueChange: (value: string) => void;
    placeholder?: string;
    searchPlaceholder?: string;
    emptyMessage?: string;
    className?: string;
};

export function SearchableSelect({
    value,
    options,
    onValueChange,
    placeholder = 'Select option',
    searchPlaceholder = 'Search...',
    emptyMessage = 'No options found.',
    className,
}: Props) {
    const [open, setOpen] = useState(false);
    const selectedOption = useMemo(
        () => options.find((option) => option.value === value),
        [options, value],
    );

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>
                <Button
                    type="button"
                    variant="outline"
                    role="combobox"
                    aria-expanded={open}
                    className={cn(
                        'w-full justify-between px-3 font-normal',
                        !selectedOption && 'text-muted-foreground',
                        className,
                    )}
                >
                    <span className="truncate">
                        {selectedOption?.label ?? placeholder}
                    </span>
                    <ChevronsUpDown className="opacity-50" />
                </Button>
            </PopoverTrigger>
            <PopoverContent
                align="start"
                className="w-[var(--radix-popover-trigger-width)] p-0"
            >
                <Command>
                    <CommandInput placeholder={searchPlaceholder} />
                    <CommandList>
                        <CommandEmpty>{emptyMessage}</CommandEmpty>
                        <CommandGroup>
                            {options.map((option) => (
                                <CommandItem
                                    key={option.value}
                                    value={`${option.label} ${option.description ?? ''}`}
                                    disabled={option.disabled}
                                    onSelect={() => {
                                        if (option.disabled) {
                                            return;
                                        }

                                        onValueChange(option.value);
                                        setOpen(false);
                                    }}
                                >
                                    <div className="min-w-0 flex-1">
                                        <div className="truncate">
                                            {option.label}
                                        </div>
                                        {option.description && (
                                            <div className="truncate text-xs text-muted-foreground">
                                                {option.description}
                                            </div>
                                        )}
                                    </div>
                                    <Check
                                        className={cn(
                                            'ml-auto',
                                            value === option.value
                                                ? 'opacity-100'
                                                : 'opacity-0',
                                        )}
                                    />
                                </CommandItem>
                            ))}
                        </CommandGroup>
                    </CommandList>
                </Command>
            </PopoverContent>
        </Popover>
    );
}
