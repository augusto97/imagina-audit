import { forwardRef } from 'react'
import type { InputHTMLAttributes } from 'react'
import { cn } from '@/lib/utils'

/**
 * Switch minimal — checkbox HTML estilizado como toggle. Soporta dos
 * APIs:
 *  - controlado al estilo Radix: <Switch checked={x} onCheckedChange={fn} />
 *  - register de react-hook-form: <Switch {...register('flag')} />
 *
 * No depende de Radix Switch (ahorramos una dependencia más). El input
 * real queda invisible vía sr-only y el estado visual lo lleva el span
 * peer-checked al lado.
 */
interface SwitchProps extends Omit<InputHTMLAttributes<HTMLInputElement>, 'type' | 'onChange'> {
  onCheckedChange?: (checked: boolean) => void
  onChange?: InputHTMLAttributes<HTMLInputElement>['onChange']
}

export const Switch = forwardRef<HTMLInputElement, SwitchProps>(
  ({ className, onCheckedChange, onChange, disabled, ...props }, ref) => {
    return (
      <label className={cn('relative inline-flex items-center cursor-pointer select-none', disabled && 'opacity-60 cursor-not-allowed', className)}>
        <input
          ref={ref}
          type="checkbox"
          className="peer sr-only"
          disabled={disabled}
          onChange={(e) => {
            onChange?.(e)
            onCheckedChange?.(e.target.checked)
          }}
          {...props}
        />
        <span
          className={cn(
            'h-5 w-9 rounded-full bg-[var(--bg-tertiary)] transition-colors',
            'peer-checked:bg-[var(--accent-primary)]',
            'peer-focus-visible:ring-2 peer-focus-visible:ring-[var(--accent-primary)]/40',
            'after:absolute after:top-0.5 after:left-0.5 after:h-4 after:w-4',
            'after:rounded-full after:bg-white after:shadow-sm after:transition-transform',
            'peer-checked:after:translate-x-4'
          )}
        />
      </label>
    )
  }
)
Switch.displayName = 'Switch'
