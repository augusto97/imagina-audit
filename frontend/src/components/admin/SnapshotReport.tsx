import { useEffect, useState, useCallback } from 'react'
import { Link, useParams } from 'react-router-dom'
import { ArrowLeft, Loader2, Database, FileDown, RefreshCw } from 'lucide-react'
import { Card, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { useAdmin } from '@/hooks/useAdmin'
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

export default function SnapshotReport() {
  const { id } = useParams<{ id: string }>()
  const { fetchSnapshotReport } = useAdmin()
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

  // Sin snapshot → mostrar upload zone
  if (!data && id) {
    return (
      <div className="space-y-4">
        <BackNav auditId={id} />
        <div>
          <h1 className="text-2xl font-bold text-[var(--text-primary)]">Análisis interno</h1>
          <p className="text-sm text-[var(--text-secondary)] mt-1">
            Datos internos del sitio que no se pueden obtener desde fuera (plugins con versiones reales, base
            de datos, cron, seguridad interna). Requiere subir el JSON del plugin{' '}
            <a href="https://github.com/mrabro/wp-snapshot" target="_blank" rel="noreferrer" className="text-[var(--accent-primary)] hover:underline">
              wp-snapshot
            </a>.
          </p>
        </div>
        <SnapshotUploader auditId={id} onChange={load} />
        <Card>
          <CardContent className="py-6 text-sm text-[var(--text-secondary)]">
            <p className="font-medium text-[var(--text-primary)] mb-2">Cómo obtener el JSON</p>
            <ol className="list-decimal list-inside space-y-1 ml-2">
              <li>Pide al cliente instalar el plugin wp-snapshot en su WordPress.</li>
              <li>Entrar a WP Admin → Herramientas → Site Audit Snapshot.</li>
              <li>Click en <b>Download JSON</b>.</li>
              <li>Subir ese archivo aquí arriba.</li>
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
      <BackNav auditId={id} />

      {/* Header */}
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h1 className="text-2xl font-bold text-[var(--text-primary)] flex items-center gap-2">
            <Database className="h-6 w-6 text-[var(--accent-primary)]" strokeWidth={1.5} />
            Análisis interno
          </h1>
          <p className="text-sm text-[var(--text-secondary)] mt-1">
            {meta.siteName ? <><b>{meta.siteName}</b> · </> : null}
            {meta.siteUrl && <span className="font-mono">{meta.siteUrl}</span>}
          </p>
          <p className="text-xs text-[var(--text-tertiary)] mt-0.5">
            Snapshot generado: {meta.generatedAt} · Subido: {new Date(meta.uploadedAt).toLocaleString('es-CO')} · Plugin v{meta.generatorVersion}
          </p>
        </div>
        <div className="flex gap-2">
          <Button size="sm" variant="outline" onClick={load}>
            <RefreshCw className="h-3.5 w-3.5" strokeWidth={1.5} /> Refrescar
          </Button>
          <Button size="sm" variant="outline" disabled title="Próximamente">
            <FileDown className="h-3.5 w-3.5" strokeWidth={1.5} /> Exportar
          </Button>
        </div>
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

function BackNav({ auditId }: { auditId: string }) {
  return (
    <div className="flex items-center gap-2 text-xs text-[var(--text-tertiary)]">
      <Link to={`/admin/leads/${auditId}`} className="flex items-center gap-1 hover:text-[var(--text-secondary)]">
        <ArrowLeft className="h-3 w-3" /> Volver al lead
      </Link>
      <span>·</span>
      <Link to={`/admin/leads/${auditId}/report`} className="hover:text-[var(--text-secondary)]">Informe técnico</Link>
    </div>
  )
}

