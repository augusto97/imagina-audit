import { useEffect, useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Languages, Check, ChevronDown, Loader2 } from 'lucide-react'
import { changeLanguageSafe } from '@/i18n'
import { useLanguagesStore } from '@/store/languagesStore'

/**
 * Selector de idioma. La lista viene dinámicamente del backend vía
 * `useLanguagesStore`. Si un idioma seleccionado aún no tiene bundle
 * cargado, `changeLanguageSafe` lo baja de la API antes de aplicar el
 * cambio para evitar flash de strings en inglés.
 *
 * Variante 'compact' solo muestra el código (EN/ES/PT…) — ideal para
 * headers con poco espacio. Variante 'full' muestra Languages icon +
 * nombre completo.
 */

export function LanguageSwitcher({
  variant = 'compact',
  align = 'right',
}: {
  variant?: 'compact' | 'full'
  align?: 'left' | 'right'
}) {
  const { i18n } = useTranslation()
  const { languages, loaded, load } = useLanguagesStore()
  const [open, setOpen] = useState(false)
  const [switching, setSwitching] = useState(false)
  const ref = useRef<HTMLDivElement>(null)

  const current = (i18n.resolvedLanguage || i18n.language || 'en').slice(0, 2)

  useEffect(() => {
    if (!loaded) load()
  }, [loaded, load])

  useEffect(() => {
    if (!open) return
    const onDown = (e: MouseEvent) => {
      if (ref.current && !ref.current.contains(e.target as Node)) setOpen(false)
    }
    document.addEventListener('mousedown', onDown)
    return () => document.removeEventListener('mousedown', onDown)
  }, [open])

  const changeTo = async (code: string) => {
    setOpen(false)
    if (code === current) return
    setSwitching(true)
    try {
      await changeLanguageSafe(code)
    } finally {
      setSwitching(false)
    }
  }

  // Si el idioma activo no está en la lista pública (p.ej. idioma ya no
  // expuesto), no romper — mostrar el código en mayúsculas.
  const activeEntry = languages.find(l => l.code === current)
  const displayLabel = (code: string) => {
    const entry = languages.find(l => l.code === code)
    return entry?.nativeName ?? code.toUpperCase()
  }

  // Si solo hay un idioma disponible y coincide con el activo, no tiene
  // sentido pintar el switcher — lo ocultamos.
  if (languages.length <= 1) return null

  return (
    <div ref={ref} className="relative">
      <button
        type="button"
        onClick={() => setOpen(o => !o)}
        aria-label="Change language"
        className={`inline-flex items-center gap-1 rounded-md px-2 py-1 text-xs font-medium text-[var(--text-secondary)] transition-colors hover:bg-[var(--bg-secondary)] hover:text-[var(--text-primary)]`}
      >
        {variant === 'full' && <Languages className="h-3.5 w-3.5" strokeWidth={1.5} />}
        {switching
          ? <Loader2 className="h-3 w-3 animate-spin" strokeWidth={2} />
          : <span className="font-mono uppercase">{current}</span>}
        <ChevronDown className={`h-3 w-3 transition-transform ${open ? 'rotate-180' : ''}`} />
      </button>

      {open && (
        <div className={`absolute z-50 mt-1 min-w-[150px] overflow-hidden rounded-lg border border-[var(--border-default)] bg-white shadow-lg ${align === 'right' ? 'right-0' : 'left-0'}`}>
          {languages.map((lang) => {
            const isActive = current === lang.code
            return (
              <button
                key={lang.code}
                type="button"
                onClick={() => changeTo(lang.code)}
                className={`flex w-full items-center gap-2 px-3 py-1.5 text-left text-xs transition-colors ${
                  isActive
                    ? 'bg-[var(--accent-primary)]/10 text-[var(--accent-primary)] font-medium'
                    : 'text-[var(--text-primary)] hover:bg-[var(--bg-secondary)]'
                }`}
              >
                <span className="font-mono text-[10px] uppercase opacity-70">{lang.code}</span>
                <span className="flex-1">{displayLabel(lang.code)}</span>
                {isActive && <Check className="h-3 w-3" strokeWidth={2} />}
              </button>
            )
          })}
          {!activeEntry && (
            <div className="border-t border-[var(--border-default)] px-3 py-1.5 text-[10px] italic text-[var(--text-tertiary)]">
              Active: {current.toUpperCase()}
            </div>
          )}
        </div>
      )}
    </div>
  )
}
