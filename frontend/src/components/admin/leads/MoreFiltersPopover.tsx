import { useState, useRef, useEffect } from 'react'
import { useTranslation } from 'react-i18next'
import { SlidersHorizontal, ChevronDown } from 'lucide-react'
import { Button } from '@/components/ui/button'

type Dimensional = 'any' | 'yes' | 'no'

/**
 * Mini-popover con los filtros inversos que los tiles no cubren:
 * sólo sin WP, sólo sin snapshot, sólo sin proteger. También expone
 * los filtros temporales secundarios (warning, this_month).
 *
 * Mantiene el dropdown sin dependencia extra — click-outside cierra.
 */
export function MoreFiltersPopover({
  mainFilter,
  onMainFilterChange,
  filterWp,
  onFilterWpChange,
  filterSnap,
  onFilterSnapChange,
  filterPinned,
  onFilterPinnedChange,
}: {
  mainFilter: string
  onMainFilterChange: (v: string) => void
  filterWp: Dimensional
  onFilterWpChange: (v: Dimensional) => void
  filterSnap: Dimensional
  onFilterSnapChange: (v: Dimensional) => void
  filterPinned: Dimensional
  onFilterPinnedChange: (v: Dimensional) => void
}) {
  const { t } = useTranslation()
  const [open, setOpen] = useState(false)
  const ref = useRef<HTMLDivElement>(null)

  useEffect(() => {
    if (!open) return
    const onDown = (e: MouseEvent) => {
      if (ref.current && !ref.current.contains(e.target as Node)) setOpen(false)
    }
    document.addEventListener('mousedown', onDown)
    return () => document.removeEventListener('mousedown', onDown)
  }, [open])

  return (
    <div ref={ref} className="relative">
      <Button variant="outline" size="sm" onClick={() => setOpen(o => !o)}>
        <SlidersHorizontal className="h-4 w-4" strokeWidth={1.5} />
        <span className="hidden sm:inline">{t('leads.more_filters')}</span>
        <ChevronDown className={`h-3 w-3 transition-transform ${open ? 'rotate-180' : ''}`} />
      </Button>
      {open && (
        <div className="absolute right-0 z-20 mt-1 w-64 rounded-lg border border-[var(--border-default)] bg-white p-3 shadow-lg">
          <TriToggle
            label={t('leads.more_filters_section_wordpress')}
            value={filterWp}
            onChange={onFilterWpChange}
          />
          <TriToggle
            label={t('leads.more_filters_section_snapshot')}
            value={filterSnap}
            onChange={onFilterSnapChange}
          />
          <TriToggle
            label={t('leads.more_filters_section_pinned')}
            value={filterPinned}
            onChange={onFilterPinnedChange}
          />

          <div className="mt-2 border-t border-[var(--border-default)] pt-2">
            <p className="mb-1 text-[10px] font-semibold uppercase tracking-wider text-[var(--text-tertiary)]">
              {t('leads.more_filters_section_temporal')}
            </p>
            <div className="flex flex-wrap gap-1">
              {[
                { v: 'all', l: t('leads.more_filters_all') },
                { v: 'this_week', l: t('leads.more_filters_last_7d') },
                { v: 'this_month', l: t('leads.more_filters_last_30d') },
              ].map((o) => (
                <button
                  key={o.v}
                  type="button"
                  onClick={() => onMainFilterChange(o.v)}
                  className={`rounded-full px-2 py-0.5 text-[11px] font-medium transition-colors ${
                    mainFilter === o.v
                      ? 'bg-[var(--accent-primary)] text-white'
                      : 'border border-[var(--border-default)] bg-white text-[var(--text-secondary)] hover:border-[var(--text-tertiary)]'
                  }`}
                >
                  {o.l}
                </button>
              ))}
            </div>

            <p className="mt-2 mb-1 text-[10px] font-semibold uppercase tracking-wider text-[var(--text-tertiary)]">
              {t('leads.more_filters_section_score')}
            </p>
            <div className="flex flex-wrap gap-1">
              {[
                { v: 'all', l: t('leads.more_filters_score_any') },
                { v: 'critical', l: t('leads.more_filters_score_critical') },
                { v: 'warning', l: t('leads.more_filters_score_warning') },
              ].map((o) => (
                <button
                  key={o.v}
                  type="button"
                  onClick={() => onMainFilterChange(o.v)}
                  className={`rounded-full px-2 py-0.5 text-[11px] font-medium transition-colors ${
                    mainFilter === o.v
                      ? 'bg-[var(--accent-primary)] text-white'
                      : 'border border-[var(--border-default)] bg-white text-[var(--text-secondary)] hover:border-[var(--text-tertiary)]'
                  }`}
                >
                  {o.l}
                </button>
              ))}
            </div>
          </div>
        </div>
      )}
    </div>
  )
}

function TriToggle({
  label, value, onChange,
}: {
  label: string
  value: Dimensional
  onChange: (v: Dimensional) => void
}) {
  const { t } = useTranslation()
  return (
    <div className="mb-2">
      <p className="mb-1 text-xs font-medium text-[var(--text-primary)]">{label}</p>
      <div className="flex gap-1">
        {[
          { v: 'any' as const, l: t('leads.more_filters_any') },
          { v: 'yes' as const, l: t('leads.more_filters_only_with') },
          { v: 'no' as const,  l: t('leads.more_filters_only_without') },
        ].map((o) => (
          <button
            key={o.v}
            type="button"
            onClick={() => onChange(o.v)}
            className={`flex-1 rounded-md px-2 py-1 text-[11px] font-medium transition-colors ${
              value === o.v
                ? 'bg-[var(--accent-primary)] text-white'
                : 'bg-[var(--bg-secondary)] text-[var(--text-secondary)] hover:bg-[var(--border-default)]'
            }`}
          >
            {o.l}
          </button>
        ))}
      </div>
    </div>
  )
}
