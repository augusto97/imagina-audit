import { FileCode2 } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { SectionCard, KpiTile, IssueList } from './ui'
import type { SnapshotReport } from '@/types/snapshotReport'

/**
 * Post types custom, taxonomies custom y top namespaces REST.
 * Útil para entender el stack del cliente (WooCommerce, CPT UI, etc.)
 * y detectar endpoints REST que podrían exponer datos.
 */
export default function ContentSection({ report }: { report: SnapshotReport }) {
  const { t } = useTranslation()
  const s = report.content.summary
  const customPostTypes = report.content.postTypes.filter(p => !p.isBuiltin)
  const customTaxonomies = report.content.taxonomies.filter(tx => !tx.isBuiltin)
  const customSuffix = (count: number) => t('report.snap_content_custom_suffix', { count })

  return (
    <SectionCard
      title={t('report.snap_content_title')}
      subtitle={t('report.snap_content_subtitle', {
        pt: s.totalPostTypes, cpt: s.customPostTypes,
        tax: s.totalTaxonomies, ctax: s.customTaxonomies,
        rest: s.totalRestRoutes,
      })}
      icon={<FileCode2 className="h-4 w-4 text-[var(--accent-primary)]" strokeWidth={1.75} />}
    >
      <div className="mb-4 grid grid-cols-2 gap-2 sm:grid-cols-4">
        <KpiTile label={t('report.snap_content_kpi_post_types')} value={s.totalPostTypes} hint={customSuffix(s.customPostTypes)} />
        <KpiTile label={t('report.snap_content_kpi_taxonomies')} value={s.totalTaxonomies} hint={customSuffix(s.customTaxonomies)} />
        <KpiTile
          label={t('report.snap_content_kpi_routes')}
          value={s.totalRestRoutes}
          tone={s.totalRestRoutes > 800 ? 'warning' : 'neutral'}
          hint={t('report.snap_content_namespaces_hint', { count: s.restNamespaces })}
        />
        <KpiTile
          label={t('report.snap_content_kpi_cpt_rest')}
          value={customPostTypes.filter(p => p.showInRest).length}
          hint={t('report.snap_content_rest_exposed_hint')}
        />
      </div>

      <div className="grid gap-4 lg:grid-cols-3">
        {/* CPTs custom */}
        <div>
          <h4 className="mb-2 text-xs font-semibold uppercase tracking-wider text-[var(--text-tertiary)]">
            {t('report.snap_content_cpt_title', { count: customPostTypes.length })}
          </h4>
          <div className="overflow-hidden rounded-lg border border-[var(--border-default)]">
            <table className="w-full text-[11px]">
              <thead className="bg-[var(--bg-secondary)] text-left text-[10px] uppercase tracking-wider text-[var(--text-tertiary)]">
                <tr>
                  <th className="px-2 py-1.5">{t('report.snap_col_slug')}</th>
                  <th className="px-2 py-1.5">{t('report.snap_col_label')}</th>
                  <th className="px-2 py-1.5">{t('report.snap_content_col_rest_short')}</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-[var(--border-default)]">
                {customPostTypes.map((p) => (
                  <tr key={String(p.slug)}>
                    <td className="px-2 py-1 font-mono text-[10px]">{String(p.slug)}</td>
                    <td className="px-2 py-1">{String(p.label || '')}</td>
                    <td className="px-2 py-1 text-[10px]">
                      {p.showInRest ? <span className="text-emerald-600">{t('report.snap_content_yes_short')}</span> : <span className="text-[var(--text-tertiary)]">{t('report.snap_content_no_short')}</span>}
                    </td>
                  </tr>
                ))}
                {customPostTypes.length === 0 && (
                  <tr><td colSpan={3} className="px-2 py-2 text-center text-[var(--text-tertiary)]">{t('report.snap_none_masc')}</td></tr>
                )}
              </tbody>
            </table>
          </div>
        </div>

        {/* Taxonomies custom */}
        <div>
          <h4 className="mb-2 text-xs font-semibold uppercase tracking-wider text-[var(--text-tertiary)]">
            {t('report.snap_content_ctax_title', { count: customTaxonomies.length })}
          </h4>
          <div className="overflow-hidden rounded-lg border border-[var(--border-default)]">
            <table className="w-full text-[11px]">
              <thead className="bg-[var(--bg-secondary)] text-left text-[10px] uppercase tracking-wider text-[var(--text-tertiary)]">
                <tr>
                  <th className="px-2 py-1.5">{t('report.snap_col_slug')}</th>
                  <th className="px-2 py-1.5">{t('report.snap_col_label')}</th>
                  <th className="px-2 py-1.5">{t('report.snap_content_col_hier_short')}</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-[var(--border-default)]">
                {customTaxonomies.map((tx) => (
                  <tr key={String(tx.slug)}>
                    <td className="px-2 py-1 font-mono text-[10px]">{String(tx.slug)}</td>
                    <td className="px-2 py-1">{String(tx.label || '')}</td>
                    <td className="px-2 py-1 text-[10px]">{tx.hierarchical ? t('report.snap_content_yes_short') : t('report.snap_content_no_short')}</td>
                  </tr>
                ))}
                {customTaxonomies.length === 0 && (
                  <tr><td colSpan={3} className="px-2 py-2 text-center text-[var(--text-tertiary)]">{t('report.snap_none_fem')}</td></tr>
                )}
              </tbody>
            </table>
          </div>
        </div>

        {/* Top namespaces REST */}
        <div>
          <h4 className="mb-2 text-xs font-semibold uppercase tracking-wider text-[var(--text-tertiary)]">
            {t('report.snap_content_rest_title')}
          </h4>
          <div className="overflow-hidden rounded-lg border border-[var(--border-default)]">
            <table className="w-full text-[11px]">
              <thead className="bg-[var(--bg-secondary)] text-left text-[10px] uppercase tracking-wider text-[var(--text-tertiary)]">
                <tr>
                  <th className="px-2 py-1.5">{t('report.snap_col_namespace')}</th>
                  <th className="px-2 py-1.5 text-right">{t('report.snap_content_col_routes')}</th>
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
