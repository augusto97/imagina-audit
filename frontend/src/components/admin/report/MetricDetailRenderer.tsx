import i18n from '@/i18n'
import type { MetricResult } from '@/types/audit'

/**
 * Renderer de detalles técnicos por métrica.
 *
 * Cada métrica del audit puede incluir un objeto `details` con datos
 * específicos (p. ej. emisor del SSL, lista de archivos expuestos).
 * Esta función inspecciona el `metricId` y devuelve el JSX adecuado.
 * Retorna null si la métrica no tiene un renderer específico.
 *
 * El chain de `if` se mantiene plano (en vez de un registry) para que
 * cada rama sea fácil de buscar por el string del `metricId`.
 *
 * Como la función no es un componente (no puede usar hooks con early
 * return), llamamos directamente a `i18n.t()` — el caller re-renderiza
 * al cambiar el idioma y eso vuelve a evaluar estas strings.
 */
const t = (key: string, params?: Record<string, unknown>) => i18n.t(key, params)

export function renderTechnicalDetails(metricId: string, details: Record<string, unknown>, metric?: MetricResult) {
  if (!details || Object.keys(details).length === 0) return null

  // SSL certificate details
  if (metricId === 'ssl_valid' && (details.issuer || details.validTo)) {
    return (
      <div className="mt-2 rounded-lg bg-white/60 border border-[var(--border-default)] p-3 text-xs space-y-1">
        {details.issuer != null && <div><span className="font-semibold">{t('report.md_ssl_issuer')}</span> {String(details.issuer)}</div>}
        {details.protocol != null && <div><span className="font-semibold">{t('report.md_ssl_protocol')}</span> {String(details.protocol)}</div>}
        {details.validFrom != null && <div><span className="font-semibold">{t('report.md_ssl_valid_from')}</span> {String(details.validFrom)}</div>}
        {details.validTo != null && <div><span className="font-semibold">{t('report.md_ssl_expires')}</span> <span className={Number(details.daysRemaining) < 30 ? 'text-red-600 font-bold' : ''}>{String(details.validTo)} {t('report.md_ssl_days_remaining', { days: details.daysRemaining })}</span></div>}
      </div>
    )
  }

  // Exposed headers — show what to remove
  if (metricId === 'exposed_headers' && Array.isArray(details.exposed) && (details.exposed as string[]).length > 0) {
    return (
      <div className="mt-2 rounded-lg bg-white/60 border border-amber-200 p-3">
        <p className="text-xs font-bold text-amber-700 mb-1">{t('report.md_exposed_headers_title')}</p>
        <div className="space-y-1 text-xs">
          {(details.exposed as string[]).map((h, i) => (
            <div key={i} className="font-mono text-amber-800">{h}</div>
          ))}
        </div>
        <p className="text-xs text-[var(--text-secondary)] mt-2">
          {t('report.md_exposed_headers_note_prefix')}<code className="font-mono bg-gray-100 px-1 rounded">Header unset X-Powered-By</code>{t('report.md_exposed_headers_note_and')}<code className="font-mono bg-gray-100 px-1 rounded">ServerTokens Prod</code>{t('report.md_exposed_headers_note_suffix')}
        </p>
      </div>
    )
  }

  // WordPress version — show upgrade target
  if (metricId === 'wp_version' && details.latestVersion) {
    return (
      <div className="mt-2 rounded-lg bg-white/60 border border-[var(--border-default)] p-3 text-xs">
        <span className="font-semibold">{t('report.md_wp_version_installed')}</span> {String(metric?.value || '?')} →{' '}
        <span className="font-semibold text-emerald-600">{t('report.md_wp_version_update_to')} {String(details.latestVersion)}</span>
        <p className="text-[var(--text-tertiary)] mt-1">{t('report.md_wp_version_note')}</p>
      </div>
    )
  }

  // Theme info
  if (metricId === 'wp_theme' && (details.themeName || details.childTheme !== undefined)) {
    return (
      <div className="mt-2 rounded-lg bg-white/60 border border-[var(--border-default)] p-3 text-xs space-y-1">
        {details.themeName != null && <div><span className="font-semibold">{t('report.md_theme_theme')}</span> {String(details.themeName)}</div>}
        {details.themeVersion != null && <div><span className="font-semibold">{t('report.md_theme_version')}</span> {String(details.themeVersion)}</div>}
        <div><span className="font-semibold">{t('report.md_theme_child_label')}</span> {details.childTheme ? <span className="text-emerald-600">{t('report.md_theme_child_yes')}</span> : <span className="text-amber-600">{t('report.md_theme_child_no')}</span>}</div>
      </div>
    )
  }

  // REST API exposed users
  if (metricId === 'rest_api_exposed' && details.users && Array.isArray(details.users)) {
    return (
      <div className="mt-2 rounded-lg bg-white/60 border border-red-200 p-3">
        <p className="text-xs font-bold text-red-600 mb-1">{t('report.md_rest_exposed_title')}</p>
        <div className="flex flex-wrap gap-2">
          {(details.users as string[]).map((u, i) => (
            <span key={i} className="text-xs font-mono px-2 py-0.5 bg-red-100 rounded text-red-700">{u}</span>
          ))}
        </div>
        <p className="text-xs text-[var(--text-secondary)] mt-2">{t('report.md_rest_exposed_note')}</p>
      </div>
    )
  }

  // User enumeration
  if (metricId === 'user_enumeration' && details.username) {
    return (
      <div className="mt-2 rounded-lg bg-white/60 border border-amber-200 p-3 text-xs">
        <span className="font-semibold">{t('report.md_user_enum_label')}</span>{' '}
        <span className="font-mono text-amber-700">{String(details.username)}</span>
        <p className="text-[var(--text-secondary)] mt-1">{t('report.md_user_enum_note_prefix')}<br/>
          <code className="font-mono bg-gray-100 px-1 rounded">RewriteRule ^/?author= - [F,L]</code>
        </p>
      </div>
    )
  }

  // Images missing alt — show file names
  if (metricId === 'images_alt' && Array.isArray(details.missingExamples) && (details.missingExamples as string[]).length > 0) {
    return (
      <div className="mt-2 rounded-lg bg-white/60 border border-[var(--border-default)] p-3">
        <p className="text-xs font-bold text-[var(--text-tertiary)] mb-1">{t('report.md_images_alt_title', { count: details.withoutAlt })}</p>
        <div className="flex flex-wrap gap-1.5">
          {(details.missingExamples as string[]).map((f, i) => (
            <span key={i} className="text-[10px] font-mono px-2 py-0.5 bg-gray-100 rounded text-[var(--text-secondary)] break-all">{f}</span>
          ))}
        </div>
      </div>
    )
  }

  // Internal links stats
  if (metricId === 'internal_links' && (details.internal !== undefined || details.external !== undefined)) {
    return (
      <div className="mt-2 flex flex-wrap gap-3 text-xs">
        <span className="px-2 py-1 rounded-lg bg-white/60 border border-[var(--border-default)]">{t('report.md_links_internal')} <b>{String(details.internal ?? 0)}</b></span>
        <span className="px-2 py-1 rounded-lg bg-white/60 border border-[var(--border-default)]">{t('report.md_links_external')} <b>{String(details.external ?? 0)}</b></span>
        {Number(details.nofollow) > 0 && <span className="px-2 py-1 rounded-lg bg-white/60 border border-[var(--border-default)]">{t('report.md_links_nofollow')} <b>{String(details.nofollow)}</b></span>}
        {Number(details.emptyAnchors) > 0 && <span className="px-2 py-1 rounded-lg bg-amber-50 border border-amber-200">{t('report.md_links_empty_anchor')} <b>{String(details.emptyAnchors)}</b></span>}
      </div>
    )
  }

  // Directory listing
  if (metricId === 'directory_listing' && Array.isArray(details.exposed)) {
    return (
      <div className="mt-2 rounded-lg bg-white/60 border border-red-200 p-3">
        <p className="text-xs font-bold text-red-600 mb-1">{t('report.md_dir_listing_title')}</p>
        <ul className="space-y-1">
          {(details.exposed as string[]).map((d, i) => (
            <li key={i} className="text-xs font-mono text-red-700">{d}</li>
          ))}
        </ul>
        <p className="text-xs text-[var(--text-secondary)] mt-2">{t('report.md_dir_listing_note_prefix')}<code className="font-mono bg-gray-100 px-1 rounded">Options -Indexes</code>{t('report.md_dir_listing_note_suffix')}</p>
      </div>
    )
  }

  // Plugins con vulnerabilidades o desactualizados
  if (metricId === 'wp_plugins' && Array.isArray(details.plugins)) {
    const plugins = details.plugins as Array<Record<string, unknown>>
    const outdated = plugins.filter(p => p.outdated)
    if (outdated.length === 0) return null
    return (
      <div className="mt-2 rounded-lg bg-white/60 border border-[var(--border-default)] overflow-hidden">
        <table className="w-full text-xs">
          <thead><tr className="bg-[var(--bg-tertiary)]">
            <th className="text-left px-3 py-1.5 font-semibold">{t('report.md_plugins_plugin')}</th>
            <th className="text-left px-3 py-1.5 font-semibold">{t('report.md_plugins_installed')}</th>
            <th className="text-left px-3 py-1.5 font-semibold">{t('report.md_plugins_update_to')}</th>
          </tr></thead>
          <tbody>
            {outdated.map((p, i) => (
              <tr key={i} className="border-t border-[var(--border-default)]">
                <td className="px-3 py-1.5 font-medium">{String(p.name)}</td>
                <td className="px-3 py-1.5 text-red-600">{String(p.detectedVersion || '?')}</td>
                <td className="px-3 py-1.5 text-emerald-600 font-semibold">{String(p.latestVersion || '?')}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    )
  }

  // Archivos sensibles
  if (metricId === 'sensitive_files' && Array.isArray(details.files) && (details.files as string[]).length > 0) {
    return (
      <div className="mt-2 rounded-lg bg-white/60 border border-red-200 p-3">
        <p className="text-xs font-bold text-red-600 mb-1">{t('report.md_sensitive_title')}</p>
        <ul className="space-y-1">
          {(details.files as string[]).map((f, i) => (
            <li key={i} className="text-xs font-mono text-red-700">{f}</li>
          ))}
        </ul>
        <p className="text-xs text-[var(--text-secondary)] mt-2">{t('report.md_sensitive_note')}</p>
      </div>
    )
  }

  // Security headers
  if (metricId === 'security_headers' && details.missing && Array.isArray(details.missing)) {
    return (
      <div className="mt-2 rounded-lg bg-white/60 border border-[var(--border-default)] p-3">
        <p className="text-xs font-bold text-[var(--text-tertiary)] mb-1">{t('report.md_headers_missing_title')}</p>
        <div className="space-y-1.5 font-mono text-xs">
          {(details.missing as string[]).map((h, i) => (
            <div key={i} className="text-[var(--text-primary)]">
              Header set {h} {getHeaderExample(h)}
            </div>
          ))}
        </div>
      </div>
    )
  }

  // Vulnerabilidades
  if (metricId === 'plugin_vulnerabilities' && Array.isArray(details.vulnerabilities)) {
    const vulns = details.vulnerabilities as Array<Record<string, unknown>>
    if (vulns.length === 0) return null

    const cvssColor = (score: number) => {
      if (score >= 9) return 'bg-red-600 text-white'
      if (score >= 7) return 'bg-red-500 text-white'
      if (score >= 4) return 'bg-amber-500 text-white'
      return 'bg-yellow-400 text-gray-900'
    }

    return (
      <div className="mt-2 space-y-2">
        {vulns.map((v, i) => (
          <div key={i} className="rounded-lg border border-red-200 bg-red-50/50 p-3">
            <div className="flex items-start justify-between gap-3">
              <div className="min-w-0 flex-1">
                <div className="flex items-center gap-2 flex-wrap">
                  <span className="font-semibold text-sm text-gray-900">{String(v.pluginName || v.plugin)}</span>
                  {v.cveId != null && <span className="text-[10px] font-mono text-gray-500">{String(v.cveId)}</span>}
                </div>
                {v.name != null && <p className="text-xs text-gray-600 mt-1">{String(v.name)}</p>}
                <div className="flex items-center gap-3 mt-2 text-[11px] text-gray-500">
                  {v.fixedInVersion != null && !v.unfixed && <span>{t('report.md_vuln_fix', { version: v.fixedInVersion })}</span>}
                  {v.unfixed === true && <span className="font-semibold text-red-600">{t('report.md_vuln_unfixed')}</span>}
                  {v.affectedVersions != null && <span>{t('report.md_vuln_affects', { range: v.affectedVersions })}</span>}
                </div>
              </div>
              {v.cvssScore != null && Number(v.cvssScore) > 0 && (
                <div className="shrink-0 text-center">
                  <div className={`inline-block px-2.5 py-1 rounded-md text-sm font-bold ${cvssColor(Number(v.cvssScore))}`}>
                    {Number(v.cvssScore).toFixed(1)}
                  </div>
                  <div className="text-[9px] text-gray-400 mt-0.5">{t('report.md_vuln_cvss')}</div>
                </div>
              )}
            </div>
          </div>
        ))}
      </div>
    )
  }

  // Open Graph / Twitter Cards tags
  if ((metricId === 'open_graph' || metricId === 'twitter_cards') && details.tags) {
    const tags = details.tags as Record<string, string | null>
    return (
      <div className="mt-2 rounded-lg bg-white/60 border border-[var(--border-default)] p-3">
        <p className="text-xs font-bold text-[var(--text-tertiary)] mb-1">{t('report.md_tags_title')}</p>
        <div className="space-y-1 text-xs">
          {Object.entries(tags).map(([key, val]) => (
            <div key={key} className="flex gap-2">
              <span className="font-mono font-semibold w-36 shrink-0">{key}</span>
              <span className={val ? 'text-[var(--text-secondary)]' : 'text-red-500 font-medium'}>
                {val ? (String(val).length > 60 ? String(val).substring(0, 60) + '...' : String(val)) : t('report.md_tags_missing')}
              </span>
            </div>
          ))}
        </div>
      </div>
    )
  }

  // PageSpeed opportunities
  if (metricId === 'pagespeed_opportunities' && Array.isArray(details.opportunities)) {
    const opps = details.opportunities as Array<Record<string, unknown>>
    if (opps.length === 0) return null
    return (
      <div className="mt-2 rounded-lg bg-white/60 border border-[var(--border-default)] p-3">
        <p className="text-xs font-bold text-[var(--text-tertiary)] mb-1">{t('report.md_opps_title')}</p>
        <div className="space-y-1.5 text-xs">
          {opps.map((o, i) => (
            <div key={i} className="flex items-center justify-between gap-2">
              <span className="text-[var(--text-primary)]">{String(o.title)}</span>
              {Number(o.savings) > 0 && (
                <span className="text-amber-600 font-semibold shrink-0">-{(Number(o.savings) / 1000).toFixed(1)}s</span>
              )}
            </div>
          ))}
        </div>
      </div>
    )
  }

  // Heading hierarchy
  // SERP preview
  if (metricId === 'serp_preview' && details.title) {
    const title = String(details.title)
    const desc = String(details.description || '')
    const domain = String(details.domain || '')
    return (
      <div className="mt-2 space-y-3">
        <div className="rounded-lg border border-gray-200 bg-white p-4 max-w-lg">
          <p className="text-xs text-gray-500 mb-1">{t('report.md_serp_preview')}</p>
          <div className="text-xs text-emerald-700 mb-0.5">{domain}</div>
          <div className="text-blue-700 text-base hover:underline cursor-pointer leading-snug">{title.length > 70 ? title.substring(0, 67) + '...' : title}</div>
          <div className="text-xs text-gray-600 mt-1 leading-relaxed">{desc.length > 160 ? desc.substring(0, 157) + '...' : desc || t('report.md_serp_no_description')}</div>
        </div>
        <div className="flex gap-4 text-[10px] text-gray-400">
          <span>{t('report.md_serp_title_chars', { count: details.titleLength })}</span>
          <span>{t('report.md_serp_desc_chars', { count: details.descriptionLength })}</span>
        </div>
      </div>
    )
  }

  // Link stats with detail table
  if (metricId === 'link_stats' && Array.isArray(details.links)) {
    const links = details.links as Array<{ href: string; anchor: string; type: string; follow: string }>
    if (links.length === 0) return null
    return (
      <div className="mt-2 rounded-lg bg-white/60 border border-[var(--border-default)] p-2 max-h-56 overflow-y-auto space-y-1">
        {links.map((l, i) => (
          <div key={i} className="text-[11px] border-b border-gray-100 pb-1 last:border-0">
            <div className="flex items-center gap-1.5 flex-wrap">
              <span className={`text-[9px] px-1.5 py-0.5 rounded ${l.type === 'internal' ? 'bg-blue-50 text-blue-600' : 'bg-amber-50 text-amber-600'}`}>{l.type === 'internal' ? t('report.md_linkstat_int') : t('report.md_linkstat_ext')}</span>
              <span className={`text-[9px] ${l.follow === 'nofollow' ? 'text-red-500' : 'text-gray-400'}`}>{l.follow}</span>
              <span className="text-gray-700 font-medium">{l.anchor}</span>
            </div>
            <div className="text-gray-400 font-mono text-[10px] break-all">{l.href}</div>
          </div>
        ))}
      </div>
    )
  }

  // Keyword density
  if (metricId === 'keyword_density' && (details.topWords || details.topPhrases)) {
    const words = (details.topWords || {}) as Record<string, number>
    const phrases = (details.topPhrases || {}) as Record<string, number>
    return (
      <div className="mt-2 rounded-lg bg-white/60 border border-[var(--border-default)] p-3 text-xs space-y-2">
        {Object.keys(words).length > 0 && (
          <div>
            <p className="font-bold text-[var(--text-tertiary)] mb-1">{t('report.md_kw_top_words')}</p>
            <div className="flex flex-wrap gap-1.5">
              {Object.entries(words).map(([w, c]) => (
                <span key={w} className="px-2 py-0.5 rounded bg-gray-100 text-gray-700">{w} <b>{String(c)}</b></span>
              ))}
            </div>
          </div>
        )}
        {Object.keys(phrases).length > 0 && (
          <div>
            <p className="font-bold text-[var(--text-tertiary)] mb-1">{t('report.md_kw_top_phrases')}</p>
            <div className="flex flex-wrap gap-1.5">
              {Object.entries(phrases).map(([p, c]) => (
                <span key={p} className="px-2 py-0.5 rounded bg-blue-50 text-blue-700">{p} <b>{String(c)}</b></span>
              ))}
            </div>
          </div>
        )}
      </div>
    )
  }

  // URL resolution
  if (metricId === 'url_resolution' && Array.isArray(details.results)) {
    const results = details.results as Array<{ variant: string; redirectsTo: string; matches: boolean; status: number }>
    return (
      <div className="mt-2 space-y-1.5">
        {results.map((r, i) => (
          <div key={i} className="rounded-lg bg-white/60 border border-[var(--border-default)] p-2 text-xs">
            <div className="font-mono text-gray-600 break-all">{r.variant}</div>
            <div className="font-mono text-gray-500 break-all mt-0.5">→ {r.redirectsTo}</div>
            <span className={`text-[10px] font-medium ${r.matches ? 'text-emerald-600' : 'text-red-500'}`}>{r.matches ? t('report.md_urlres_ok') : t('report.md_urlres_mismatch')}</span>
          </div>
        ))}
      </div>
    )
  }

  if (metricId === 'heading_hierarchy') {
    const counts = (details.counts || {}) as Record<string, number>
    const headings = (details.headings || []) as Array<{ level: number; tag: string; text: string }>
    return (
      <div className="mt-2 space-y-2">
        <div className="flex gap-3 text-xs">
          {Object.entries(counts).filter(([, v]) => v > 0).map(([tag, count]) => (
            <span key={tag} className="px-2 py-1 rounded-lg bg-white/60 border border-[var(--border-default)] font-mono">
              {tag.toUpperCase()}: {count}
            </span>
          ))}
        </div>
        {headings.length > 0 && (
          <div className="rounded-lg bg-white/60 border border-[var(--border-default)] p-3 text-xs space-y-1 max-h-64 overflow-y-auto">
            {headings.map((h, i) => (
              <div key={i} className="flex items-start gap-2" style={{ paddingLeft: `${(h.level - 1) * 16}px` }}>
                <span className="shrink-0 font-mono font-bold text-[var(--accent-primary)]">{h.tag}</span>
                <span className="text-gray-700">{h.text || t('report.md_heading_empty')}</span>
              </div>
            ))}
          </div>
        )}
      </div>
    )
  }

  // Broken resources
  if (metricId === 'broken_resources' && Array.isArray(details.broken) && (details.broken as Array<Record<string, unknown>>).length > 0) {
    const broken = details.broken as Array<{ url: string; type: string; status: number }>
    return (
      <div className="mt-2 rounded-lg bg-white/60 border border-red-200 p-3">
        <p className="text-xs font-bold text-red-600 mb-1">{t('report.md_broken_title', { count: broken.length, total: details.checked })}</p>
        {broken.map((b, i) => (
          <div key={i} className="text-xs flex items-center gap-2 py-0.5">
            <span className="text-red-600 font-mono">{b.status}</span>
            <span className="text-gray-500">[{b.type}]</span>
            <span className="text-gray-700 truncate">{b.url}</span>
          </div>
        ))}
      </div>
    )
  }

  // HTML errors
  if (metricId === 'html_errors' && Array.isArray(details.errors) && (details.errors as string[]).length > 0) {
    return (
      <div className="mt-2 rounded-lg bg-white/60 border border-amber-200 p-3">
        <p className="text-xs font-bold text-amber-700 mb-1">{t('report.md_html_errors_title')}</p>
        <ul className="space-y-0.5">
          {(details.errors as string[]).map((e, i) => <li key={i} className="text-xs text-gray-700">- {e}</li>)}
        </ul>
        {Number(details.inlineStyles) > 20 && <p className="text-xs text-gray-500 mt-1">{t('report.md_html_errors_inline', { count: details.inlineStyles })}</p>}
      </div>
    )
  }

  // Oversize headings
  if (metricId === 'oversize_headings' && Array.isArray(details.oversized) && (details.oversized as Array<Record<string, unknown>>).length > 0) {
    const items = details.oversized as Array<{ tag: string; text: string; length: number; maxLength: number }>
    return (
      <div className="mt-2 rounded-lg bg-white/60 border border-amber-200 p-3 text-xs space-y-1">
        {items.map((h, i) => (
          <div key={i}><span className="font-mono font-bold text-amber-600">{h.tag}</span> <span className="text-gray-500">{t('report.md_oversize_heading_meta', { length: h.length, max: h.maxLength })}</span>: <span className="text-gray-700">{h.text}</span></div>
        ))}
      </div>
    )
  }

  // Oversized alt
  if (metricId === 'oversized_alt' && Array.isArray(details.oversized) && (details.oversized as Array<Record<string, unknown>>).length > 0) {
    const items = details.oversized as Array<{ file: string; altLength: number; altPreview: string }>
    return (
      <div className="mt-2 rounded-lg bg-white/60 border border-amber-200 p-3 text-xs space-y-1">
        {items.map((a, i) => (
          <div key={i}><span className="font-medium text-gray-700">{a.file}</span> <span className="text-amber-600">{t('report.md_oversize_alt_length', { length: a.altLength })}</span>: <span className="text-gray-500">{a.altPreview}</span></div>
        ))}
      </div>
    )
  }

  // Exposed emails
  if (metricId === 'exposed_email' && Array.isArray(details.emails) && (details.emails as string[]).length > 0) {
    return (
      <div className="mt-2 flex flex-wrap gap-1.5">
        {(details.emails as string[]).map((e, i) => (
          <span key={i} className="text-xs font-mono px-2 py-0.5 bg-amber-50 border border-amber-200 rounded text-amber-700">{e}</span>
        ))}
      </div>
    )
  }

  // DMARC value
  if (metricId === 'dmarc' && details.value) {
    return (
      <div className="mt-2 rounded-lg bg-white/60 border border-[var(--border-default)] p-3">
        <p className="text-xs font-bold text-[var(--text-tertiary)] mb-1">{t('report.md_dmarc_title')}</p>
        <code className="text-[10px] text-gray-600 font-mono break-all">{String(details.value)}</code>
      </div>
    )
  }

  // Structured data types
  if (metricId === 'structured_data' && Array.isArray(details.types)) {
    return (
      <div className="mt-2 flex flex-wrap gap-1.5">
        {(details.types as string[]).map((t, i) => (
          <span key={i} className="text-xs px-2 py-0.5 bg-blue-50 border border-blue-200 rounded text-blue-700">{t}</span>
        ))}
      </div>
    )
  }

  // Cache details
  if (metricId === 'cache_headers' && Array.isArray(details.details)) {
    return (
      <div className="mt-2 rounded-lg bg-white/60 border border-[var(--border-default)] p-3 text-xs space-y-0.5">
        {(details.details as string[]).map((d, i) => <div key={i} className="text-gray-600">{d}</div>)}
      </div>
    )
  }

  // Text/code ratio
  if (metricId === 'text_code_ratio' && details.ratio != null) {
    const ratio = Number(details.ratio)
    return (
      <div className="mt-2 flex items-center gap-3 text-xs">
        <div className="flex-1 bg-gray-100 rounded-full h-3 max-w-xs">
          <div className="h-full rounded-full transition-all" style={{ width: `${Math.min(ratio, 100)}%`, backgroundColor: ratio >= 15 ? '#10B981' : ratio >= 10 ? '#F59E0B' : '#EF4444' }} />
        </div>
        <span className="text-gray-500">{t('report.md_text_code_legend', { text: details.textSize, html: details.htmlSize })}</span>
      </div>
    )
  }

  // Safe Browsing threats
  if (metricId === 'safe_browsing' && Array.isArray(details.threatTypes) && (details.threatTypes as string[]).length > 0) {
    return (
      <div className="mt-2 rounded-lg bg-red-100 border border-red-300 p-3">
        <p className="text-xs font-bold text-red-700 mb-1">{t('report.md_safebrowsing_title')}</p>
        <div className="flex flex-wrap gap-1.5">
          {(details.threatTypes as string[]).map((t, i) => (
            <span key={i} className="text-xs px-2 py-0.5 bg-red-200 rounded text-red-800 font-medium">{t}</span>
          ))}
        </div>
      </div>
    )
  }

  // Theme/core vulnerabilities (same format as plugin vulns)
  if ((metricId === 'theme_vulnerabilities' || metricId === 'core_vulnerabilities') && Array.isArray(details.vulnerabilities)) {
    const vulns = details.vulnerabilities as Array<Record<string, unknown>>
    if (vulns.length === 0) return null
    return (
      <div className="mt-2 space-y-1.5">
        {vulns.map((v, i) => (
          <div key={i} className="rounded-lg border border-red-200 bg-red-50/50 p-2 text-xs">
            <span className="font-medium text-gray-900">{String(v.name || v.cve || t('report.md_vuln_fallback_name'))}</span>
            {v.cvssScore != null && Number(v.cvssScore) > 0 && <span className="ml-2 px-1.5 py-0.5 rounded bg-red-500 text-white text-[10px] font-bold">{Number(v.cvssScore).toFixed(1)}</span>}
            {v.fixedInVersion != null && <span className="ml-2 text-emerald-600">{t('report.md_vuln_fix', { version: v.fixedInVersion })}</span>}
          </div>
        ))}
      </div>
    )
  }

  // Sitemap details
  if (metricId === 'sitemap' && (details.url || details.count)) {
    return (
      <div className="mt-2 text-xs text-gray-600 space-y-0.5">
        {details.url != null && <div>{t('report.md_sitemap_url')} <span className="font-mono">{String(details.url)}</span></div>}
        {details.isIndex === true && <div>{t('report.md_sitemap_type_index')}</div>}
        {Number(details.count) > 0 && <div>{details.isIndex ? t('report.md_sitemap_count_sub') : t('report.md_sitemap_count_urls')} <b>{String(details.count)}</b></div>}
      </div>
    )
  }

  // Robots.txt details
  if (metricId === 'robots' && (details.lineCount || details.disallowCount)) {
    return (
      <div className="mt-2 flex gap-3 text-xs">
        <span className="px-2 py-1 rounded bg-gray-50 border border-gray-200">{t('report.md_robots_directives')} <b>{String(details.lineCount)}</b></span>
        <span className="px-2 py-1 rounded bg-gray-50 border border-gray-200">{t('report.md_robots_disallow')} <b>{String(details.disallowCount)}</b></span>
        {details.hasSitemap === true && <span className="px-2 py-1 rounded bg-emerald-50 border border-emerald-200 text-emerald-700">{t('report.md_robots_includes_sitemap')}</span>}
      </div>
    )
  }

  // Hreflang languages
  if (metricId === 'hreflang' && Array.isArray(details.languages)) {
    return (
      <div className="mt-2 flex flex-wrap gap-1.5">
        {(details.languages as string[]).map((l, i) => (
          <span key={i} className="text-xs px-2 py-0.5 bg-indigo-50 border border-indigo-200 rounded text-indigo-700 font-mono">{l}</span>
        ))}
      </div>
    )
  }

  // Mixed content count
  if (metricId === 'mixed_content' && Number(details.count) > 0) {
    return (
      <div className="mt-2 text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-lg p-3">
        {t('report.md_mixed_content', { count: details.count })}
      </div>
    )
  }

  return null
}

function getHeaderExample(header: string): string {
  const examples: Record<string, string> = {
    'X-Content-Type-Options': '"nosniff"',
    'X-Frame-Options': '"SAMEORIGIN"',
    'Content-Security-Policy': '"default-src \'self\'"',
    'Strict-Transport-Security': '"max-age=31536000; includeSubDomains"',
    'X-XSS-Protection': '"1; mode=block"',
    'Referrer-Policy': '"strict-origin-when-cross-origin"',
    'Permissions-Policy': '"camera=(), microphone=(), geolocation=()"',
  }
  return examples[header] || '"value"'
}
