import * as React from 'react';
import { Input } from '@/shared/components/ui/input';
import {
  caretAfterSignificant,
  countSignificantBefore,
  formatRupiahInput,
  normalizeRupiahValue,
  parseRupiahInput,
} from '@/shared/lib/currency-input';
import { cn } from '@/shared/lib/utils';

type CurrencyInputProps = Omit<React.InputHTMLAttributes<HTMLInputElement>, 'value' | 'onChange' | 'type'> & {
  /** Raw numeric string as stored in the form ("15000", "15000.5"), or a server value ("15000.00"). */
  value: string | number | null | undefined;
  /** Receives the raw numeric string, ready to submit — never the formatted text. */
  onValueChange: (raw: string) => void;
};

/**
 * Rupiah amount input shown as "Rp 15.000" while the form keeps "15000".
 * Use for every manually typed price/nominal so a missing or extra zero is
 * visible at a glance.
 */
const CurrencyInput = React.forwardRef<HTMLInputElement, CurrencyInputProps>(({ value, onValueChange, className, ...props }, forwardedRef) => {
  const innerRef = React.useRef<HTMLInputElement>(null);
  React.useImperativeHandle(forwardedRef, () => innerRef.current as HTMLInputElement);

  // Our own emitted raw string is shown as-is (so "15000," stays while typing
  // decimals); anything else came from outside (server/reset) and is normalized.
  const lastEmitted = React.useRef<string | null>(null);
  const incoming = value === null || value === undefined ? '' : String(value);
  const raw = incoming === lastEmitted.current ? incoming : normalizeRupiahValue(incoming);
  const display = formatRupiahInput(raw);

  const pendingCaret = React.useRef<number | null>(null);
  React.useLayoutEffect(() => {
    const input = innerRef.current;
    if (pendingCaret.current === null || !input || document.activeElement !== input) return;
    const pos = caretAfterSignificant(input.value, pendingCaret.current);
    input.setSelectionRange(pos, pos);
    pendingCaret.current = null;
  });

  const handleChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const text = e.target.value;
    const caret = e.target.selectionStart ?? text.length;
    // Deleting the decimal comma drops the decimals instead of merging them
    // into the integer part (15.000,5 → 15.000, not 150.005).
    const commaDeleted = display.includes(',') && !text.includes(',');
    const next = parseRupiahInput(commaDeleted ? text.slice(0, caret) : text);
    pendingCaret.current = countSignificantBefore(text, caret);
    lastEmitted.current = next;
    onValueChange(next);
  };

  return (
    <div className={cn('relative w-full', className)}>
      <span className="pointer-events-none absolute inset-y-0 left-3 flex items-center text-sm text-gray-500 dark:text-gray-400">Rp</span>
      <Input
        {...props}
        ref={innerRef}
        type="text"
        inputMode="decimal"
        autoComplete="off"
        className="pl-9 text-right tabular-nums"
        value={display}
        onChange={handleChange}
      />
    </div>
  );
});
CurrencyInput.displayName = 'CurrencyInput';

export { CurrencyInput };
