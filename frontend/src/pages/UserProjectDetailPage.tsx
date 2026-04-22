import { useEffect, useState, useCallback, useMemo } from 'react'
import { useNavigate, useParams, Link } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { Loader2, ArrowLeft, Play, Globe, Clock, Share2, TrendingUp, TrendingDown, Minus } from 'lucide-react'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Skeleton } from '@/components/ui/skeleton'
import { useUser, type ProjectDetail } from '@/hooks/useUser'

export default function UserProjectDetailPage() {
  const { t, i18n } = useTranslation()
  const navigate = useNavigate()
  const { id } = useParams<{ id: string }>()
  const { isLoading, isAuthenticated, fetchProject } = useUser()

  const [detail, setDetail] = useState<ProjectDetail | null>(null)
  const [loadingDetail, setLoadingDetail] = useState(true)

  useEffect(() => {
    if (!isLoading && !isAuthenticated) navigate('/login', { replace: true })
  }, [isLoading, isAuthenticated, navigate])

  const load = useCallback(async () => {
    if (!id) return
    setLoadingDetail(true)
    const data = await fetchProject(Number(id))
    setDetail(data)
    setLoadingDetail(false)
  }, [id, fetchProject])

  useEffect(() => {
    if (isAuthenticated && id) load()
  }, [isAuthenticated, id, load])

  // Timeline points: ordenados cronológicamente (el endpoint los manda DESC)
  const timeline = useMemo(() => {
    if (!detail) return []
    return [...detail.audits].reverse().map((a) => ({
      date: new Date(a.createdAt),
      score: a.globalScore,
      level: a.globalLevel,
      id: a.id,
    }))
  }, [detail])

  if (isLoading || loadingDetail || !isAuthenticated) {
    return (
      <div className="flex min-h-screen items-center justify-center bg-[var(--bg-secondary)]">
        <Loader2 className="h-6 w-6 animate-spin text-[var(--accent-primary)]" />
      </div>
    )
  }

  if (!detail) {
    return (
      <div className="flex min-h-screen items-center justify-center bg-[#F4F6F8]">
        <div className="text-center">
          <p className="text-sm text-[var(--text-tertiary)]">{t('projects.detail_history_empty')}</p>
          <Link to="/account/projects"><Button variant="ghost" size="sm" className="mt-2"><ArrowLeft className="h-4 w-4" /> {t('projects.detail_back')}</Button></Link>
        </div>
      </div>
    )
  }

  const { project, audits, checklistSummary, evolution } = detail
  const latest = audits[0] ?? null
  const currentScore = latest?.globalScore ?? null
  const scanHref = `/?url=${encodeURIComponent(project.url)}`

  return (
    <div className="min-h-screen bg-[#F4F6F8]">
      <header className="border-b border-[var(--border-default)] bg-white">
        <div className="mx-auto flex max-w-6xl items-center justify-between px-6 py-4">
          <Link to="/account/projects" className="inline-flex items-center gap-2 text-sm text-[var(--text-secondary)] hover:text-[var(--text-primary)]">
            <ArrowLeft className="h-4 w-4" />
            {t('projects.detail_back')}
          </Link>
          <a href={scanHref}>
            <Button size="sm">
              <Play className="h-4 w-4" />
              {t('projects.detail_new_scan')}
            </Button>
          </a>
        </div>
      </header>

      <main className="mx-auto max-w-6xl space-y-6 px-6 py-8">
        {/* Title block */}
        <div>
          <div className="flex items-center gap-2">
            {project.color && <span className="inline-block h-3 w-3 rounded-full" style={{ background: project.color }} />}
            <h1 className="text-2xl font-bold text-[var(--text-primary)]">{project.name}</h1>
            {project.sharing.enabled && (
              <Badge variant="success" className="text-[10px]"><Share2 className="h-3 w-3" />{t('projects.card_sharing_on')}</Badge>
            )}
          </div>
          <div className="mt-1 flex items-center gap-2 text-xs text-[var(--text-tertiary)]">
            <Globe className="h-3 w-3" />
            <a href={project.url} target="_blank" rel="noreferrer" className="font-mono hover:underline">{project.url}</a>
          </div>
          {project.notes && <p className="mt-2 max-w-2xl text-sm text-[var(--text-secondary)]">{project.notes}</p>}
        </div>

        {/* Score + Evolution row */}
        <div className="grid gap-4 md:grid-cols-3">
          {/* Score card */}
          <Card className="md:col-span-1">
            <CardHeader className="pb-2">
              <CardTitle className="text-sm font-semibold text-[var(--text-tertiary)]">
                {t('projects.detail_current_score')}
              </CardTitle>
            </CardHeader>
            <CardContent className="pt-0">
              {currentScore !== null ? (
                <>
                  <div className="flex items-baseline gap-2">
                    <span className="text-4xl font-bold tabular-nums text-[var(--text-primary)]">{currentScore}</span>
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
                <>
                  <span className="text-sm text-[var(--text-tertiary)]">{t('projects.detail_score_never')}</span>
                </>
              )}
            </CardContent>
          </Card>

          {/* Timeline (simple inline chart) */}
          <Card className="md:col-span-2">
            <CardHeader className="pb-2">
              <CardTitle className="text-sm font-semibold text-[var(--text-tertiary)]">{t('projects.detail_timeline_title')}</CardTitle>
            </CardHeader>
            <CardContent className="pt-0">
              {timeline.length === 0 ? (
                <p className="text-sm text-[var(--text-tertiary)] py-8 text-center">{t('projects.detail_timeline_empty')}</p>
              ) : (
                <ScoreSparkline points={timeline.map(p => p.score)} labels={timeline.map(p => p.date.toLocaleDateString(i18n.language || 'en', { day: 'numeric', month: 'short' }))} />
              )}
            </CardContent>
          </Card>
        </div>

        {/* History table */}
        <Card>
          <CardHeader>
            <CardTitle className="text-base">{t('projects.detail_history_title')}</CardTitle>
          </CardHeader>
          <CardContent className="pt-0">
            {audits.length === 0 ? (
              <p className="py-6 text-center text-sm text-[var(--text-tertiary)]">{t('projects.detail_history_empty')}</p>
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
                        <td className="px-3 py-2 text-right"><ScoreChip score={a.globalScore} level={a.globalLevel} /></td>
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
            <p className="mt-2 text-[11px] text-[var(--text-tertiary)] text-right">
              {t('projects.card_audits', { count: audits.length })}
              {checklistSummary.open > 0 && (
                <span className="ml-3 text-amber-700">{t('projects.card_open_tasks', { count: checklistSummary.open })}</span>
              )}
            </p>
          </CardContent>
        </Card>
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

/**
 * Sparkline SVG mínimo — evita agregar Recharts como dependencia para
 * una sola curva. El eje X es uniforme, el Y va de 0 a 100 (score range).
 */
function ScoreSparkline({ points, labels }: { points: number[]; labels: string[] }) {
  if (points.length === 0) return null
  const W = 600
  const H = 140
  const pad = 24
  const stepX = points.length > 1 ? (W - pad * 2) / (points.length - 1) : 0
  const scaleY = (v: number) => pad + (H - pad * 2) * (1 - Math.min(100, Math.max(0, v)) / 100)

  const d = points.map((p, i) => `${i === 0 ? 'M' : 'L'} ${pad + i * stepX} ${scaleY(p)}`).join(' ')

  // Area path (fill underneath)
  const areaD = `${d} L ${pad + (points.length - 1) * stepX} ${H - pad} L ${pad} ${H - pad} Z`

  return (
    <div className="relative">
      <svg viewBox={`0 0 ${W} ${H}`} className="w-full h-40">
        {/* Gridlines */}
        {[0, 50, 100].map((v) => (
          <line key={v} x1={pad} x2={W - pad} y1={scaleY(v)} y2={scaleY(v)}
            stroke="var(--border-default)" strokeDasharray="3 3" />
        ))}
        {/* Filled area */}
        <path d={areaD} fill="var(--accent-primary)" opacity="0.08" />
        {/* Line */}
        <path d={d} fill="none" stroke="var(--accent-primary)" strokeWidth="2" />
        {/* Points */}
        {points.map((p, i) => (
          <circle
            key={i}
            cx={pad + i * stepX}
            cy={scaleY(p)}
            r="3"
            fill="white"
            stroke="var(--accent-primary)"
            strokeWidth="2"
          />
        ))}
        {/* Y axis labels */}
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
