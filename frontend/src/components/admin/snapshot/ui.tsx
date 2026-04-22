import type { ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { AlertCircle, AlertTriangle, Info, CheckCircle, ShieldAlert, Shield } from 'lucide-react'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import type { SnapshotIssue } from '@/types/snapshotReport'

/** Card-envoltorio con título, subtitulo y contenido — usado en cada sección del snapshot. */
export function SectionCard({
  title, subtitle, icon, right, children,
}: { title: string; subtitle?: string; icon?: ReactNode; right?: ReactNode; children: ReactNode }) {
  return (
    <Card>
      <CardHeader className="pb-3">
        <div className="flex items-start justify-between gap-3">
          <div>
            <CardTitle className="flex items-center gap-2 text-base">
              {icon}
              {title}
            </CardTitle>
            {subtitle && <p className="mt-0.5 text-xs text-[var(--text-tertiary)]">{subtitle}</p>}
          </div>
          {right}
        </div>
      </CardHeader>
      <CardContent className="pt-0">{children}</CardContent>
    </Card>
  )
}

/** KPI tile compacto */
export function KpiTile({
  label, value, suffix, hint, tone = 'neutral',
}: {
  label: string
  value: string | number
  suffix?: string
  hint?: string
  tone?: 'neutral' | 'good' | 'warning' | 'critical' | 'info'
}) {
  const tones: Record<string, string> = {
    neutral:  'text-[var(--text-primary)]',
    good:     'text-emerald-600',
    warning:  'text-amber-600',
    critical: 'text-red-600',
    info:     'text-blue-600',
  }
  return (
    <div className="rounded-lg border border-[var(--border-default)] bg-white p-3">
      <div className="text-[10px] font-medium uppercase tracking-wider text-[var(--text-tertiary)]">{label}</div>
      <div className={`mt-1 text-xl font-bold tabular-nums ${tones[tone]}`}>
        {value}{suffix && <span className="text-sm font-normal text-[var(--text-tertiary)] ml-1">{suffix}</span>}
      </div>
      {hint && <div className="mt-0.5 text-[10px] text-[var(--text-tertiary)]">{hint}</div>}
    </div>
  )
}

/** Lista de issues accionables (severidad + acción). */
export function IssueList({ issues }: { issues: SnapshotIssue[] }) {
  const { t } = useTranslation()
  if (issues.length === 0) {
    return (
      <div className="flex items-center gap-2 rounded-md bg-emerald-50 border border-emerald-200 px-3 py-2 text-xs text-emerald-800">
        <CheckCircle className="h-4 w-4 shrink-0" strokeWidth={2} />
        {t('report.snap_no_issues')}
      </div>
    )
  }
  return (
    <ul className="space-y-2">
      {issues.map((i, idx) => (
        <li key={idx} className={`rounded-md border px-3 py-2 text-xs ${
          i.severity === 'critical'
            ? 'border-red-200 bg-red-50 text-red-900'
            : i.severity === 'warning'
            ? 'border-amber-200 bg-amber-50 text-amber-900'
            : 'border-blue-200 bg-blue-50 text-blue-900'
        }`}>
          <div className="flex items-start gap-2">
            <SeverityIcon severity={i.severity} />
            <div className="flex-1 min-w-0">
              <div className="font-semibold">{i.title}</div>
              {i.action && <div className="mt-1 text-[11px] leading-relaxed opacity-90">{i.action}</div>}
            </div>
          </div>
        </li>
      ))}
    </ul>
  )
}

export function SeverityIcon({ severity }: { severity: string }) {
  const cls = 'h-3.5 w-3.5 shrink-0 mt-0.5'
  if (severity === 'critical') return <AlertCircle className={`${cls} text-red-600`} strokeWidth={2} />
  if (severity === 'warning')  return <AlertTriangle className={`${cls} text-amber-600`} strokeWidth={2} />
  if (severity === 'good')     return <CheckCircle className={`${cls} text-emerald-600`} strokeWidth={2} />
  return <Info className={`${cls} text-blue-600`} strokeWidth={2} />
}

export function StatusBadge({ status }: { status: string }) {
  const { t } = useTranslation()
  const map: Record<string, { variant: 'success' | 'warning' | 'destructive' | 'secondary'; labelKey: string }> = {
    good:     { variant: 'success',     labelKey: 'report.snap_status_ok' },
    warning:  { variant: 'warning',     labelKey: 'report.snap_status_warning' },
    critical: { variant: 'destructive', labelKey: 'report.snap_status_critical' },
    info:     { variant: 'secondary',   labelKey: 'report.snap_status_info' },
    safe:     { variant: 'success',     labelKey: 'report.snap_status_safe' },
    outdated: { variant: 'warning',     labelKey: 'report.snap_status_outdated' },
    vulnerable: { variant: 'destructive', labelKey: 'report.snap_status_vulnerable' },
    outdated_vulnerable: { variant: 'destructive', labelKey: 'report.snap_status_outdated_vulnerable' },
  }
  const m = map[status]
  const label = m ? t(m.labelKey) : status
  const variant = m?.variant ?? 'secondary'
  return <Badge variant={variant} className="text-[10px] px-1.5 py-0">{label}</Badge>
}

export function VulnIcon({ status }: { status: string }) {
  if (status === 'vulnerable' || status === 'outdated_vulnerable')
    return <ShieldAlert className="h-4 w-4 text-red-600" strokeWidth={2} />
  if (status === 'outdated')
    return <AlertTriangle className="h-4 w-4 text-amber-600" strokeWidth={2} />
  return <Shield className="h-4 w-4 text-emerald-600" strokeWidth={1.5} />
}

/** Lista simple en forma de tabla key:value. */
export function KeyValueList({ rows }: { rows: Array<[string, ReactNode]> }) {
  return (
    <dl className="divide-y divide-[var(--border-default)] text-sm">
      {rows.map(([k, v], i) => (
        <div key={i} className="flex justify-between gap-3 py-1.5">
          <dt className="text-[var(--text-tertiary)]">{k}</dt>
          <dd className="text-right text-[var(--text-primary)] font-medium tabular-nums truncate">{v}</dd>
        </div>
      ))}
    </dl>
  )
}

/** Yes/No visual compacto */
export function YesNo({ value, positive = true }: { value: boolean; positive?: boolean }) {
  const { t } = useTranslation()
  // Si positive=true, true es "bueno" (verde); si positive=false, false es "bueno"
  const isGood = positive ? value : !value
  return (
    <span className={`inline-flex items-center gap-1 ${isGood ? 'text-emerald-600' : 'text-red-600'}`}>
      {isGood ? <CheckCircle className="h-3 w-3" /> : <AlertCircle className="h-3 w-3" />}
      {value ? t('report.snap_yes') : t('report.snap_no')}
    </span>
  )
}
