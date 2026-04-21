import { Users as UsersIcon } from 'lucide-react'
import { SectionCard, KpiTile, IssueList } from './ui'
import type { SnapshotReport } from '@/types/snapshotReport'

/**
 * Distribución por rol. wp-snapshot no expone usernames para no filtrar
 * PII, pero sí el conteo por rol — suficiente para detectar exceso de
 * administradores o roles custom sospechosos.
 */
export default function UsersSection({ report }: { report: SnapshotReport }) {
  const s = report.users.summary

  return (
    <SectionCard
      title="Usuarios y roles"
      subtitle={`${s.totalUsers} usuarios · ${s.administrators} administradores · ${s.uniqueRoles} roles con usuarios`}
      icon={<UsersIcon className="h-4 w-4 text-[var(--accent-primary)]" strokeWidth={1.75} />}
    >
      <div className="mb-4 grid grid-cols-3 gap-2">
        <KpiTile label="Total usuarios" value={s.totalUsers} />
        <KpiTile
          label="Administradores"
          value={s.administrators}
          tone={s.administrators > 3 ? 'warning' : s.administrators === 0 ? 'info' : 'neutral'}
        />
        <KpiTile label="Roles activos" value={s.uniqueRoles} />
      </div>

      <div className="mb-4 overflow-hidden rounded-lg border border-[var(--border-default)]">
        <table className="w-full text-xs">
          <thead className="bg-[var(--bg-secondary)] text-left text-[10px] uppercase tracking-wider text-[var(--text-tertiary)]">
            <tr>
              <th className="px-3 py-2">Rol</th>
              <th className="px-3 py-2">Slug</th>
              <th className="px-3 py-2 text-right">Usuarios</th>
              <th className="px-3 py-2 text-right">Capabilities</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-[var(--border-default)]">
            {report.users.roles.map((r) => {
              const isAdmin = r.slug === 'administrator'
              return (
                <tr key={r.slug} className={isAdmin ? 'bg-amber-50/40' : 'hover:bg-[var(--bg-secondary)]'}>
                  <td className="px-3 py-1.5 font-medium">{r.name}</td>
                  <td className="px-3 py-1.5 font-mono text-[10px] text-[var(--text-tertiary)]">{r.slug}</td>
                  <td className="px-3 py-1.5 text-right tabular-nums font-semibold">{r.userCount}</td>
                  <td className="px-3 py-1.5 text-right tabular-nums text-[var(--text-tertiary)]">{r.capCount}</td>
                </tr>
              )
            })}
          </tbody>
        </table>
      </div>

      <IssueList issues={report.users.issues} />
    </SectionCard>
  )
}
