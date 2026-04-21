import { useEffect, useState, useCallback, useRef } from 'react'
import { Upload, Trash2, CheckCircle, Loader2, Database, FileCheck2 } from 'lucide-react'
import { toast } from 'sonner'
import { Card, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import api from '@/lib/api'

interface SnapshotMetadata {
  source: 'url' | 'upload'
  sourceUrl: string | null
  generatedAt?: string
  siteName?: string
  analysis?: {
    name: string
    score: number
    level: string
    metrics: Array<{ id: string; name: string; level: string }>
  }
  createdAt: string
}

interface Props {
  auditId: string
  onChange?: () => void
}

type ProgressStep = 'uploading' | 'analyzing' | 'reauditing' | null

export default function SnapshotUploader({ auditId, onChange }: Props) {
  const [existing, setExisting] = useState<SnapshotMetadata | null>(null)
  const [loading, setLoading] = useState(true)
  const [submitting, setSubmitting] = useState(false)
  const [progressStep, setProgressStep] = useState<ProgressStep>(null)
  const [selectedFile, setSelectedFile] = useState<{ name: string; size: number } | null>(null)
  const fileInputRef = useRef<HTMLInputElement>(null)

  const load = useCallback(async () => {
    try {
      const res = await api.get('/admin/snapshot.php', { params: { audit_id: auditId } })
      setExisting(res.data?.data || null)
    } catch { /* ignore */ }
    setLoading(false)
  }, [auditId])

  useEffect(() => { load() }, [load])

  // Timer que avanza los "pasos" visibles para que el usuario perciba actividad
  // aunque el servidor esté procesando en sync. El paso real se actualiza cuando
  // se recibe la respuesta.
  useEffect(() => {
    if (!submitting) { setProgressStep(null); return }
    setProgressStep('uploading')
    const t1 = setTimeout(() => setProgressStep('analyzing'), 2000)
    const t2 = setTimeout(() => setProgressStep('reauditing'), 8000)
    return () => { clearTimeout(t1); clearTimeout(t2) }
  }, [submitting])

  const formatSize = (bytes: number) => {
    if (bytes < 1024) return `${bytes} B`
    if (bytes < 1048576) return `${(bytes / 1024).toFixed(1)} KB`
    return `${(bytes / 1048576).toFixed(1)} MB`
  }

  const submitFile = async (file: File) => {
    setSelectedFile({ name: file.name, size: file.size })
    setSubmitting(true)
    try {
      const text = await file.text()
      const parsed = JSON.parse(text)
      const res = await api.post('/admin/snapshot.php', { auditId, jsonData: parsed })
      const data = res.data?.data
      if (data?.reaudit) {
        toast.success(`Snapshot conectado y auditoría re-ejecutada (Score: ${data.newScore}/100)`)
      } else {
        toast.success('Snapshot cargado y analizado')
      }
      await load()
      onChange?.()
      setSelectedFile(null)
      if (fileInputRef.current) fileInputRef.current.value = ''
      setTimeout(() => {
        window.scrollTo({ top: 0, behavior: 'smooth' })
      }, 300)
    } catch (e: unknown) {
      const msg = (e as { response?: { data?: { error?: string } } })?.response?.data?.error || 'JSON inválido o error al analizar'
      toast.error(msg, { duration: 10000 })
    }
    setSubmitting(false)
  }

  const deleteSnapshot = async () => {
    if (!confirm('¿Eliminar el snapshot de esta auditoría?')) return
    try {
      await api.delete('/admin/snapshot.php', { params: { audit_id: auditId } })
      toast.success('Snapshot eliminado')
      setExisting(null)
      onChange?.()
    } catch { toast.error('Error al eliminar') }
  }

  if (loading) return null

  if (existing) {
    const a = existing.analysis
    return (
      <Card className="mt-4">
        <CardContent className="pt-5">
          <div className="flex items-start justify-between gap-3 flex-wrap">
            <div className="flex items-start gap-3 min-w-0">
              <div className="h-10 w-10 rounded-lg bg-emerald-50 flex items-center justify-center shrink-0">
                <Database className="h-5 w-5 text-emerald-600" strokeWidth={1.5} />
              </div>
              <div className="min-w-0">
                <div className="flex items-center gap-2 flex-wrap">
                  <span className="font-semibold text-sm text-gray-900">Snapshot interno conectado</span>
                  <CheckCircle className="h-4 w-4 text-emerald-500" />
                </div>
                <p className="text-xs text-gray-500 mt-0.5">
                  {existing.siteName ? `${existing.siteName} · ` : ''}
                  Generado: {existing.generatedAt || '—'} · Cargado: {new Date(existing.createdAt).toLocaleString('es-CO')}
                </p>
                {a && (
                  <div className="mt-2 flex items-center gap-2 text-xs">
                    <span className={`font-bold ${a.level === 'critical' ? 'text-red-600' : a.level === 'warning' ? 'text-amber-600' : 'text-emerald-600'}`}>
                      {a.score}/100
                    </span>
                    <span className="text-gray-500">{a.metrics.length} métricas internas</span>
                  </div>
                )}
              </div>
            </div>
            <Button variant="ghost" size="sm" onClick={deleteSnapshot} className="text-red-500 hover:text-red-700">
              <Trash2 className="h-4 w-4" strokeWidth={1.5} />
              Quitar
            </Button>
          </div>
        </CardContent>
      </Card>
    )
  }

  return (
    <Card className="mt-4 border-dashed">
      <CardContent className="pt-5">
        <div className="flex items-start gap-3 mb-4">
          <div className="h-10 w-10 rounded-lg bg-blue-50 flex items-center justify-center shrink-0">
            <Database className="h-5 w-5 text-blue-600" strokeWidth={1.5} />
          </div>
          <div className="flex-1">
            <h3 className="font-semibold text-sm text-gray-900">Conectar snapshot interno</h3>
            <p className="text-xs text-gray-500 mt-1">
              Conecta la auditoría al plugin <a href="https://github.com/mrabro/wp-snapshot" target="_blank" rel="noreferrer" className="text-blue-600 hover:underline">wp-snapshot</a> para obtener datos internos (plugins con versiones, base de datos, cron, seguridad). El cliente descarga el JSON desde WP Admin → Herramientas → Site Audit Snapshot → Download JSON y lo subes aquí.
            </p>
          </div>
        </div>

        <div>
          <label className="flex cursor-pointer items-center gap-3 rounded-lg border-2 border-dashed border-[var(--border-default)] bg-white px-4 py-6 transition-colors hover:border-[var(--accent-primary)]">
            <Upload className="h-5 w-5 text-[var(--text-tertiary)]" strokeWidth={1.5} />
            <div className="flex-1">
              <p className="text-sm font-medium text-[var(--text-primary)]">Seleccionar archivo JSON</p>
              <p className="text-[11px] text-[var(--text-tertiary)]">Máximo 10 MB. Solo .json exportado por wp-snapshot.</p>
            </div>
            <input
              ref={fileInputRef}
              type="file"
              accept="application/json,.json"
              onChange={(e) => {
                const f = e.target.files?.[0]
                if (f) submitFile(f)
              }}
              className="hidden"
              disabled={submitting}
            />
          </label>
          {selectedFile && !submitting && (
            <p className="mt-2 flex items-center gap-1 text-[11px] text-emerald-600">
              <FileCheck2 className="h-3 w-3" /> {selectedFile.name} ({formatSize(selectedFile.size)})
            </p>
          )}
        </div>

        {/* Progress indicator visible mientras se procesa */}
        {submitting && (
          <div className="mt-4 rounded-lg border border-blue-200 bg-blue-50 p-3">
            <p className="text-xs font-semibold text-blue-900 mb-2">Procesando snapshot…</p>
            <div className="space-y-1.5 text-[11px]">
              <ProgressLine label="Subiendo datos al servidor" active={progressStep === 'uploading'} done={progressStep !== 'uploading' && progressStep !== null} />
              <ProgressLine label="Analizando secciones del snapshot" active={progressStep === 'analyzing'} done={progressStep === 'reauditing'} />
              <ProgressLine label="Re-ejecutando la auditoría con los datos internos" active={progressStep === 'reauditing'} done={false} />
            </div>
            <p className="mt-2 text-[11px] text-blue-700">
              Esto puede tardar 30-90 segundos si el sitio es grande. No cierres esta página.
            </p>
          </div>
        )}
      </CardContent>
    </Card>
  )
}

function ProgressLine({ label, active, done }: { label: string; active: boolean; done: boolean }) {
  return (
    <div className="flex items-center gap-2">
      {done ? (
        <CheckCircle className="h-3.5 w-3.5 text-emerald-600 shrink-0" />
      ) : active ? (
        <Loader2 className="h-3.5 w-3.5 text-blue-600 animate-spin shrink-0" />
      ) : (
        <div className="h-3.5 w-3.5 rounded-full border border-gray-300 shrink-0" />
      )}
      <span className={done ? 'text-emerald-700' : active ? 'text-blue-900 font-medium' : 'text-gray-500'}>{label}</span>
    </div>
  )
}
