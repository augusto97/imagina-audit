import { Link } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { ShieldCheck, ShieldOff, Clock, Package, Archive, Server, CheckCircle2, AlertCircle, type LucideIcon } from 'lucide-react'
import { Card, CardContent } from '@/components/ui/card'
import type { DashboardData } from '@/types/dashboard'

/**
 * Banner horizontal con el estado de los 5 sub-sistemas críticos del admin.
 * Cada tile enlaza a su página de gestión. El tono semántico (emerald/amber/
 * red/gray) lo calcula StatusTile según cada caso.
 */
export function SystemStatusBanner({ data }: { data: DashboardData }) {
  const { t } = useTranslation()
  const cron = data.cronHealth

  const cronValue = !cron
    ? t('dashboard.status_cron_no_data')
    : cron.overallOk
      ? t('dashboard.status_cron_ok')
      : cron.counts.critical > 0
        ? t('dashboard.status_cron_critical', { count: cron.counts.critical })
        : cron.counts.warning > 0
          ? t('dashboard.status_cron_overdue', { count: cron.counts.warning })
          : t('dashboard.status_cron_never', { count: cron.counts.never })

  const cronHint = !cron
    ? t('dashboard.status_cron_see_health')
    : cron.overallOk
      ? t('dashboard.status_cron_ontime', { count: cron.counts.ok })
      : t('dashboard.status_cron_check_system')

  const cronTone: Tone = !cron ? 'neutral'
    : cron.counts.critical > 0 ? 'critical'
    : cron.counts.warning > 0 || cron.counts.never > 0 ? 'warning'
    : 'ok'

  return (
    <Card className="border-0 shadow-sm">
      <CardContent className="p-3 sm:p-4">
        <div className="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-5">
          <StatusTile
            to="/admin/security"
            icon={data.security.twoFaEnabled ? ShieldCheck : ShieldOff}
            label={t('dashboard.status_twofa_label')}
            value={data.security.twoFaEnabled ? t('dashboard.status_twofa_active') : t('dashboard.status_twofa_off')}
            hint={data.security.twoFaEnabled
              ? t('dashboard.status_twofa_recovery_codes', { count: data.security.recoveryCodesLeft })
              : t('dashboard.status_twofa_suggest_enable')}
            tone={data.security.twoFaEnabled ? 'ok' : 'warning'}
          />
          <StatusTile
            to="/admin/health"
            icon={Clock}
            label={t('dashboard.status_cron_label')}
            value={cronValue}
            hint={cronHint}
            tone={cronTone}
          />
          <StatusTile
            to="/admin/queue"
            icon={Server}
            label={t('dashboard.status_queue_label')}
            value={`${data.queue.running}/${data.queue.maxConcurrent}`}
            hint={
              data.queue.queued > 0
                ? t('dashboard.status_queue_waiting', { count: data.queue.queued })
                : data.queue.failedLastHour > 0
                ? t('dashboard.status_queue_failed_1h', { count: data.queue.failedLastHour })
                : t('dashboard.status_queue_completed_1h', { count: data.queue.completedLastHour })
            }
            tone={data.queue.failedLastHour > 3 ? 'warning' : 'ok'}
          />
          <StatusTile
            to="/admin/plugin-vault"
            icon={Package}
            label={t('dashboard.status_vault_label')}
            value={data.pluginVault.cached
              ? (data.pluginVault.version ?? t('dashboard.status_vault_cached'))
              : t('dashboard.status_vault_missing')}
            hint={data.pluginVault.cached
              ? t('dashboard.status_vault_ready')
              : t('dashboard.status_vault_click_download')}
            tone={data.pluginVault.cached ? 'ok' : 'warning'}
          />
          <StatusTile
            to="/admin/retention"
            icon={Archive}
            label={t('dashboard.status_retention_label')}
            value={data.retention.enabled
              ? t('dashboard.status_retention_months', { count: data.retention.months })
              : t('dashboard.status_retention_manual')}
            hint={data.retention.enabled
              ? t('dashboard.status_retention_auto_on')
              : t('dashboard.status_retention_auto_off')}
            tone="neutral"
          />
        </div>
      </CardContent>
    </Card>
  )
}

type Tone = 'ok' | 'warning' | 'critical' | 'neutral'

function StatusTile({
  to, icon: Icon, label, value, hint, tone,
}: {
  to: string
  icon: LucideIcon
  label: string
  value: string
  hint: string
  tone: Tone
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
