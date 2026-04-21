import { memo } from 'react'
import { useTranslation } from 'react-i18next'
import type { AuditResult } from '@/types/audit'

/**
 * Sección con el stack tecnológico detectado y datos de hosting/dominio.
 */
export const TechStackSummary = memo(function TechStackSummary({
  techStack,
  scanDuration,
}: {
  techStack: NonNullable<AuditResult['techStack']>
  scanDuration: number
}) {
  const { t } = useTranslation()
  const hosting = techStack.hostingInfo
  const domain = techStack.domainInfo

  const techItems: Array<{ label: string; value: string }> = []
  if (techStack.server) techItems.push({ label: t('report.techstack_server'), value: techStack.server })
  if (techStack.phpVersion) techItems.push({ label: t('report.techstack_php'), value: techStack.phpVersion })
  if (techStack.httpProtocol) techItems.push({ label: t('report.techstack_protocol'), value: techStack.httpProtocol })
  if (techStack.cms) techItems.push({ label: t('report.techstack_cms'), value: techStack.cms })
  if (techStack.pageBuilder?.length) techItems.push({ label: t('report.techstack_page_builder'), value: techStack.pageBuilder.join(', ') })
  if (techStack.ecommerce?.length) techItems.push({ label: t('report.techstack_ecommerce'), value: techStack.ecommerce.join(', ') })
  if (techStack.cachePlugin?.length) techItems.push({ label: t('report.techstack_cache'), value: techStack.cachePlugin.join(', ') })
  if (techStack.seoPlugin?.length) techItems.push({ label: t('report.techstack_seo_plugin'), value: techStack.seoPlugin.join(', ') })
  if (techStack.securityPlugin?.length) techItems.push({ label: t('report.techstack_security_plugin'), value: techStack.securityPlugin.join(', ') })
  if (techStack.cdn) techItems.push({ label: t('report.techstack_cdn'), value: techStack.cdn })
  if (techStack.analytics?.length) techItems.push({ label: t('report.techstack_analytics'), value: techStack.analytics.join(', ') })

  return (
    <div className="rounded-2xl border border-[var(--border-default)] bg-white p-6 space-y-5">
      <div className="flex items-center justify-between">
        <h2 className="text-lg font-bold text-[var(--text-primary)]">{t('report.techstack_title')}</h2>
        {scanDuration > 0 && <span className="text-xs text-[var(--text-tertiary)]">{t('report.techstack_scan_duration', { seconds: (scanDuration / 1000).toFixed(1) })}</span>}
      </div>

      {/* Hosting & Domain info */}
      {(hosting || domain) && (
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
          {hosting && (
            <div className="rounded-xl bg-[var(--bg-secondary)] p-4">
              <h3 className="text-xs font-bold uppercase tracking-wider text-[var(--text-tertiary)] mb-2">{t('report.techstack_hosting')}</h3>
              <div className="space-y-1.5 text-sm">
                {hosting.ip && <div><span className="text-[var(--text-tertiary)]">{t('report.techstack_ip')}:</span> <span className="font-mono font-medium">{hosting.ip}</span></div>}
                {hosting.provider && <div><span className="text-[var(--text-tertiary)]">{t('report.techstack_provider')}:</span> <span className="font-medium">{hosting.provider}</span></div>}
                {(hosting.city || hosting.country) && <div><span className="text-[var(--text-tertiary)]">{t('report.techstack_location')}:</span> <span className="font-medium">{[hosting.city, hosting.country].filter(Boolean).join(', ')}</span></div>}
                {hosting.nameservers && hosting.nameservers.length > 0 && <div><span className="text-[var(--text-tertiary)]">{t('report.techstack_nameservers')}:</span> <span className="font-mono text-xs">{hosting.nameservers.slice(0, 2).join(', ')}</span></div>}
              </div>
            </div>
          )}
          {domain && (
            <div className="rounded-xl bg-[var(--bg-secondary)] p-4">
              <h3 className="text-xs font-bold uppercase tracking-wider text-[var(--text-tertiary)] mb-2">{t('report.techstack_domain')}</h3>
              <div className="space-y-1.5 text-sm">
                {domain.domain && <div><span className="text-[var(--text-tertiary)]">{t('report.techstack_domain_label')}:</span> <span className="font-medium">{domain.domain}</span></div>}
                {domain.registrar && <div><span className="text-[var(--text-tertiary)]">{t('report.techstack_registrar')}:</span> <span className="font-medium">{domain.registrar}</span></div>}
                {domain.createdDate && <div><span className="text-[var(--text-tertiary)]">{t('report.techstack_registered')}:</span> <span className="font-medium">{domain.createdDate}</span></div>}
                {domain.expiryDate && (
                  <div>
                    <span className="text-[var(--text-tertiary)]">{t('report.techstack_expires')}:</span>{' '}
                    <span className={`font-medium ${domain.daysUntilExpiry !== null && domain.daysUntilExpiry !== undefined && domain.daysUntilExpiry < 60 ? 'text-red-600' : ''}`}>
                      {domain.expiryDate}
                      {domain.daysUntilExpiry !== null && domain.daysUntilExpiry !== undefined && (
                        <span className="text-xs ml-1">{t('report.techstack_days_left', { days: domain.daysUntilExpiry })}</span>
                      )}
                    </span>
                  </div>
                )}
              </div>
            </div>
          )}
        </div>
      )}

      {/* Tech stack grid */}
      {techItems.length > 0 && (
        <div className="grid grid-cols-2 sm:grid-cols-3 gap-x-6 gap-y-2">
          {techItems.map(({ label, value }) => (
            <div key={label} className="text-sm">
              <span className="text-[var(--text-tertiary)]">{label}: </span>
              <span className="font-medium text-[var(--text-primary)]">{value}</span>
            </div>
          ))}
        </div>
      )}
    </div>
  )
})
