import * as React from "react"

import { cn } from "@/lib/utils"

function rawNumericValue(value: string): string {
  const withoutSeparators = value.replaceAll(",", "").trim()
  const sign = withoutSeparators.startsWith("-") ? "-" : ""
  const unsigned = withoutSeparators.replaceAll("-", "").replace(/[^\d.]/g, "")
  const decimalAt = unsigned.indexOf(".")
  const integer = decimalAt === -1 ? unsigned : unsigned.slice(0, decimalAt)
  const decimal = decimalAt === -1 ? null : unsigned.slice(decimalAt + 1).replaceAll(".", "")

  return sign + integer + (decimal === null ? "" : "." + decimal)
}

function formattedNumericValue(
  value: React.ComponentProps<"input">["value"]
): string {
  if (value === undefined || value === "") {
    return ""
  }

  const raw = rawNumericValue(String(value))
  const sign = raw.startsWith("-") ? "-" : ""
  const unsigned = sign ? raw.slice(1) : raw
  const [integer = "", decimal] = unsigned.split(".", 2)
  const groupedInteger = integer.replace(/\B(?=(\d{3})+(?!\d))/g, ",")

  return sign + groupedInteger + (decimal === undefined ? "" : "." + decimal)
}

function Input({
  className,
  type,
  value,
  defaultValue,
  onChange,
  inputMode,
  ...props
}: React.ComponentProps<"input">) {
  const isNumeric = type === "number"
  const [internalValue, setInternalValue] = React.useState(() =>
    isNumeric ? formattedNumericValue(defaultValue) : ""
  )

  if (!isNumeric) {
    return (
      <input
        type={type}
        data-slot="input"
        className={cn(
          "h-9 w-full min-w-0 rounded-md border border-input bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none selection:bg-primary selection:text-primary-foreground file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium file:text-foreground placeholder:text-muted-foreground disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm dark:bg-input/30",
          "focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50",
          "aria-invalid:border-destructive aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40",
          className
        )}
        value={value}
        defaultValue={defaultValue}
        onChange={onChange}
        inputMode={inputMode}
        {...props}
      />
    )
  }

  const displayValue =
    value === undefined ? internalValue : formattedNumericValue(value)

  return (
    <input
      type="text"
      inputMode={inputMode ?? "decimal"}
      data-slot="input"
      className={cn(
        "h-9 w-full min-w-0 rounded-md border border-input bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none selection:bg-primary selection:text-primary-foreground file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium file:text-foreground placeholder:text-muted-foreground disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm dark:bg-input/30",
        "focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50",
        "aria-invalid:border-destructive aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40",
        className
      )}
      value={displayValue}
      onChange={(event) => {
        const raw = rawNumericValue(event.currentTarget.value)
        if (value === undefined) {
          setInternalValue(formattedNumericValue(raw))
        }

        event.currentTarget.value = raw
        onChange?.(event)
      }}
      {...props}
    />
  )
}

export { Input }
