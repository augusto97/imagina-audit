import { useEffect, useState } from 'react'
import { Loader2, Package, RefreshCw, Download, Copy, Check, ExternalLink } from 'lucide-react'
import { toast } from 'sonner'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Skeleton } from '@/components/ui/skeleton'
import { useAdmin } from '@/hooks/useAdmin'

interface PluginInfo {
  slug: string
  displayName: string
  description: string
  githubRepo: string
  githubUrl: string
  version: string | null
  publishedAt: string | null
  downloadedAt: string | null
  checkedAt: string | null
  sizeBytes: number | null
  sha256: string | null
  source: string | null
  fileExists: boolean
  publicUrl: string
}

/**
 * Pestaña /admin/plugin-vault — gestiona el caché local de plugins
 * de terceros que el operador comparte con clientes (wp-snapshot
 * por ahora; se puede ampliar editando PluginVault::catalog en PHP).
 */
export default function SettingsPluginVault() {
  const { fetchPluginVault, refreshPluginVault } = useAdmin()
  const [plugins, setPlugins] = useState<PluginInfo[]>([])
  const [loading, setLoading] = useState(true)
  const [refreshing, setRefreshing] = useState<string | null>(null)
  const [copied, setCopied] = useState<string | null>(null)

  const load = async () => {
    setLoading(true)
    const data = await fetchPluginVault()
    if (data?.plugins) setPlugins(data.plugins as PluginInfo[])
    setLoading(false)
  }

  useEffect(() => { load() /* eslint-disable-next-line react-hooks/exhaustive-deps */ }, [])

  const handleRefresh = async (slug: string, force = false) => {
    setRefreshing(slug)
    try {
      const res = await refreshPluginVault(slug, force)
      if (res?.plugin) {
        setPlugins(plugins.map(p => p.slug === slug ? res.plugin as PluginInfo : p))
        toast.success(`Plugin actualizado a v${(res.plugin as PluginInfo).version ?? '?'}`)
      }
    } catch {
      toast.error('Error al refrescar el plugin')
    }
    setRefreshing(null)
  }

  const fullUrl = (publicUrl: string) => `${window.location.origin}${publicUrl}`

  const copyLink = async (publicUrl: string) => {
    try {
      await navigator.clipboard.writeText(fullUrl(publicUrl))
      setCopied(publicUrl)
      toast.success('Link copiado al portapapeles')
      setTimeout(() => setCopied(null), 2000)
    } catch {
      toast.error('No se pudo copiar')
    }
  }

  if (loading) return <Skeleton className="h-96 rounded-2xl" />

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-[var(--text-primary)]">Plugin Vault</h1>
        <p className="text-sm text-[var(--text-secondary)] mt-1">
          Caché local de plugins de terceros que compartimos con clientes. Sirve dos propósitos: (1) tener una
          copia si el repo de GitHub desaparece, (2) ofrecer al cliente un link de descarga desde nuestro
          dominio en vez de pedirle ir a GitHub. El refresco automático se ejecuta mensualmente vía cron.
        </p>
      </div>

      {plugins.map((p) => (
        <Card key={p.slug}>
          <CardHeader>
            <CardTitle className="flex items-center gap-2 text-base">
              <Package className="h-5 w-5 text-[var(--accent-primary)]" strokeWidth={1.5} />
              {p.displayName}
              {p.version && <Badge variant="secondary" className="ml-1 font-mono text-[10px]">v{p.version}</Badge>}
              {p.fileExists
                ? <Badge variant="success" className="text-[10px]">Cacheado</Badge>
                : <Badge variant="destructive" className="text-[10px]">Sin caché</Badge>}
            </CardTitle>
            <p className="mt-1 text-xs text-[var(--text-secondary)]">{p.description}</p>
          </CardHeader>
          <CardContent className="space-y-4">

            {/* Stats */}
            <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
              <Stat label="Versión" value={p.version || '—'} mono />
              <Stat label="Tamaño" value={p.sizeBytes ? formatSize(p.sizeBytes) : '—'} />
              <Stat label="Descargado" value={formatDate(p.downloadedAt)} hint={p.source ? `vía ${p.source}` : undefined} />
              <Stat label="Última verificación" value={formatDate(p.checkedAt)} />
            </div>

            {/* Link público */}
            <div className="rounded-lg border border-[var(--border-default)] bg-[var(--bg-secondary)] p-3">
              <p className="mb-1 text-[10px] font-semibold uppercase tracking-wider text-[var(--text-tertiary)]">
                Link público para compartir con el cliente
              </p>
              <div className="flex flex-wrap items-center gap-2">
                <code className="flex-1 rounded bg-white px-2 py-1.5 font-mono text-[11px] break-all">
                  {fullUrl(p.publicUrl)}
                </code>
                <Button size="sm" variant="outline" onClick={() => copyLink(p.publicUrl)}>
                  {copied === p.publicUrl
                    ? <><Check className="h-3.5 w-3.5 text-emerald-600" strokeWidth={2} /> Copiado</>
                    : <><Copy className="h-3.5 w-3.5" strokeWidth={1.5} /> Copiar</>}
                </Button>
              </div>
              <p className="mt-1.5 text-[10px] text-[var(--text-tertiary)]">
                Sin autenticación. El cliente entra a este link y obtiene el ZIP listo para subir a WP Admin → Plugins → Añadir nuevo → Subir plugin.
              </p>
            </div>

            {/* SHA256 (verificación de integridad) */}
            {p.sha256 && (
              <div className="text-[10px] text-[var(--text-tertiary)]">
                <span className="font-semibold uppercase tracking-wider">SHA256:</span>{' '}
                <code className="font-mono">{p.sha256}</code>
              </div>
            )}

            {/* Acciones */}
            <div className="flex flex-wrap gap-2 border-t border-[var(--border-default)] pt-3">
              <Button asChild size="sm">
                <a href={p.publicUrl} download>
                  <Download className="h-3.5 w-3.5" strokeWidth={1.5} /> Descargar ZIP
                </a>
              </Button>
              <Button size="sm" variant="outline" onClick={() => handleRefresh(p.slug, false)} disabled={refreshing === p.slug}>
                {refreshing === p.slug
                  ? <Loader2 className="h-3.5 w-3.5 animate-spin" />
                  : <RefreshCw className="h-3.5 w-3.5" strokeWidth={1.5} />}
                Buscar nueva versión
              </Button>
              <Button size="sm" variant="ghost" onClick={() => handleRefresh(p.slug, true)} disabled={refreshing === p.slug}>
                Forzar re-descarga
              </Button>
              <Button asChild size="sm" variant="ghost" className="ml-auto">
                <a href={p.githubUrl} target="_blank" rel="noreferrer">
                  Ver en GitHub <ExternalLink className="h-3 w-3" />
                </a>
              </Button>
            </div>
          </CardContent>
        </Card>
      ))}

      {/* Cómo automatizar */}
      <Card>
        <CardHeader>
          <CardTitle className="text-sm">Refresco automático mensual</CardTitle>
        </CardHeader>
        <CardContent className="text-xs text-[var(--text-secondary)] space-y-2">
          <p>
            El refresco no se programa solo desde la app. Para que se ejecute mensualmente, configura un cron del sistema en tu hosting:
          </p>
          <pre className="rounded bg-[var(--bg-secondary)] p-2 text-[10px] font-mono overflow-x-auto">
{`30 3 1 * *  /usr/bin/php /var/www/audit/cron/refresh-plugin-vault.php`}
          </pre>
          <p>
            O alternativamente vía web (necesita <code>CRON_TOKEN</code> en el .env):
          </p>
          <pre className="rounded bg-[var(--bg-secondary)] p-2 text-[10px] font-mono overflow-x-auto">
{`30 3 1 * *  curl -s "https://${window.location.hostname}/api/cron/refresh-plugin-vault.php?token=$CRON_TOKEN" >/dev/null`}
          </pre>
        </CardContent>
      </Card>
    </div>
  )
}

function Stat({ label, value, hint, mono }: { label: string; value: string; hint?: string; mono?: boolean }) {
  return (
    <div className="rounded-lg border border-[var(--border-default)] bg-white p-3">
      <div className="text-[10px] font-medium uppercase tracking-wider text-[var(--text-tertiary)]">{label}</div>
      <div className={`mt-1 text-sm font-bold text-[var(--text-primary)] ${mono ? 'font-mono' : ''}`}>{value}</div>
      {hint && <div className="mt-0.5 text-[10px] text-[var(--text-tertiary)]">{hint}</div>}
    </div>
  )
}

function formatSize(bytes: number): string {
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1048576) return `${(bytes / 1024).toFixed(1)} KB`
  return `${(bytes / 1048576).toFixed(2)} MB`
}

function formatDate(iso: string | null): string {
  if (!iso) return 'Nunca'
  try {
    return new Date(iso).toLocaleString('es-CO', {
      day: '2-digit', month: 'short', year: 'numeric',
      hour: '2-digit', minute: '2-digit',
    })
  } catch {
    return iso
  }
}
