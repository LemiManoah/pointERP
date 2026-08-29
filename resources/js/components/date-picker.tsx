import { format, isValid, parseISO } from 'date-fns';
import { CalendarIcon, X } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Calendar } from '@/components/ui/calendar';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { cn } from '@/lib/utils';

type Props = {
    value: string;
    onChange: (value: string) => void;
    placeholder?: string;
    disabled?: boolean;
    id?: string;
    className?: string;
};

export function DatePicker({
    value,
    onChange,
    placeholder = 'Select date',
    disabled = false,
    id,
    className,
}: Props) {
    const parsed = value ? parseISO(value) : undefined;
    const selected = parsed && isValid(parsed) ? parsed : undefined;

    return (
        <div className={cn('flex min-w-0 gap-2', className)}>
            <Popover>
                <PopoverTrigger asChild>
                    <Button
                        id={id}
                        type="button"
                        variant="outline"
                        disabled={disabled}
                        className={cn(
                            'min-w-0 flex-1 justify-start overflow-hidden text-left font-normal',
                            !selected && 'text-muted-foreground',
                        )}
                    >
                        <CalendarIcon className="size-4 shrink-0" />
                        <span className="truncate">
                            {selected
                                ? format(selected, 'dd MMM yyyy')
                                : placeholder}
                        </span>
                    </Button>
                </PopoverTrigger>
                <PopoverContent className="w-auto p-0" align="start">
                    <Calendar
                        mode="single"
                        selected={selected}
                        onSelect={(date) =>
                            onChange(date ? format(date, 'yyyy-MM-dd') : '')
                        }
                        captionLayout="dropdown"
                        autoFocus
                    />
                </PopoverContent>
            </Popover>
            {selected && !disabled && (
                <Button
                    type="button"
                    variant="outline"
                    size="icon"
                    onClick={() => onChange('')}
                    title="Clear date"
                >
                    <X className="size-4" />
                    <span className="sr-only">Clear date</span>
                </Button>
            )}
        </div>
    );
}
