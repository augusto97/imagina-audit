import { useEffect, useState, useCallback } from 'react'
import { Upload, Link as LinkIcon, Trash2, CheckCircle, Loader2, Database, AlertCircle } from 'lucide-react'
import { toast } from 'sonner'
import { Card, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Tabs, TabsList, TabsTrigger, TabsContent } from '@/components/ui/tabs'
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

export default function SnapshotUploader({ auditId, onChange }: Props) {
  const [existing, setExisting] = useState<SnapshotMetadata | null>(null)
  const [loading, setLoading] = useState(true)
  const [shareUrl, setShareUrl] = useState('')
  const [submitting, setSubmitting] = useState(false)
  const [tab, setTab] = useState<'url' | 'upload'>('url')

  const load = useCallback(async () => {
    try {
      const res = await api.get('/admin/snapshot.php', { params: { audit_id: auditId } })
      setExisting(res.data?.data || null)
    } catch { /* ignore */ }
    setLoading(false)
  }, [auditId])

  useEffect(() => { load() }, [load])

  const submitUrl = async () => {
    if (!shareUrl.trim()) { toast.error('Pega la URL del share'); return }
    setSubmitting(true)
    try {
      await api.post('/admin/snapshot.php', { auditId, source: 'url', shareUrl: shareUrl.trim() })
      toast.success('Snapshot cargado y analizado')
      setShareUrl('')
      await load()
      onChange?.()
    } catch (e: unknown) {
      const msg = (e as { response?: { data?: { error?: string } } })?.response?.data?.error || 'Error al cargar el snapshot'
      toast.error(msg)
    }
    setSubmitting(false)
  }

  const submitFile = async (file: File) => {
    setSubmitting(true)
    try {
      const text = await file.text()
      const parsed = JSON.parse(text)
      await api.post('/admin/snapshot.php', { auditId, source: 'upload', jsonData: parsed })
      toast.success('Snapshot cargado y analizado')
      await load()
      onChange?.()
    } catch (e: unknown) {
      const msg = (e as { response?: { data?: { error?: string } } })?.response?.data?.error || 'JSON inválido o error al analizar'
      toast.error(msg)
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
              Conecta la auditoría al plugin <a href="https://github.com/mrabro/wp-snapshot" target="_blank" rel="noreferrer" className="text-blue-600 hover:underline">wp-snapshot</a> para obtener datos internos (plugins inactivos, tamaño DB, cron, etc.). Pide al cliente que instale el plugin y te envíe la URL compartida, o sube el JSON exportado.
            </p>
          </div>
        </div>

        <Tabs value={tab} onValueChange={(v) => setTab(v as 'url' | 'upload')}>
          <TabsList>
            <TabsTrigger value="url"><LinkIcon className="h-3.5 w-3.5 mr-1" /> URL Compartida</TabsTrigger>
            <TabsTrigger value="upload"><Upload className="h-3.5 w-3.5 mr-1" /> Subir JSON</TabsTrigger>
          </TabsList>

          <TabsContent value="url" className="mt-3">
            <div className="flex flex-col sm:flex-row gap-2">
              <Input
                value={shareUrl}
                onChange={(e) => setShareUrl(e.target.value)}
                placeholder="https://ejemplo.com/site-audit-snapshot/share/abc123..."
                disabled={submitting}
                className="flex-1"
              />
              <Button onClick={submitUrl} disabled={submitting || !shareUrl.trim()}>
                {submitting ? <Loader2 className="h-4 w-4 animate-spin" /> : 'Conectar'}
              </Button>
            </div>
            <p className="text-[11px] text-gray-400 mt-2 flex items-center gap-1">
              <AlertCircle className="h-3 w-3" /> El enlace se genera desde WP Admin → Tools → Site Audit Snapshot → Share
            </p>
          </TabsContent>

          <TabsContent value="upload" className="mt-3">
            <label className="block">
              <input
                type="file"
                accept="application/json,.json"
                onChange={(e) => {
                  const f = e.target.files?.[0]
                  if (f) submitFile(f)
                }}
                className="block w-full text-xs text-gray-500 file:mr-3 file:py-1.5 file:px-3 file:rounded file:border-0 file:text-xs file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 cursor-pointer"
                disabled={submitting}
              />
            </label>
            <p className="text-[11px] text-gray-400 mt-2">
              Descargar desde WP Admin → Tools → Site Audit Snapshot → Download JSON
            </p>
          </TabsContent>
        </Tabs>
      </CardContent>
    </Card>
  )
}
