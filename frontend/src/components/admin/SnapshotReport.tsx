import { useEffect, useState, useCallback } from 'react'
import { useParams } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { Loader2, Database, RefreshCw } from 'lucide-react'
import { Card, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { useAdmin } from '@/hooks/useAdmin'
import LeadReportNav from './LeadReportNav'
import SnapshotUploader from './SnapshotUploader'
import OverviewSection from './snapshot/OverviewSection'
import PluginsSection from './snapshot/PluginsSection'
import SecuritySection from './snapshot/SecuritySection'
import EnvironmentSection from './snapshot/EnvironmentSection'
import PerformanceSection from './snapshot/PerformanceSection'
import DatabaseSection from './snapshot/DatabaseSection'
import ThemesSection from './snapshot/ThemesSection'
import CronSection from './snapshot/CronSection'
import MediaSection from './snapshot/MediaSection'
import UsersSection from './snapshot/UsersSection'
import ContentSection from './snapshot/ContentSection'
import type { SnapshotReportResponse } from '@/types/snapshotReport'

/**
 * Página "Análisis interno" de una auditoría.
 * Alimentada por /admin/snapshot-report.php que ya devuelve los datos pre-
 * estructurados desde el SnapshotReportBuilder del backend.
 *
 * Si no hay snapshot, muestra la zona de upload.
 * Si hay snapshot, muestra el dashboard con todas las secciones.
 */

interface SnapshotReportProps {
  /** Override del fetcher. Default: useAdmin().fetchSnapshotReport */
  fetcher?: (auditId: string) => Promise<SnapshotReportResponse | null>
  basePath?: string
  backTo?: string | null
  /** Si true, oculta el uploader (admin-only) cuando no hay snapshot. */
  hideUploader?: boolean
}

export default function SnapshotReport({ fetcher, basePath, backTo, hideUploader = false }: SnapshotReportProps = {}) {
  const { t, i18n } = useTranslation()
  const { id } = useParams<{ id: string }>()
  const { fetchSnapshotReport: adminFetch } = useAdmin()
  const fetchSnapshotReport = fetcher ?? adminFetch
  const [data, setData] = useState<SnapshotReportResponse | null>(null)
  const [loading, setLoading] = useState(true)

  const load = useCallback(async () => {
    if (!id) return
    setLoading(true)
    const res = await fetchSnapshotReport(id)
    setData(res || null)
    setLoading(false)
  }, [id, fetchSnapshotReport])

  useEffect(() => { load() }, [load])

  if (loading) {
    return (
      <div className="flex items-center justify-center py-16">
        <Loader2 className="h-6 w-6 animate-spin text-[var(--text-tertiary)]" />
      </div>
    )
  }

  // Sin snapshot → mostrar upload zone (o mensaje vacío para user)
  if (!data && id) {
    return (
      <div className="space-y-4">
        <LeadReportNav auditId={id} basePath={basePath} backTo={backTo} />
        <div>
          <h2 className="text-lg font-semibold text-[var(--text-primary)]">{t('settings.snapshot_title')}</h2>
          <p className="text-sm text-[var(--text-secondary)] mt-1">
            {t('settings.snapshot_intro_prefix')}{' '}
            <a href="https://github.com/mrabro/wp-snapshot" target="_blank" rel="noreferrer" className="text-[var(--accent-primary)] hover:underline">
              wp-snapshot
            </a>{t('settings.snapshot_intro_suffix')}
          </p>
        </div>
        {!hideUploader && <SnapshotUploader auditId={id} onChange={load} />}
        <Card>
          <CardContent className="py-6 text-sm text-[var(--text-secondary)]">
            <p className="font-medium text-[var(--text-primary)] mb-2">{t('settings.snapshot_how_title')}</p>
            <ol className="list-decimal list-inside space-y-1 ml-2">
              <li>{t('settings.snapshot_how_1')}</li>
              <li>{t('settings.snapshot_how_2')}</li>
              <li dangerouslySetInnerHTML={{ __html: t('settings.snapshot_how_3') }} />
              <li>{t('settings.snapshot_how_4')}</li>
            </ol>
          </CardContent>
        </Card>
      </div>
    )
  }

  if (!data || !id) return null

  const { meta, report } = data

  return (
    <div className="space-y-5">
      <LeadReportNav auditId={id} domain={meta.siteName || meta.siteUrl} basePath={basePath} backTo={backTo} />

      {/* Header */}
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h2 className="text-lg font-semibold text-[var(--text-primary)] flex items-center gap-2">
            <Database className="h-5 w-5 text-[var(--accent-primary)]" strokeWidth={1.5} />
            {t('settings.snapshot_title')}
          </h2>
          <p className="text-sm text-[var(--text-secondary)] mt-1">
            {meta.siteName ? <><b>{meta.siteName}</b> · </> : null}
            {meta.siteUrl && <span className="font-mono">{meta.siteUrl}</span>}
          </p>
          <p className="text-xs text-[var(--text-tertiary)] mt-0.5">
            {t('settings.snapshot_generated_at', { date: meta.generatedAt })} · {t('settings.snapshot_uploaded', { date: new Date(meta.uploadedAt).toLocaleString(i18n.language) })} · {t('settings.snapshot_plugin_version', { version: meta.generatorVersion })}
          </p>
        </div>
        <Button size="sm" variant="outline" onClick={load}>
          <RefreshCw className="h-3.5 w-3.5" strokeWidth={1.5} /> {t('settings.snapshot_refresh')}
        </Button>
      </div>

      <OverviewSection report={report} />
      <PluginsSection report={report} />
      <SecuritySection report={report} />
      <EnvironmentSection report={report} />
      <PerformanceSection report={report} />
      <DatabaseSection report={report} />
      <ThemesSection report={report} />
      <CronSection report={report} />
      <MediaSection report={report} />
      <UsersSection report={report} />
      <ContentSection report={report} />
    </div>
  )
}


