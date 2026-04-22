import { useEffect, useState, useMemo } from 'react'
import { useParams, Link } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { Loader2, Globe, Clock, TrendingUp, TrendingDown, Minus, Package, AlertCircle, AlertTriangle } from 'lucide-react'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { useConfigStore } from '@/store/configStore'
import api from '@/lib/api'

interface SharedAudit {
  id: string
  url: string
  globalScore: number
  globalLevel: string
  isWordPress: boolean
  scanDurationMs: number
  createdAt: string
}

interface SharedProject {
  id: number
  name: string
  url: string
  domain: string
  icon: string | null
  color: string | null
  createdAt: string
}

interface SharedResponse {
  project: SharedProject
  audits: SharedAudit[]
  evolution: {
    scoreDelta: number
    issuesDelta: { critical: number; warning: number }
    wordpress: { previousVersion: string | null; latestVersion: string | null; changed: boolean } | null
    plugins: { added: string[]; removed: string[]; kept: string[] }
  } | null
}

/**
 * Vista pública de un proyecto compartido. Accesible sin login vía el
 * share_token del proyecto. Deliberadamente minimalista: score actual,
 * timeline, historial, y diff evolutivo. Sin datos del owner ni del
 * checklist (son internos del dueño).
 */
export default function SharedProjectPage() {
  const { t, i18n } = useTranslation()
  const { token } = useParams<{ token: string }>()
  const { logoUrl, companyName, companyUrl } = useConfigStore((s) => s.config)

  const [data, setData] = useState<SharedResponse | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    let active = true
    if (!token) return
    setLoading(true)
    api.get<{ success: boolean; data: SharedResponse }>('/shared/project.php', { params: { token } })
      .then((res) => {
        if (!active) return
        setData(res.data.data)
        setError(null)
      })
      .catch((err) => {
        if (!active) return
        const msg = (err?.response?.data?.error as string | undefined) ?? 'Error'
        setError(msg)
      })
      .finally(() => { if (active) setLoading(false) })
    return () => { active = false }
  }, [token])

  const timeline = useMemo(() => {
    if (!data) return []
    return [...data.audits].reverse().map((a) => ({
      date: new Date(a.createdAt),
      score: a.globalScore,
      id: a.id,
    }))
  }, [data])

  if (loading) {
    return (
      <div className="flex min-h-screen items-center justify-center bg-[#F4F6F8]">
        <Loader2 className="h-6 w-6 animate-spin text-[var(--accent-primary)]" />
      </div>
    )
  }

  if (error || !data) {
    return (
      <div className="flex min-h-screen items-center justify-center bg-[#F4F6F8]">
        <div className="text-center max-w-md px-6">
          <p className="text-sm text-red-600">{error ?? '—'}</p>
        </div>
      </div>
    )
  }

  const { project, audits, evolution } = data
  const latest = audits[0] ?? null

  return (
    <div className="min-h-screen bg-[#F4F6F8]">
      <header className="border-b border-[var(--border-default)] bg-white">
        <div className="mx-auto flex max-w-5xl items-center justify-between px-6 py-4">
          <a href={companyUrl || '/'} target="_blank" rel="noreferrer" className="flex items-center gap-2">
            {logoUrl && <img src={logoUrl} alt={companyName} className="h-8 w-auto" />}
            <span className="text-sm font-semibold text-[var(--text-primary)]">{t('projects.shared_view_title')}</span>
          </a>
          <span className="text-[11px] text-[var(--text-tertiary)]">{t('projects.shared_view_owner_note')}</span>
        </div>
      </header>

      <main className="mx-auto max-w-5xl space-y-6 px-6 py-8">
        <div>
          <div className="flex items-center gap-2">
            {project.color && <span className="inline-block h-3 w-3 rounded-full" style={{ background: project.color }} />}
            <h1 className="text-2xl font-bold text-[var(--text-primary)]">{project.name}</h1>
          </div>
          <div className="mt-1 flex items-center gap-2 text-xs text-[var(--text-tertiary)]">
            <Globe className="h-3 w-3" />
            <a href={project.url} target="_blank" rel="noreferrer" className="font-mono hover:underline">{project.url}</a>
          </div>
        </div>

        <div className="grid gap-4 md:grid-cols-3">
          <Card className="md:col-span-1">
            <CardHeader className="pb-2">
              <CardTitle className="text-sm font-semibold text-[var(--text-tertiary)]">
                {t('projects.detail_current_score')}
              </CardTitle>
            </CardHeader>
            <CardContent className="pt-0">
              {latest ? (
                <>
                  <div className="flex items-baseline gap-2">
                    <span className="text-4xl font-bold tabular-nums text-[var(--text-primary)]">{latest.globalScore}</span>
                    <span className="text-sm text-[var(--text-tertiary)]">/100</span>
                  </div>
                  <div className="mt-2 text-xs">
                    {evolution === null ? (
                      <span className="text-[var(--text-tertiary)]">—</span>
                    ) : evolution.scoreDelta > 0 ? (
                      <span className="inline-flex items-center gap-1 text-emerald-700">
                        <TrendingUp className="h-3 w-3" />
                        {t('projects.detail_score_change_up', { value: evolution.scoreDelta })}
                      </span>
                    ) : evolution.scoreDelta < 0 ? (
                      <span className="inline-flex items-center gap-1 text-red-600">
                        <TrendingDown className="h-3 w-3" />
                        {t('projects.detail_score_change_down', { value: evolution.scoreDelta })}
                      </span>
                    ) : (
                      <span className="inline-flex items-center gap-1 text-[var(--text-tertiary)]">
                        <Minus className="h-3 w-3" />
                        {t('projects.detail_score_change_flat')}
                      </span>
                    )}
                  </div>
                </>
              ) : (
                <span className="text-sm text-[var(--text-tertiary)]">{t('projects.detail_score_never')}</span>
              )}
            </CardContent>
          </Card>

          <Card className="md:col-span-2">
            <CardHeader className="pb-2">
              <CardTitle className="text-sm font-semibold text-[var(--text-tertiary)]">{t('projects.detail_timeline_title')}</CardTitle>
            </CardHeader>
            <CardContent className="pt-0">
              {timeline.length === 0 ? (
                <p className="py-6 text-center text-sm text-[var(--text-tertiary)]">{t('projects.detail_timeline_empty')}</p>
              ) : (
                <SharedSparkline points={timeline.map(p => p.score)} labels={timeline.map(p => p.date.toLocaleDateString(i18n.language || 'en', { day: 'numeric', month: 'short' }))} />
              )}
            </CardContent>
          </Card>
        </div>

        {/* Evolution — misma lógica que el detail page del owner */}
        {evolution && (
          <Card>
            <CardHeader>
              <CardTitle className="text-base">{t('projects.detail_evolution_title')}</CardTitle>
            </CardHeader>
            <CardContent className="pt-0 space-y-1.5 text-sm">
              {evolution.scoreDelta > 0 && (
                <div className="flex items-center gap-2 rounded-md px-3 py-1.5 bg-emerald-50">
                  <TrendingUp className="h-3.5 w-3.5 text-emerald-700" />
                  <span>{t('projects.detail_evolution_score_up', { value: evolution.scoreDelta })}</span>
                </div>
              )}
              {evolution.scoreDelta < 0 && (
                <div className="flex items-center gap-2 rounded-md px-3 py-1.5 bg-red-50">
                  <TrendingDown className="h-3.5 w-3.5 text-red-600" />
                  <span>{t('projects.detail_evolution_score_down', { value: Math.abs(evolution.scoreDelta) })}</span>
                </div>
              )}
              {evolution.issuesDelta.critical !== 0 && (
                <div className={`flex items-center gap-2 rounded-md px-3 py-1.5 ${evolution.issuesDelta.critical > 0 ? 'bg-red-50' : 'bg-emerald-50'}`}>
                  <AlertCircle className="h-3.5 w-3.5 text-red-600" />
                  <span>{evolution.issuesDelta.critical > 0
                    ? t('projects.detail_evolution_critical_up', { count: evolution.issuesDelta.critical })
                    : t('projects.detail_evolution_critical_down', { count: Math.abs(evolution.issuesDelta.critical) })}</span>
                </div>
              )}
              {evolution.issuesDelta.warning !== 0 && (
                <div className={`flex items-center gap-2 rounded-md px-3 py-1.5 ${evolution.issuesDelta.warning > 0 ? 'bg-red-50' : 'bg-emerald-50'}`}>
                  <AlertTriangle className="h-3.5 w-3.5 text-amber-600" />
                  <span>{evolution.issuesDelta.warning > 0
                    ? t('projects.detail_evolution_warning_up', { count: evolution.issuesDelta.warning })
                    : t('projects.detail_evolution_warning_down', { count: Math.abs(evolution.issuesDelta.warning) })}</span>
                </div>
              )}
              {evolution.wordpress?.changed && evolution.wordpress.latestVersion && (
                <div className="flex items-center gap-2 rounded-md px-3 py-1.5 bg-[var(--bg-secondary)]">
                  <Package className="h-3.5 w-3.5 text-blue-600" />
                  <span>{t('projects.detail_evolution_wp_changed', {
                    from: evolution.wordpress.previousVersion ?? '—',
                    to: evolution.wordpress.latestVersion,
                  })}</span>
                </div>
              )}
            </CardContent>
          </Card>
        )}

        {/* History table */}
        <Card>
          <CardHeader>
            <CardTitle className="text-base">{t('projects.detail_history_title')}</CardTitle>
          </CardHeader>
          <CardContent className="pt-0">
            {audits.length === 0 ? (
              <p className="py-6 text-center text-sm text-[var(--text-tertiary)]">{t('projects.shared_view_empty')}</p>
            ) : (
              <div className="overflow-hidden rounded-lg border border-[var(--border-default)]">
                <table className="w-full text-sm">
                  <thead className="bg-[var(--bg-secondary)] text-left text-[10px] uppercase tracking-wider text-[var(--text-tertiary)]">
                    <tr>
                      <th className="px-3 py-2 text-right">{t('account.history_col_score')}</th>
                      <th className="px-3 py-2">{t('account.history_col_date')}</th>
                      <th className="px-3 py-2 w-24"></th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-[var(--border-default)]">
                    {audits.map((a) => (
                      <tr key={a.id} className="hover:bg-[var(--bg-secondary)]">
                        <td className="px-3 py-2 text-right">
                          <ScoreChip score={a.globalScore} level={a.globalLevel} />
                        </td>
                        <td className="px-3 py-2 text-xs text-[var(--text-secondary)]">
                          <Clock className="h-3 w-3 inline mr-1" />
                          {new Date(a.createdAt).toLocaleString(i18n.language || 'en', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })}
                        </td>
                        <td className="px-3 py-2 text-right">
                          <Link to={`/results/${a.id}`} className="text-xs text-[var(--accent-primary)] hover:underline">
                            {t('account.history_view')}
                          </Link>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </CardContent>
        </Card>

        <footer className="py-4 text-center">
          <Badge variant="secondary" className="text-[10px]">
            {t('projects.shared_view_owner_note')}
          </Badge>
        </footer>
      </main>
    </div>
  )
}

function ScoreChip({ score, level }: { score: number; level: string }) {
  const tone = level === 'critical' ? 'bg-red-100 text-red-700'
    : level === 'warning' ? 'bg-amber-100 text-amber-700'
    : level === 'excellent' ? 'bg-emerald-100 text-emerald-800'
    : level === 'good' ? 'bg-emerald-50 text-emerald-700'
    : 'bg-gray-100 text-gray-700'
  return <span className={`inline-block rounded-md px-2 py-0.5 text-xs font-semibold tabular-nums ${tone}`}>{score}</span>
}

function SharedSparkline({ points, labels }: { points: number[]; labels: string[] }) {
  if (points.length === 0) return null
  const W = 600
  const H = 140
  const pad = 24
  const stepX = points.length > 1 ? (W - pad * 2) / (points.length - 1) : 0
  const scaleY = (v: number) => pad + (H - pad * 2) * (1 - Math.min(100, Math.max(0, v)) / 100)
  const d = points.map((p, i) => `${i === 0 ? 'M' : 'L'} ${pad + i * stepX} ${scaleY(p)}`).join(' ')
  const areaD = `${d} L ${pad + (points.length - 1) * stepX} ${H - pad} L ${pad} ${H - pad} Z`

  return (
    <div className="relative">
      <svg viewBox={`0 0 ${W} ${H}`} className="w-full h-40">
        {[0, 50, 100].map((v) => (
          <line key={v} x1={pad} x2={W - pad} y1={scaleY(v)} y2={scaleY(v)}
            stroke="var(--border-default)" strokeDasharray="3 3" />
        ))}
        <path d={areaD} fill="var(--accent-primary)" opacity="0.08" />
        <path d={d} fill="none" stroke="var(--accent-primary)" strokeWidth="2" />
        {points.map((p, i) => (
          <circle key={i} cx={pad + i * stepX} cy={scaleY(p)} r="3" fill="white" stroke="var(--accent-primary)" strokeWidth="2" />
        ))}
        {[0, 50, 100].map((v) => (
          <text key={v} x={pad - 6} y={scaleY(v) + 3} textAnchor="end" fontSize="9" fill="var(--text-tertiary)">{v}</text>
        ))}
      </svg>
      <div className="flex justify-between text-[10px] text-[var(--text-tertiary)] mt-1 px-4">
        {labels.map((l, i) => (
          <span key={i} className={points.length > 8 && i % 2 === 1 ? 'invisible' : ''}>{l}</span>
        ))}
      </div>
    </div>
  )
}
