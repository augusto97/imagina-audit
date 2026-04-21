import { FileCode2 } from 'lucide-react'
import { SectionCard, KpiTile, IssueList } from './ui'
import type { SnapshotReport } from '@/types/snapshotReport'

/**
 * Post types custom, taxonomies custom y top namespaces REST.
 * Útil para entender el stack del cliente (WooCommerce, CPT UI, etc.)
 * y detectar endpoints REST que podrían exponer datos.
 */
export default function ContentSection({ report }: { report: SnapshotReport }) {
  const s = report.content.summary
  const customPostTypes = report.content.postTypes.filter(p => !p.isBuiltin)
  const customTaxonomies = report.content.taxonomies.filter(t => !t.isBuiltin)

  return (
    <SectionCard
      title="Contenido y REST API"
      subtitle={`${s.totalPostTypes} post types (${s.customPostTypes} custom) · ${s.totalTaxonomies} taxonomies (${s.customTaxonomies} custom) · ${s.totalRestRoutes} rutas REST`}
      icon={<FileCode2 className="h-4 w-4 text-[var(--accent-primary)]" strokeWidth={1.75} />}
    >
      <div className="mb-4 grid grid-cols-2 gap-2 sm:grid-cols-4">
        <KpiTile label="Post types" value={s.totalPostTypes} hint={`${s.customPostTypes} custom`} />
        <KpiTile label="Taxonomies" value={s.totalTaxonomies} hint={`${s.customTaxonomies} custom`} />
        <KpiTile
          label="Rutas REST"
          value={s.totalRestRoutes}
          tone={s.totalRestRoutes > 800 ? 'warning' : 'neutral'}
          hint={`${s.restNamespaces} namespaces`}
        />
        <KpiTile
          label="CPTs en REST"
          value={customPostTypes.filter(p => p.showInRest).length}
          hint="expuestos públicamente"
        />
      </div>

      <div className="grid gap-4 lg:grid-cols-3">
        {/* CPTs custom */}
        <div>
          <h4 className="mb-2 text-xs font-semibold uppercase tracking-wider text-[var(--text-tertiary)]">
            Custom post types ({customPostTypes.length})
          </h4>
          <div className="overflow-hidden rounded-lg border border-[var(--border-default)]">
            <table className="w-full text-[11px]">
              <thead className="bg-[var(--bg-secondary)] text-left text-[10px] uppercase tracking-wider text-[var(--text-tertiary)]">
                <tr>
                  <th className="px-2 py-1.5">Slug</th>
                  <th className="px-2 py-1.5">Label</th>
                  <th className="px-2 py-1.5">REST</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-[var(--border-default)]">
                {customPostTypes.map((p) => (
                  <tr key={String(p.slug)}>
                    <td className="px-2 py-1 font-mono text-[10px]">{String(p.slug)}</td>
                    <td className="px-2 py-1">{String(p.label || '')}</td>
                    <td className="px-2 py-1 text-[10px]">
                      {p.showInRest ? <span className="text-emerald-600">sí</span> : <span className="text-[var(--text-tertiary)]">no</span>}
                    </td>
                  </tr>
                ))}
                {customPostTypes.length === 0 && (
                  <tr><td colSpan={3} className="px-2 py-2 text-center text-[var(--text-tertiary)]">Ninguno</td></tr>
                )}
              </tbody>
            </table>
          </div>
        </div>

        {/* Taxonomies custom */}
        <div>
          <h4 className="mb-2 text-xs font-semibold uppercase tracking-wider text-[var(--text-tertiary)]">
            Taxonomies custom ({customTaxonomies.length})
          </h4>
          <div className="overflow-hidden rounded-lg border border-[var(--border-default)]">
            <table className="w-full text-[11px]">
              <thead className="bg-[var(--bg-secondary)] text-left text-[10px] uppercase tracking-wider text-[var(--text-tertiary)]">
                <tr>
                  <th className="px-2 py-1.5">Slug</th>
                  <th className="px-2 py-1.5">Label</th>
                  <th className="px-2 py-1.5">Hier.</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-[var(--border-default)]">
                {customTaxonomies.map((t) => (
                  <tr key={String(t.slug)}>
                    <td className="px-2 py-1 font-mono text-[10px]">{String(t.slug)}</td>
                    <td className="px-2 py-1">{String(t.label || '')}</td>
                    <td className="px-2 py-1 text-[10px]">{t.hierarchical ? 'sí' : 'no'}</td>
                  </tr>
                ))}
                {customTaxonomies.length === 0 && (
                  <tr><td colSpan={3} className="px-2 py-2 text-center text-[var(--text-tertiary)]">Ninguna</td></tr>
                )}
              </tbody>
            </table>
          </div>
        </div>

        {/* Top namespaces REST */}
        <div>
          <h4 className="mb-2 text-xs font-semibold uppercase tracking-wider text-[var(--text-tertiary)]">
            Top namespaces REST
          </h4>
          <div className="overflow-hidden rounded-lg border border-[var(--border-default)]">
            <table className="w-full text-[11px]">
              <thead className="bg-[var(--bg-secondary)] text-left text-[10px] uppercase tracking-wider text-[var(--text-tertiary)]">
                <tr>
                  <th className="px-2 py-1.5">Namespace</th>
                  <th className="px-2 py-1.5 text-right">Rutas</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-[var(--border-default)]">
                {report.content.topRestNs.map((n) => (
                  <tr key={n.namespace}>
                    <td className="px-2 py-1 font-mono text-[10px]">{n.namespace}</td>
                    <td className="px-2 py-1 text-right tabular-nums">{n.routes}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <div className="mt-4">
        <IssueList issues={report.content.issues} />
      </div>
    </SectionCard>
  )
}
