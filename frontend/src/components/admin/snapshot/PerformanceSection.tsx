import { Gauge, Zap, Database as DbIcon, Image as ImageIcon, Link as LinkIcon } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { SectionCard, IssueList } from './ui'
import type { SnapshotReport } from '@/types/snapshotReport'

/**
 * Cache stack + image editor + permalinks. Cada item es un "estado" claro
 * (activo/no) con la acción recomendada para habilitarlo.
 */
export default function PerformanceSection({ report }: { report: SnapshotReport }) {
  const { t } = useTranslation()
  const s = report.performance.summary

  return (
    <SectionCard
      title={t('report.snap_perf_title')}
      subtitle={t('report.snap_perf_subtitle')}
      icon={<Gauge className="h-4 w-4 text-[var(--accent-primary)]" strokeWidth={1.75} />}
    >
      {/* Cache stack as a stat grid */}
      <div className="mb-4 grid grid-cols-1 gap-2 sm:grid-cols-3">
        <CacheTile
          icon={<LinkIcon className="h-3.5 w-3.5" />}
          label={t('report.snap_perf_kpi_page_cache')}
          active={Boolean(s.pageCache)}
          detail={s.pageCache ? t('report.snap_perf_page_cache_on') : t('report.snap_perf_page_cache_off')}
        />
        <CacheTile
          icon={<DbIcon className="h-3.5 w-3.5" />}
          label={t('report.snap_perf_kpi_object_cache')}
          active={Boolean(s.objectCache)}
          detail={s.objectCache ? String(s.objectCacheType || t('report.snap_perf_state_enabled')) : t('report.snap_perf_cache_default')}
        />
        <CacheTile
          icon={<Zap className="h-3.5 w-3.5" />}
          label={t('report.snap_perf_kpi_opcache')}
          active={Boolean(s.opcache)}
          detail={s.opcache ? t('report.snap_perf_opcache_on') : t('report.snap_perf_opcache_off')}
        />
      </div>

      {/* Otros datos de rendimiento */}
      <div className="mb-4 grid gap-2 sm:grid-cols-2">
        <InfoRow
          icon={<ImageIcon className="h-3.5 w-3.5" />}
          label={t('report.snap_perf_kpi_image_editor')}
          value={String(s.imageEditor || '—')}
          good={(s.imageEditor as string || '').toLowerCase().includes('imagick')}
        />
        <InfoRow
          icon={<LinkIcon className="h-3.5 w-3.5" />}
          label={t('report.snap_perf_kpi_permalinks')}
          value={String(s.permalinks || '—')}
          mono
          good={Boolean(s.permalinks) && !String(s.permalinks).startsWith('/?')}
        />
      </div>

      <h4 className="mb-2 text-xs font-semibold uppercase tracking-wider text-[var(--text-tertiary)]">
        {t('report.snap_perf_actionable')}
      </h4>
      <IssueList issues={report.performance.issues} />
    </SectionCard>
  )
}

function CacheTile({ icon, label, active, detail }: { icon: React.ReactNode; label: string; active: boolean; detail: string }) {
  return (
    <div className={`rounded-lg border p-3 ${active ? 'border-emerald-200 bg-emerald-50' : 'border-amber-200 bg-amber-50'}`}>
      <div className="flex items-center gap-1.5">
        <span className={active ? 'text-emerald-600' : 'text-amber-600'}>{icon}</span>
        <span className="text-[10px] uppercase tracking-wider font-semibold text-[var(--text-secondary)]">{label}</span>
        <span className={`ml-auto rounded px-1.5 py-0.5 text-[10px] font-bold ${
          active ? 'bg-emerald-200 text-emerald-900' : 'bg-amber-200 text-amber-900'
        }`}>
          {active ? 'ON' : 'OFF'}
        </span>
      </div>
      <p className="mt-1.5 text-[11px] text-[var(--text-secondary)]">{detail}</p>
    </div>
  )
}

function InfoRow({ icon, label, value, good, mono }: { icon: React.ReactNode; label: string; value: string; good?: boolean; mono?: boolean }) {
  return (
    <div className="flex items-center gap-2 rounded-lg border border-[var(--border-default)] px-3 py-2">
      <span className="text-[var(--text-tertiary)]">{icon}</span>
      <span className="text-[10px] uppercase tracking-wider text-[var(--text-tertiary)]">{label}</span>
      <span className={`ml-auto text-xs ${mono ? 'font-mono' : ''} ${good ? 'text-emerald-700 font-medium' : 'text-[var(--text-primary)]'}`}>
        {value}
      </span>
    </div>
  )
}
