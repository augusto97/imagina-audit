import { describe, it, expect } from 'vitest'
import { cn, formatCurrency, formatMs, getLevelColor, getLevelClassName, getLevelLabel } from './utils'

describe('cn', () => {
  it('merges tailwind classes deduplicating conflicts', () => {
    // twMerge deja solo la última clase conflictiva
    expect(cn('p-2', 'p-4')).toBe('p-4')
    expect(cn('text-red-500', 'text-blue-500')).toBe('text-blue-500')
  })

  it('handles conditional classes', () => {
    expect(cn('base', { active: true, disabled: false })).toContain('base')
    expect(cn('base', { active: true, disabled: false })).toContain('active')
    expect(cn('base', { active: true, disabled: false })).not.toContain('disabled')
  })

  it('ignores falsy inputs', () => {
    expect(cn('foo', null, undefined, false, 'bar')).toBe('foo bar')
  })
})

describe('formatMs', () => {
  it('formats milliseconds when under 1s', () => {
    expect(formatMs(500)).toBe('500ms')
    expect(formatMs(999)).toBe('999ms')
  })

  it('formats seconds when >= 1s', () => {
    expect(formatMs(1000)).toBe('1.0s')
    expect(formatMs(2500)).toBe('2.5s')
    expect(formatMs(15432)).toBe('15.4s')
  })

  it('rounds ms to integer', () => {
    expect(formatMs(123.7)).toBe('124ms')
  })
})

describe('formatCurrency', () => {
  it('formats USD without decimals by default', () => {
    const out = formatCurrency(1234)
    expect(out).toContain('1')
    expect(out).toContain('234')
    expect(out).not.toContain('.00')
    expect(out).not.toContain(',00')
  })

  it('accepts custom currency', () => {
    const out = formatCurrency(100, 'EUR')
    expect(out).toMatch(/€|EUR/)
  })
})

describe('getLevelColor', () => {
  it('returns CSS variable references for known levels', () => {
    expect(getLevelColor('critical')).toBe('var(--color-critical)')
    expect(getLevelColor('warning')).toBe('var(--color-warning)')
    expect(getLevelColor('good')).toBe('var(--color-good)')
    expect(getLevelColor('excellent')).toBe('var(--color-excellent)')
  })

  it('falls back to info color for unknown levels', () => {
    expect(getLevelColor('unknown')).toBe('var(--color-info)')
    expect(getLevelColor('bogus')).toBe('var(--color-info)')
  })
})

describe('getLevelClassName', () => {
  it('returns Tailwind classes for known levels', () => {
    expect(getLevelClassName('critical')).toBe('text-red-500')
    expect(getLevelClassName('excellent')).toBe('text-emerald-600')
  })

  it('falls back to info class for unknown levels', () => {
    expect(getLevelClassName('bogus')).toBe('text-gray-500')
  })
})

describe('getLevelLabel', () => {
  it('returns Spanish labels', () => {
    expect(getLevelLabel('critical')).toBe('Crítico')
    expect(getLevelLabel('warning')).toBe('Importante')
    expect(getLevelLabel('good')).toBe('Bien')
    expect(getLevelLabel('excellent')).toBe('Excelente')
  })

  it('falls back to "No disponible" for unknown levels', () => {
    expect(getLevelLabel('bogus')).toBe('No disponible')
  })
})
