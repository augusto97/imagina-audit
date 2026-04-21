import { Link } from 'react-router-dom'
import { Database, ShieldAlert, Pin } from 'lucide-react'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import type { DashboardData } from '@/types/dashboard'

/**
 * Resumen de integraciones y datos derivados — cosas que no son KPIs de
 * volumen pero que son interesantes de un vistazo:
 *  - % de auditorías WordPress con snapshot conectado
 *  - Base local de CVEs (cuándo se actualizó por última vez)
 *  - Informes protegidos de la retención (pinned)
 */
export function IntegrationsCard({
  snapshots, vulnerabilities, pinned,
}: {
  snapshots: DashboardData['snapshots']
  vulnerabilities: DashboardData['vulnerabilities']
  pinned: number
}) {
  return (
    <Card className="border-0 shadow-sm">
      <CardHeader className="pb-2">
        <CardTitle className="text-base">Integraciones</CardTitle>
      </CardHeader>
      <CardContent className="space-y-3">
        <MetricRow
          to="/admin/leads"
          icon={<Database className="h-4 w-4 text-emerald-600" strokeWidth={1.5} />}
          label="Snapshots wp-snapshot conectados"
          value={String(snapshots.connected)}
          hint={snapshots.connected > 0
            ? `${snapshots.percentageOfWp}% de los sitios WordPress auditados`
            : 'Aún ninguno — sube un JSON desde cualquier lead'}
        />
        <MetricRow
          to="/admin/vulnerabilities"
          icon={<ShieldAlert className="h-4 w-4 text-red-600" strokeWidth={1.5} />}
          label="CVEs en base local"
          value={String(vulnerabilities.total)}
          hint={vulnerabilities.lastUpdate
            ? `Última sync: ${formatDate(vulnerabilities.lastUpdate)}`
            : 'Sin sincronizar — configura el cron update-vulnerabilities'}
        />
        <MetricRow
          to="/admin/retention"
          icon={<Pin className="h-4 w-4 fill-amber-500 text-amber-500" strokeWidth={1.5} />}
          label="Informes protegidos"
          value={String(pinned)}
          hint={pinned > 0
            ? 'Nunca se borrarán por la retención automática'
            : 'Marca informes importantes con el pin para protegerlos'}
        />
      </CardContent>
    </Card>
  )
}

function MetricRow({
  to, icon, label, value, hint,
}: {
  to: string
  icon: React.ReactNode
  label: string
  value: string
  hint: string
}) {
  return (
    <Link
      to={to}
      className="flex items-center gap-3 rounded-lg border border-[var(--border-default)] bg-white px-3 py-2.5 transition-colors hover:border-[var(--text-tertiary)]"
    >
      <div className="shrink-0">{icon}</div>
      <div className="min-w-0 flex-1">
        <div className="truncate text-xs font-medium text-[var(--text-secondary)]">{label}</div>
        <div className="truncate text-[10px] text-[var(--text-tertiary)]">{hint}</div>
      </div>
      <div className="shrink-0 text-xl font-bold text-[var(--text-primary)] tabular-nums">{value}</div>
    </Link>
  )
}

function formatDate(iso: string): string {
  try {
    return new Date(iso).toLocaleDateString('es-CO', { day: 'numeric', month: 'short', year: 'numeric' })
  } catch {
    return iso
  }
}
