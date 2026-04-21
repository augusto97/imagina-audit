import { Link } from 'react-router-dom'
import { ShieldCheck, ShieldOff, Clock, Package, Archive, Server, CheckCircle2, AlertCircle, type LucideIcon } from 'lucide-react'
import { Card, CardContent } from '@/components/ui/card'
import type { DashboardData } from '@/types/dashboard'

/**
 * Banner horizontal con el estado de los 5 sub-sistemas críticos del admin.
 * Cada tile enlaza a su página de gestión. El tono semántico (emerald/amber/
 * red/gray) lo calcula StatusTile según cada caso.
 */
export function SystemStatusBanner({ data }: { data: DashboardData }) {
  return (
    <Card className="border-0 shadow-sm">
      <CardContent className="p-3 sm:p-4">
        <div className="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-5">
          <StatusTile
            to="/admin/security"
            icon={data.security.twoFaEnabled ? ShieldCheck : ShieldOff}
            label="Login 2FA"
            value={data.security.twoFaEnabled ? 'Activo' : 'Desactivado'}
            hint={data.security.twoFaEnabled ? `${data.security.recoveryCodesLeft} recovery codes` : 'Recomendado activar'}
            tone={data.security.twoFaEnabled ? 'ok' : 'warning'}
          />
          <StatusTile
            to="/admin/health"
            icon={Clock}
            label="Cron"
            value={cronLabel(data.cronHealth)}
            hint={cronHint(data.cronHealth)}
            tone={cronTone(data.cronHealth)}
          />
          <StatusTile
            to="/admin/queue"
            icon={Server}
            label="Cola"
            value={`${data.queue.running}/${data.queue.maxConcurrent}`}
            hint={
              data.queue.queued > 0
                ? `${data.queue.queued} en espera`
                : data.queue.failedLastHour > 0
                ? `${data.queue.failedLastHour} fallos 1h`
                : `${data.queue.completedLastHour} completados 1h`
            }
            tone={data.queue.failedLastHour > 3 ? 'warning' : 'ok'}
          />
          <StatusTile
            to="/admin/plugin-vault"
            icon={Package}
            label="Plugin Vault"
            value={data.pluginVault.cached ? (data.pluginVault.version ?? 'cacheado') : 'Sin caché'}
            hint={data.pluginVault.cached ? 'wp-snapshot listo' : 'Click para descargar'}
            tone={data.pluginVault.cached ? 'ok' : 'warning'}
          />
          <StatusTile
            to="/admin/retention"
            icon={Archive}
            label="Retención"
            value={data.retention.enabled ? `${data.retention.months} meses` : 'Manual'}
            hint={data.retention.enabled ? 'Borrado automático ON' : 'Sin borrado automático'}
            tone="neutral"
          />
        </div>
      </CardContent>
    </Card>
  )
}

function StatusTile({
  to, icon: Icon, label, value, hint, tone,
}: {
  to: string
  icon: LucideIcon
  label: string
  value: string
  hint: string
  tone: 'ok' | 'warning' | 'critical' | 'neutral'
}) {
  const tones = {
    ok:       { text: 'text-emerald-700', icon: 'text-emerald-600', dot: <CheckCircle2 className="h-3 w-3 text-emerald-600" /> },
    warning:  { text: 'text-amber-700',   icon: 'text-amber-600',   dot: <AlertCircle className="h-3 w-3 text-amber-600" /> },
    critical: { text: 'text-red-700',     icon: 'text-red-600',     dot: <AlertCircle className="h-3 w-3 text-red-600" /> },
    neutral:  { text: 'text-[var(--text-primary)]', icon: 'text-[var(--text-tertiary)]', dot: null },
  }[tone]
  return (
    <Link
      to={to}
      className="group flex items-start gap-2 rounded-lg border border-[var(--border-default)] bg-white p-2.5 transition-colors hover:border-[var(--text-tertiary)]"
    >
      <div className="mt-0.5 shrink-0">
        <Icon className={`h-4 w-4 ${tones.icon}`} strokeWidth={1.5} />
      </div>
      <div className="min-w-0 flex-1">
        <div className="flex items-center gap-1 text-[10px] uppercase tracking-wider text-[var(--text-tertiary)]">
          {label} {tones.dot}
        </div>
        <div className={`truncate text-sm font-semibold ${tones.text}`}>{value}</div>
        <div className="truncate text-[10px] text-[var(--text-tertiary)]">{hint}</div>
      </div>
    </Link>
  )
}

function cronLabel(c: DashboardData['cronHealth']): string {
  if (!c) return 'Sin datos'
  if (c.overallOk) return 'OK'
  if (c.counts.critical > 0) return `${c.counts.critical} crítico${c.counts.critical > 1 ? 's' : ''}`
  if (c.counts.warning > 0) return `${c.counts.warning} atrasado${c.counts.warning > 1 ? 's' : ''}`
  return `${c.counts.never} sin correr`
}
function cronHint(c: DashboardData['cronHealth']): string {
  if (!c) return 'Ver /admin/health'
  if (c.overallOk) return `${c.counts.ok} tareas a tiempo`
  return 'Revisar config del sistema'
}
function cronTone(c: DashboardData['cronHealth']): 'ok' | 'warning' | 'critical' | 'neutral' {
  if (!c) return 'neutral'
  if (c.counts.critical > 0) return 'critical'
  if (c.counts.warning > 0 || c.counts.never > 0) return 'warning'
  return 'ok'
}
