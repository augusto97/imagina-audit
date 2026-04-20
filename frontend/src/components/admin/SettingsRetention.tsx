import { useEffect, useState } from 'react'
import { Loader2, Save, Archive, AlertTriangle, Pin, Trash2 } from 'lucide-react'
import { toast } from 'sonner'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Label } from '@/components/ui/label'
import { Skeleton } from '@/components/ui/skeleton'
import { useAdmin } from '@/hooks/useAdmin'

const MONTH_OPTIONS = [
  { value: 3, label: '3 meses' },
  { value: 6, label: '6 meses' },
  { value: 12, label: '12 meses' },
  { value: 24, label: '24 meses' },
]

interface Preview {
  months: number
  cutoffDate: string
  totalAudits: number
  pinnedAudits: number
  wouldDelete: number
  wouldKeep: number
  estimatedBytesFreed: number
}

function formatBytes(bytes: number): string {
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1048576) return `${(bytes / 1024).toFixed(1)} KB`
  if (bytes < 1073741824) return `${(bytes / 1048576).toFixed(1)} MB`
  return `${(bytes / 1073741824).toFixed(2)} GB`
}

export default function SettingsRetention() {
  const { fetchSettings, updateSettings, fetchRetentionPreview } = useAdmin()
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [enabled, setEnabled] = useState(false)
  const [months, setMonths] = useState(6)
  const [preview, setPreview] = useState<Preview | null>(null)
  const [previewLoading, setPreviewLoading] = useState(false)

  useEffect(() => {
    fetchSettings().then((data) => {
      if (data) {
        setEnabled(!!(data.auditsRetentionEnabled ?? data.audits_retention_enabled))
        setMonths(Number(data.auditsRetentionMonths ?? data.audits_retention_months ?? 6))
      }
      setLoading(false)
    })
  }, [fetchSettings])

  // Recalcular preview cuando cambia months
  useEffect(() => {
    if (loading) return
    setPreviewLoading(true)
    fetchRetentionPreview(months).then((p) => {
      if (p) setPreview(p as Preview)
      setPreviewLoading(false)
    })
  }, [months, loading, fetchRetentionPreview])

  const save = async () => {
    setSaving(true)
    try {
      await updateSettings({
        auditsRetentionEnabled: enabled ? 'true' : 'false',
        auditsRetentionMonths: months,
      })
      toast.success('Configuración de retención guardada')
    } catch { toast.error('Error al guardar') }
    setSaving(false)
  }

  if (loading) return <Skeleton className="h-96 rounded-2xl" />

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-[var(--text-primary)]">Retención de Informes</h1>
        <p className="text-sm text-[var(--text-secondary)] mt-1">
          Controla por cuánto tiempo se guardan los informes antes de borrarse automáticamente. Los informes marcados como
          <Pin className="inline-block h-3 w-3 mx-1 text-amber-500 fill-amber-500" strokeWidth={2} />
          protegidos nunca se eliminan.
        </p>
      </div>

      {/* Toggle master */}
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <Archive className="h-5 w-5 text-[var(--accent-primary)]" /> Eliminación automática
          </CardTitle>
        </CardHeader>
        <CardContent>
          <label className="flex items-start gap-3 cursor-pointer">
            <input
              type="checkbox"
              checked={enabled}
              onChange={(e) => setEnabled(e.target.checked)}
              className="mt-1 h-4 w-4 accent-[var(--accent-primary)]"
            />
            <div>
              <p className="font-medium text-[var(--text-primary)]">Habilitar borrado por antigüedad</p>
              <p className="text-sm text-[var(--text-secondary)] mt-1">
                Cuando está activo, el cron diario elimina informes más viejos que el período seleccionado.
                Los informes protegidos quedan exentos del borrado.
              </p>
            </div>
          </label>

          {enabled && (
            <div className="mt-6 pt-6 border-t border-[var(--border-default)]">
              <Label className="font-medium">Conservar informes de los últimos</Label>
              <div className="grid grid-cols-2 sm:grid-cols-4 gap-2 mt-2">
                {MONTH_OPTIONS.map((opt) => (
                  <button
                    key={opt.value}
                    onClick={() => setMonths(opt.value)}
                    className={`rounded-lg border px-3 py-2 text-sm font-medium transition-colors cursor-pointer ${
                      months === opt.value
                        ? 'bg-[var(--accent-primary)] border-[var(--accent-primary)] text-white'
                        : 'bg-white border-[var(--border-default)] text-[var(--text-secondary)] hover:border-[var(--accent-primary)]'
                    }`}
                  >
                    {opt.label}
                  </button>
                ))}
              </div>
              <p className="text-xs text-[var(--text-tertiary)] mt-2">
                Fecha de corte: informes anteriores a{' '}
                <span className="font-mono">
                  {preview?.cutoffDate ? new Date(preview.cutoffDate).toLocaleDateString('es-CO') : '—'}
                </span>{' '}
                serán eliminados.
              </p>
            </div>
          )}
        </CardContent>
      </Card>

      {/* Preview */}
      {enabled && (
        <Card>
          <CardHeader>
            <CardTitle>Impacto de la configuración actual</CardTitle>
          </CardHeader>
          <CardContent>
            {previewLoading || !preview ? (
              <div className="flex items-center gap-2 text-sm text-[var(--text-tertiary)]">
                <Loader2 className="h-4 w-4 animate-spin" /> Calculando…
              </div>
            ) : (
              <div className="space-y-4">
                <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
                  <StatCard label="Informes totales" value={preview.totalAudits} color="gray" />
                  <StatCard label="Se conservarían" value={preview.wouldKeep} color="emerald" />
                  <StatCard label="Se eliminarían" value={preview.wouldDelete} color={preview.wouldDelete > 0 ? 'red' : 'gray'} />
                  <StatCard label="Protegidos" value={preview.pinnedAudits} color="amber" icon={<Pin className="h-3 w-3" />} />
                </div>
                {preview.wouldDelete > 0 && (
                  <div className="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm">
                    <AlertTriangle className="h-4 w-4 inline-block mr-2 text-amber-600" />
                    Al guardar, el próximo cron ejecutará la eliminación de{' '}
                    <b className="text-red-600">{preview.wouldDelete}</b> informes,
                    liberando aproximadamente <b>{formatBytes(preview.estimatedBytesFreed)}</b> de espacio.
                    Esta acción es irreversible — protege los informes que quieras conservar antes de activar esto.
                  </div>
                )}
                {preview.wouldDelete === 0 && preview.totalAudits > 0 && (
                  <p className="text-sm text-[var(--text-secondary)]">
                    No hay informes más viejos que {months} meses. La configuración queda aplicada para el futuro.
                  </p>
                )}
              </div>
            )}
          </CardContent>
        </Card>
      )}

      {/* Cómo proteger */}
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <Pin className="h-5 w-5 text-amber-500 fill-amber-500" /> Cómo proteger un informe
          </CardTitle>
        </CardHeader>
        <CardContent className="space-y-2 text-sm text-[var(--text-secondary)]">
          <p>
            Desde <b>Leads y Auditorías</b>: click en el icono <Pin className="inline-block h-3.5 w-3.5 mx-0.5 text-[var(--text-tertiary)]" /> de
            cualquier fila para protegerla. La fila queda marcada con un pin dorado.
          </p>
          <p>
            Desde el <b>Reporte Técnico</b> de un informe: el botón <b>Proteger</b> en la cabecera hace lo mismo.
          </p>
          <p>
            Un informe protegido: (1) nunca se borra automáticamente, (2) no se puede eliminar con el botón{' '}
            <Trash2 className="inline-block h-3.5 w-3.5 mx-0.5" /> sin quitar antes la protección.
          </p>
        </CardContent>
      </Card>

      <Button onClick={save} disabled={saving}>
        {saving ? <Loader2 className="h-4 w-4 animate-spin" /> : <Save className="h-4 w-4" strokeWidth={1.5} />}
        Guardar configuración
      </Button>
    </div>
  )
}

function StatCard({ label, value, color, icon }: {
  label: string
  value: number
  color: 'emerald' | 'amber' | 'red' | 'gray'
  icon?: React.ReactNode
}) {
  const colors = {
    emerald: 'text-emerald-600',
    amber: 'text-amber-600',
    red: 'text-red-600',
    gray: 'text-[var(--text-primary)]',
  }
  return (
    <div className="rounded-xl bg-[var(--bg-secondary)] p-3">
      <p className="text-xs text-[var(--text-tertiary)] flex items-center gap-1">{icon}{label}</p>
      <p className={`text-2xl font-bold tabular-nums ${colors[color]}`}>{value}</p>
    </div>
  )
}
