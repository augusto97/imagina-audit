import { useEffect, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { RefreshCw, Copy, Check, Stethoscope, CheckCircle2, AlertTriangle, XCircle, Clock } from 'lucide-react'
import { toast } from 'sonner'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Skeleton } from '@/components/ui/skeleton'
import api from '@/lib/api'

interface Check {
  name: string
  status: 'ok' | 'warn' | 'fail' | 'skip'
  ms: number
  detail: unknown
}

interface DiagnosticsResult {
  timestamp: string
  app: { version: string }
  summary: { ok: number; warn: number; fail: number; skip: number }
  checks: Check[]
}

/**
 * Página de diagnóstico. Ejecuta el pipeline crítico del escaneo paso a
 * paso en el servidor del usuario y reporta qué falla. La salida está
 * pensada para copiar entera y mandarla a soporte.
 */
export default function Diagnostics() {
  const { t } = useTranslation()
  const [result, setResult] = useState<DiagnosticsResult | null>(null)
  const [loading, setLoading] = useState(true)
  const [copied, setCopied] = useState(false)

  const load = async () => {
    setLoading(true)
    try {
      const res = await api.get<{ success: boolean; data: DiagnosticsResult }>('/admin/diagnostics.php')
      setResult(res.data.data)
    } catch (err) {
      toast.error(t('settings.diag_load_error'))
      // En caso de fallo, mostramos lo poco que llegue
      const e = err as { response?: { data?: { error?: string } } }
      if (e.response?.data?.error) toast.error(e.response.data.error)
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => { load() }, [])

  const copyJson = async () => {
    if (!result) return
    try {
      await navigator.clipboard.writeText(JSON.stringify(result, null, 2))
      setCopied(true)
      setTimeout(() => setCopied(false), 2000)
      toast.success(t('common.copied'))
    } catch {
      toast.error(t('common.error'))
    }
  }

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h1 className="text-2xl font-bold text-[var(--text-primary)] flex items-center gap-2">
            <Stethoscope className="h-6 w-6 text-[var(--accent-primary)]" /> {t('settings.diag_title')}
          </h1>
          <p className="mt-1 text-sm text-[var(--text-secondary)]">{t('settings.diag_subtitle')}</p>
        </div>
        <div className="flex gap-2">
          <Button variant="outline" size="sm" onClick={load} disabled={loading}>
            <RefreshCw className={`h-4 w-4 ${loading ? 'animate-spin' : ''}`} />
            {t('common.refresh')}
          </Button>
          <Button variant="outline" size="sm" onClick={copyJson} disabled={!result}>
            {copied ? <Check className="h-4 w-4 text-emerald-600" /> : <Copy className="h-4 w-4" />}
            {t('settings.diag_copy_for_support')}
          </Button>
        </div>
      </div>

      {loading && !result && (
        <div className="space-y-3">
          {[...Array(6)].map((_, i) => <Skeleton key={i} className="h-20 rounded-xl" />)}
        </div>
      )}

      {result && (
        <>
          {/* Resumen */}
          <Card>
            <CardContent className="py-4">
              <div className="flex flex-wrap gap-3 text-sm">
                <SummaryStat label="OK" count={result.summary.ok} color="emerald" />
                <SummaryStat label={t('settings.diag_warn')} count={result.summary.warn} color="amber" />
                <SummaryStat label={t('settings.diag_fail')} count={result.summary.fail} color="red" />
                <div className="ml-auto text-xs text-[var(--text-tertiary)]">
                  {t('settings.diag_app_version', { version: result.app.version })} · {new Date(result.timestamp).toLocaleString()}
                </div>
              </div>
            </CardContent>
          </Card>

          {/* Checks individuales */}
          <div className="space-y-2">
            {result.checks.map((check, i) => <CheckCard key={i} check={check} />)}
          </div>

          <Card className="border-[var(--accent-primary)]/30 bg-[var(--accent-primary)]/[0.03]">
            <CardContent className="py-4 text-sm text-[var(--text-secondary)]">
              {t('settings.diag_share_hint')}
            </CardContent>
          </Card>
        </>
      )}
    </div>
  )
}

function SummaryStat({ label, count, color }: { label: string; count: number; color: 'emerald' | 'amber' | 'red' }) {
  const styles = {
    emerald: 'bg-emerald-50 text-emerald-700 border-emerald-200',
    amber: 'bg-amber-50 text-amber-700 border-amber-200',
    red: 'bg-red-50 text-red-700 border-red-200',
  }
  return (
    <div className={`rounded-lg border px-3 py-1.5 ${styles[color]}`}>
      <span className="font-bold tabular-nums">{count}</span> <span className="text-xs">{label}</span>
    </div>
  )
}

function CheckCard({ check }: { check: Check }) {
  const icons = {
    ok: <CheckCircle2 className="h-5 w-5 text-emerald-600 shrink-0" />,
    warn: <AlertTriangle className="h-5 w-5 text-amber-600 shrink-0" />,
    fail: <XCircle className="h-5 w-5 text-red-600 shrink-0" />,
    skip: <Clock className="h-5 w-5 text-[var(--text-tertiary)] shrink-0" />,
  }
  const borderStyle = {
    ok: 'border-emerald-200/50',
    warn: 'border-amber-200',
    fail: 'border-red-200',
    skip: 'border-[var(--border-default)]',
  }[check.status]

  return (
    <Card className={`border ${borderStyle}`}>
      <CardHeader className="pb-2">
        <CardTitle className="flex items-center gap-2 text-sm font-medium">
          {icons[check.status]}
          <span className="flex-1">{check.name}</span>
          <Badge variant="secondary" className="text-[10px] tabular-nums">{check.ms}ms</Badge>
        </CardTitle>
      </CardHeader>
      {check.detail !== null && check.detail !== undefined && (
        <CardContent className="pt-0">
          <pre className="overflow-x-auto rounded-lg bg-[var(--bg-secondary)] p-3 text-[11px] leading-relaxed text-[var(--text-secondary)] whitespace-pre-wrap">
            {typeof check.detail === 'string' ? check.detail : JSON.stringify(check.detail, null, 2)}
          </pre>
        </CardContent>
      )}
    </Card>
  )
}
