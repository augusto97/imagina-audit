import { useEffect, useState, useCallback, useMemo } from 'react'
import { useNavigate, useParams, Link } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { Loader2, ArrowLeft, Play, Globe, Clock, Share2, TrendingUp, TrendingDown, Minus, CheckCircle2, Circle, CircleDashed, AlertTriangle, AlertCircle, Package } from 'lucide-react'
import { toast } from 'sonner'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { useUser, type ProjectDetail, type ProjectChecklistItem } from '@/hooks/useUser'
import { useAudit } from '@/hooks/useAudit'

export default function UserProjectDetailPage() {
  const { t, i18n } = useTranslation()
  const navigate = useNavigate()
  const { id } = useParams<{ id: string }>()
  const { isLoading, isAuthenticated, fetchProject, fetchProjectChecklist, updateChecklistItem, enableProjectShare, disableProjectShare } = useUser()
  const { startAudit: runAudit, status: auditStatus } = useAudit()

  const [detail, setDetail] = useState<ProjectDetail | null>(null)
  const [checklist, setChecklist] = useState<ProjectChecklistItem[]>([])
  const [loadingDetail, setLoadingDetail] = useState(true)
  const [shareBusy, setShareBusy] = useState(false)

  useEffect(() => {
    if (!isLoading && !isAuthenticated) navigate('/login', { replace: true })
  }, [isLoading, isAuthenticated, navigate])

  const load = useCallback(async () => {
    if (!id) return
    setLoadingDetail(true)
    const [data, items] = await Promise.all([
      fetchProject(Number(id)),
      fetchProjectChecklist(Number(id)),
    ])
    setDetail(data)
    setChecklist(items ?? [])
    setLoadingDetail(false)
  }, [id, fetchProject, fetchProjectChecklist])

  useEffect(() => {
    if (isAuthenticated && id) load()
  }, [isAuthenticated, id, load])

  const handleShareEnable = useCallback(async (rotate = false) => {
    if (!id) return
    if (rotate && !confirm(t('projects.share_confirm_rotate'))) return
    setShareBusy(true)
    try {
      const res = await enableProjectShare(Number(id), rotate)
      setDetail((prev) => prev ? { ...prev, project: { ...prev.project, sharing: { enabled: res.enabled, token: res.token } } } : prev)
      toast.success(t('projects.share_toggled_on'))
    } catch (err: unknown) {
      const axiosErr = err as { response?: { data?: { error?: string } } }
      toast.error(axiosErr.response?.data?.error ?? 'Error')
    } finally {
      setShareBusy(false)
    }
  }, [id, enableProjectShare, t])

  const handleShareDisable = useCallback(async () => {
    if (!id) return
    if (!confirm(t('projects.share_confirm_disable'))) return
    setShareBusy(true)
    try {
      await disableProjectShare(Number(id))
      setDetail((prev) => prev ? { ...prev, project: { ...prev.project, sharing: { enabled: false, token: null } } } : prev)
      toast.success(t('projects.share_toggled_off'))
    } catch (err: unknown) {
      const axiosErr = err as { response?: { data?: { error?: string } } }
      toast.error(axiosErr.response?.data?.error ?? 'Error')
    } finally {
      setShareBusy(false)
    }
  }, [id, disableProjectShare, t])

  const copyShareUrl = useCallback(async (token: string) => {
    const url = `${window.location.origin}/shared/${token}`
    try {
      await navigator.clipboard.writeText(url)
      toast.success(t('projects.share_copied'))
    } catch {
      toast.error('—')
    }
  }, [t])

  const toggleItem = useCallback(async (item: ProjectChecklistItem, nextStatus: 'open' | 'done' | 'ignored') => {
    try {
      await updateChecklistItem(Number(id), item.metricId, { status: nextStatus })
      setChecklist((prev) => prev.map(i => i.metricId === item.metricId ? { ...i, status: nextStatus, userModified: true } : i))
    } catch (err: unknown) {
      const axiosErr = err as { response?: { data?: { error?: string } } }
      toast.error(axiosErr.response?.data?.error ?? 'Error')
    }
  }, [id, updateChecklistItem])

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
  const isScanning = auditStatus === 'scanning'

  // Dispara el scan con la URL del proyecto directamente — sin form, sin
  // lead fields. useAudit pone status='scanning', el ScanningAnimation se
  // muestra en / y al terminar navega a /account/audits/:id (la redirect
  // path vive en useAudit y detecta la sesión de user).
  const runProjectScan = () => {
    if (isScanning) return
    runAudit({ url: project.url })
    navigate('/')
  }

  return (
    <div className="min-h-screen bg-[#F4F6F8]">
      <header className="border-b border-[var(--border-default)] bg-white">
        <div className="mx-auto flex max-w-6xl items-center justify-between px-6 py-4">
          <Link to="/account/projects" className="inline-flex items-center gap-2 text-sm text-[var(--text-secondary)] hover:text-[var(--text-primary)]">
            <ArrowLeft className="h-4 w-4" />
            {t('projects.detail_back')}
          </Link>
          <Button size="sm" onClick={runProjectScan} disabled={isScanning}>
            {isScanning
              ? <Loader2 className="h-4 w-4 animate-spin" />
              : <Play className="h-4 w-4" />}
            {t('projects.detail_new_scan')}
          </Button>
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

        {/* Evolution — solo visible cuando hay 2+ audits */}
        <Card>
          <CardHeader>
            <CardTitle className="text-base">{t('projects.detail_evolution_title')}</CardTitle>
          </CardHeader>
          <CardContent className="pt-0">
            {evolution === null ? (
              <p className="text-sm text-[var(--text-tertiary)]">{t('projects.detail_evolution_empty')}</p>
            ) : (
              <div className="space-y-1.5 text-sm">
                {evolution.scoreDelta !== 0 && (
                  <EvolutionLine
                    icon={evolution.scoreDelta > 0 ? <TrendingUp className="h-3.5 w-3.5 text-emerald-700" /> : <TrendingDown className="h-3.5 w-3.5 text-red-600" />}
                    tone={evolution.scoreDelta > 0 ? 'good' : 'bad'}
                    text={evolution.scoreDelta > 0
                      ? t('projects.detail_evolution_score_up', { value: evolution.scoreDelta })
                      : t('projects.detail_evolution_score_down', { value: Math.abs(evolution.scoreDelta) })}
                  />
                )}
                {evolution.issuesDelta.critical !== 0 && (
                  <EvolutionLine
                    icon={<AlertCircle className="h-3.5 w-3.5 text-red-600" />}
                    tone={evolution.issuesDelta.critical > 0 ? 'bad' : 'good'}
                    text={evolution.issuesDelta.critical > 0
                      ? t('projects.detail_evolution_critical_up', { count: evolution.issuesDelta.critical })
                      : t('projects.detail_evolution_critical_down', { count: Math.abs(evolution.issuesDelta.critical) })}
                  />
                )}
                {evolution.issuesDelta.warning !== 0 && (
                  <EvolutionLine
                    icon={<AlertTriangle className="h-3.5 w-3.5 text-amber-600" />}
                    tone={evolution.issuesDelta.warning > 0 ? 'bad' : 'good'}
                    text={evolution.issuesDelta.warning > 0
                      ? t('projects.detail_evolution_warning_up', { count: evolution.issuesDelta.warning })
                      : t('projects.detail_evolution_warning_down', { count: Math.abs(evolution.issuesDelta.warning) })}
                  />
                )}
                {evolution.wordpress?.changed && evolution.wordpress.latestVersion && (
                  <EvolutionLine
                    icon={<Package className="h-3.5 w-3.5 text-blue-600" />}
                    tone="neutral"
                    text={t('projects.detail_evolution_wp_changed', {
                      from: evolution.wordpress.previousVersion ?? '—',
                      to: evolution.wordpress.latestVersion,
                    })}
                  />
                )}
                {evolution.plugins.added.length > 0 && (
                  <EvolutionLine
                    icon={<Package className="h-3.5 w-3.5 text-blue-600" />}
                    tone="neutral"
                    text={t('projects.detail_evolution_plugins_added', { count: evolution.plugins.added.length })}
                  />
                )}
                {evolution.plugins.removed.length > 0 && (
                  <EvolutionLine
                    icon={<Package className="h-3.5 w-3.5 text-[var(--text-tertiary)]" />}
                    tone="neutral"
                    text={t('projects.detail_evolution_plugins_removed', { count: evolution.plugins.removed.length })}
                  />
                )}
                {evolution.scoreDelta === 0 && evolution.issuesDelta.critical === 0 && evolution.issuesDelta.warning === 0 && !evolution.wordpress?.changed && evolution.plugins.added.length === 0 && evolution.plugins.removed.length === 0 && (
                  <EvolutionLine icon={<Minus className="h-3.5 w-3.5 text-[var(--text-tertiary)]" />} tone="neutral" text={t('projects.detail_score_change_flat')} />
                )}
              </div>
            )}
          </CardContent>
        </Card>

        {/* Share card */}
        <Card>
          <CardHeader className="pb-2">
            <CardTitle className="flex items-center gap-2 text-base">
              <Share2 className="h-4 w-4 text-[var(--accent-primary)]" />
              {t('projects.share_title')}
            </CardTitle>
          </CardHeader>
          <CardContent className="pt-0">
            {project.sharing.enabled && project.sharing.token ? (
              <div className="space-y-3">
                <p className="text-xs text-[var(--text-secondary)]">{t('projects.share_enabled_body')}</p>
                <div className="flex items-center gap-2">
                  <input
                    type="text"
                    readOnly
                    value={`${window.location.origin}/shared/${project.sharing.token}`}
                    className="flex-1 rounded-md border border-[var(--border-default)] bg-[var(--bg-secondary)] px-3 py-2 font-mono text-xs"
                    onFocus={(e) => e.currentTarget.select()}
                  />
                  <Button size="sm" variant="outline" onClick={() => copyShareUrl(project.sharing.token!)}>
                    {t('projects.share_copy')}
                  </Button>
                </div>
                <div className="flex items-center gap-3 pt-1">
                  <button
                    type="button"
                    disabled={shareBusy}
                    onClick={() => handleShareEnable(true)}
                    className="text-[11px] text-[var(--accent-primary)] hover:underline disabled:opacity-50"
                    title={t('projects.share_rotate_hint')}
                  >
                    {t('projects.share_rotate')}
                  </button>
                  <button
                    type="button"
                    disabled={shareBusy}
                    onClick={handleShareDisable}
                    className="text-[11px] text-red-600 hover:underline disabled:opacity-50"
                  >
                    {t('projects.share_disable')}
                  </button>
                </div>
              </div>
            ) : (
              <div className="space-y-3">
                <p className="text-xs text-[var(--text-secondary)]">{t('projects.share_off_body')}</p>
                <Button size="sm" disabled={shareBusy} onClick={() => handleShareEnable(false)}>
                  {shareBusy && <Loader2 className="h-3.5 w-3.5 animate-spin" />}
                  <Share2 className="h-3.5 w-3.5" />
                  {t('projects.share_enable')}
                </Button>
              </div>
            )}
          </CardContent>
        </Card>

        {/* Checklist vivo */}
        <Card>
          <CardHeader>
            <CardTitle className="text-base">{t('projects.detail_tasks_title')}</CardTitle>
          </CardHeader>
          <CardContent className="pt-0">
            <ChecklistBoard items={checklist} onToggle={toggleItem} />
          </CardContent>
        </Card>

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
                          <Link to={`/account/audits/${a.id}`} className="text-xs text-[var(--accent-primary)] hover:underline">
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

function EvolutionLine({ icon, tone, text }: { icon: React.ReactNode; tone: 'good' | 'bad' | 'neutral'; text: string }) {
  const bg = tone === 'good' ? 'bg-emerald-50' : tone === 'bad' ? 'bg-red-50' : 'bg-[var(--bg-secondary)]'
  return (
    <div className={`flex items-center gap-2 rounded-md px-3 py-1.5 ${bg}`}>
      {icon}
      <span className="text-[var(--text-primary)]">{text}</span>
    </div>
  )
}

function ChecklistBoard({ items, onToggle }: { items: ProjectChecklistItem[]; onToggle: (item: ProjectChecklistItem, next: 'open' | 'done' | 'ignored') => void }) {
  const { t } = useTranslation()

  if (items.length === 0) {
    return <p className="py-6 text-center text-sm text-[var(--text-tertiary)]">{t('projects.detail_tasks_empty')}</p>
  }

  // Agrupamos por estado para que el user vea claro qué queda abierto.
  const open = items.filter(i => i.status === 'open')
  const done = items.filter(i => i.status === 'done')
  const ignored = items.filter(i => i.status === 'ignored')

  return (
    <div className="space-y-4">
      {open.length > 0 && (
        <ChecklistGroup title={t('projects.detail_tasks_title')} items={open} onToggle={onToggle} />
      )}
      {(done.length > 0 || ignored.length > 0) && (
        <details className="group">
          <summary className="cursor-pointer text-xs text-[var(--text-tertiary)] select-none">
            {done.length} done · {ignored.length} ignored
          </summary>
          <div className="mt-2 space-y-2">
            {done.map(item => <ChecklistRow key={item.metricId} item={item} onToggle={onToggle} />)}
            {ignored.map(item => <ChecklistRow key={item.metricId} item={item} onToggle={onToggle} />)}
          </div>
        </details>
      )}
    </div>
  )
}

function ChecklistGroup({ title, items, onToggle }: { title: string; items: ProjectChecklistItem[]; onToggle: (item: ProjectChecklistItem, next: 'open' | 'done' | 'ignored') => void }) {
  return (
    <div className="space-y-2">
      <h4 className="text-[10px] font-semibold uppercase tracking-wider text-[var(--text-tertiary)]">{title}</h4>
      <div className="space-y-1.5">
        {items.map(item => <ChecklistRow key={item.metricId} item={item} onToggle={onToggle} />)}
      </div>
    </div>
  )
}

function ChecklistRow({ item, onToggle }: { item: ProjectChecklistItem; onToggle: (item: ProjectChecklistItem, next: 'open' | 'done' | 'ignored') => void }) {
  const severityTone =
    item.severity === 'critical' ? 'border-red-200 bg-red-50/40'
    : item.severity === 'warning' ? 'border-amber-200 bg-amber-50/30'
    : 'border-[var(--border-default)]'

  const icon = item.status === 'done'
    ? <CheckCircle2 className="h-4 w-4 text-emerald-600" />
    : item.status === 'ignored'
    ? <CircleDashed className="h-4 w-4 text-[var(--text-tertiary)]" />
    : <Circle className="h-4 w-4 text-[var(--text-tertiary)]" />

  const nextStatus: 'open' | 'done' = item.status === 'done' ? 'open' : 'done'

  return (
    <div className={`flex items-start gap-2 rounded-lg border px-3 py-2 ${severityTone}`}>
      <button
        type="button"
        onClick={() => onToggle(item, nextStatus)}
        className="mt-0.5 shrink-0 hover:scale-110 transition-transform"
        title={item.status === 'done' ? 'Mark open' : 'Mark done'}
      >
        {icon}
      </button>
      <div className="min-w-0 flex-1">
        <div className="flex items-center gap-2 flex-wrap">
          <span className={`text-sm font-medium ${item.status === 'done' ? 'line-through text-[var(--text-tertiary)]' : 'text-[var(--text-primary)]'}`}>
            {item.name}
          </span>
          {item.moduleName && (
            <span className="text-[10px] text-[var(--text-tertiary)] font-mono">[{item.moduleName}]</span>
          )}
          {item.severity === 'critical' && (
            <Badge variant="destructive" className="text-[9px] px-1 py-0">critical</Badge>
          )}
          {item.severity === 'warning' && (
            <Badge variant="warning" className="text-[9px] px-1 py-0">warning</Badge>
          )}
        </div>
        {item.recommendation && item.status !== 'done' && (
          <p className="mt-0.5 text-xs text-[var(--text-secondary)] line-clamp-2">{item.recommendation}</p>
        )}
      </div>
      {item.status === 'open' && (
        <button
          type="button"
          onClick={() => onToggle(item, 'ignored')}
          className="shrink-0 text-[10px] text-[var(--text-tertiary)] hover:text-[var(--text-secondary)] self-start"
        >
          ignore
        </button>
      )}
      {item.status === 'ignored' && (
        <button
          type="button"
          onClick={() => onToggle(item, 'open')}
          className="shrink-0 text-[10px] text-[var(--text-tertiary)] hover:text-[var(--text-secondary)] self-start"
        >
          reopen
        </button>
      )}
    </div>
  )
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
