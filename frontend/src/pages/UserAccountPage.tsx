import { useEffect, useState, useCallback } from 'react'
import { useNavigate, Link } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { Loader2, LogOut, Plus, Gauge, ShieldCheck, ArrowRight, Globe, Folder } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { useUser } from '@/hooks/useUser'
import { useConfigStore } from '@/store/configStore'

interface UserAudit {
  id: string
  url: string
  domain: string
  globalScore: number
  globalLevel: string
  isWordPress: boolean
  createdAt: string
}

export default function UserAccountPage() {
  const { t, i18n } = useTranslation()
  const navigate = useNavigate()
  const { isLoading, isAuthenticated, user, quota, logout, fetchAudits } = useUser()
  const { logoUrl, companyName } = useConfigStore((s) => s.config)

  const [audits, setAudits] = useState<UserAudit[]>([])
  const [page, setPage] = useState(1)
  const [total, setTotal] = useState(0)
  const [totalPages, setTotalPages] = useState(1)
  const [loadingHistory, setLoadingHistory] = useState(true)

  useEffect(() => {
    if (!isLoading && !isAuthenticated) navigate('/login', { replace: true })
  }, [isLoading, isAuthenticated, navigate])

  const loadPage = useCallback(async (p: number) => {
    setLoadingHistory(true)
    const res = await fetchAudits(p, 10)
    if (res) {
      setAudits((prev) => (p === 1 ? res.audits : [...prev, ...res.audits]))
      setTotal(res.total)
      setTotalPages(res.totalPages)
      setPage(res.page)
    }
    setLoadingHistory(false)
  }, [fetchAudits])

  useEffect(() => {
    if (isAuthenticated) loadPage(1)
  }, [isAuthenticated, loadPage])

  if (isLoading || !isAuthenticated || !user) {
    return (
      <div className="flex min-h-screen items-center justify-center bg-[var(--bg-secondary)]">
        <Loader2 className="h-6 w-6 animate-spin text-[var(--accent-primary)]" />
      </div>
    )
  }

  const quotaPct = quota && !quota.unlimited && quota.limit > 0
    ? Math.min(100, (quota.used / quota.limit) * 100)
    : 0
  const quotaTone = quota && !quota.unlimited
    ? (quota.remaining === 0 ? 'bg-red-500' : quotaPct >= 80 ? 'bg-amber-500' : 'bg-emerald-500')
    : 'bg-emerald-500'

  // Fecha de reset: primer día del próximo mes en el idioma del user.
  const nextReset = new Date()
  nextReset.setMonth(nextReset.getMonth() + 1)
  nextReset.setDate(1)
  const resetDate = nextReset.toLocaleDateString(i18n.language || 'en', { day: 'numeric', month: 'long' })

  return (
    <div className="min-h-screen bg-[#F4F6F8]">
      {/* Header */}
      <header className="border-b border-[var(--border-default)] bg-white">
        <div className="mx-auto flex max-w-6xl items-center justify-between px-6 py-4">
          <Link to="/" className="flex items-center gap-2">
            {logoUrl && <img src={logoUrl} alt={companyName} className="h-8 w-auto" />}
            <span className="text-sm font-semibold text-[var(--text-primary)]">{t('account.title')}</span>
          </Link>
          <Button variant="ghost" size="sm" onClick={logout}>
            <LogOut className="h-4 w-4" />
            {t('account.logout')}
          </Button>
        </div>
      </header>

      <main className="mx-auto max-w-6xl space-y-6 px-6 py-8">
        {/* Greeting */}
        <div className="flex items-start justify-between gap-4 flex-wrap">
          <div>
            <h1 className="text-2xl font-bold text-[var(--text-primary)]">
              {user.name
                ? t('account.welcome', { name: user.name })
                : t('account.welcome_no_name')}
            </h1>
            <p className="mt-1 text-sm text-[var(--text-secondary)]">{user.email}</p>
          </div>
          <div className="flex items-center gap-2">
            <Link to="/account/projects">
              <Button variant="outline" size="sm">
                <Folder className="h-4 w-4" />
                {t('projects.title')}
              </Button>
            </Link>
            <Link to="/">
              <Button size="sm">
                <Plus className="h-4 w-4" />
                {t('account.new_audit')}
              </Button>
            </Link>
          </div>
        </div>

        {/* Banner cuota crítica */}
        {quota && !quota.unlimited && quota.remaining === 0 && (
          <div className="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-900">
            {t('account.quota_exceeded_banner')}
          </div>
        )}
        {quota && !quota.unlimited && quota.remaining !== null && quota.remaining > 0 && quota.remaining <= Math.max(1, Math.floor(quota.limit * 0.2)) && (
          <div className="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
            {t('account.quota_low_banner', { used: quota.used, limit: quota.limit, remaining: quota.remaining })}
          </div>
        )}

        {/* Plan + Quota cards */}
        <div className="grid gap-4 md:grid-cols-2">
          {/* Plan */}
          <Card>
            <CardHeader className="pb-2">
              <CardTitle className="flex items-center gap-2 text-sm font-semibold text-[var(--text-tertiary)]">
                <ShieldCheck className="h-4 w-4 text-[var(--accent-primary)]" />
                {t('account.plan_card_title')}
              </CardTitle>
            </CardHeader>
            <CardContent className="pt-0">
              {user.plan ? (
                <>
                  <div className="flex items-baseline gap-2">
                    <span className="text-xl font-bold text-[var(--text-primary)]">{user.plan.name}</span>
                    {user.plan.monthlyLimit === 0 && (
                      <Badge variant="success" className="text-[10px]">{t('account.plan_unlimited')}</Badge>
                    )}
                  </div>
                  {user.plan.monthlyLimit > 0 && (
                    <p className="mt-1 text-xs text-[var(--text-secondary)]">
                      {user.plan.monthlyLimit} · {t('account.plan_limit_suffix')}
                    </p>
                  )}
                  {user.plan.description && (
                    <p className="mt-2 text-xs text-[var(--text-tertiary)]">{user.plan.description}</p>
                  )}
                </>
              ) : (
                <>
                  <p className="text-sm font-semibold text-amber-700">{t('account.plan_no_plan')}</p>
                  <p className="mt-1 text-xs text-[var(--text-tertiary)]">{t('account.plan_no_plan_hint')}</p>
                </>
              )}
            </CardContent>
          </Card>

          {/* Quota */}
          <Card>
            <CardHeader className="pb-2">
              <CardTitle className="flex items-center gap-2 text-sm font-semibold text-[var(--text-tertiary)]">
                <Gauge className="h-4 w-4 text-[var(--accent-primary)]" />
                {t('account.quota_card_title')}
              </CardTitle>
            </CardHeader>
            <CardContent className="pt-0 space-y-2">
              {quota ? (
                <>
                  <div className="flex items-baseline gap-2">
                    {quota.unlimited ? (
                      <span className="text-xl font-bold text-[var(--text-primary)]">
                        {t('account.quota_unlimited_used', { used: quota.used })}
                      </span>
                    ) : (
                      <>
                        <span className="text-xl font-bold tabular-nums text-[var(--text-primary)]">
                          {t('account.quota_used_of', { used: quota.used, limit: quota.limit })}
                        </span>
                        {quota.remaining !== null && (
                          <span className="text-xs text-[var(--text-tertiary)]">
                            {t('account.quota_remaining', { count: quota.remaining })}
                          </span>
                        )}
                      </>
                    )}
                  </div>
                  {!quota.unlimited && (
                    <div className="h-2 w-full overflow-hidden rounded-full bg-[var(--bg-secondary)]">
                      <div
                        className={`h-full rounded-full transition-all ${quotaTone}`}
                        style={{ width: `${quotaPct}%` }}
                      />
                    </div>
                  )}
                  <p className="text-[11px] text-[var(--text-tertiary)]">
                    {t('account.quota_resets_on', { date: resetDate })}
                  </p>
                </>
              ) : (
                <p className="text-sm text-[var(--text-tertiary)]">—</p>
              )}
            </CardContent>
          </Card>
        </div>

        {/* Historial */}
        <Card>
          <CardHeader>
            <CardTitle className="text-base">{t('account.history_title')}</CardTitle>
          </CardHeader>
          <CardContent className="pt-0">
            {audits.length === 0 && !loadingHistory ? (
              <div className="py-8 text-center">
                <p className="mb-4 text-sm text-[var(--text-tertiary)]">{t('account.history_empty')}</p>
                <Link to="/">
                  <Button size="sm">{t('account.history_run_first')}</Button>
                </Link>
              </div>
            ) : (
              <>
                <div className="overflow-hidden rounded-lg border border-[var(--border-default)]">
                  <table className="w-full text-sm">
                    <thead className="bg-[var(--bg-secondary)] text-left text-[10px] uppercase tracking-wider text-[var(--text-tertiary)]">
                      <tr>
                        <th className="px-3 py-2">{t('account.history_col_domain')}</th>
                        <th className="px-3 py-2 text-right">{t('account.history_col_score')}</th>
                        <th className="px-3 py-2">{t('account.history_col_date')}</th>
                        <th className="px-3 py-2 w-20"></th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-[var(--border-default)]">
                      {audits.map((a) => (
                        <tr key={a.id} className="hover:bg-[var(--bg-secondary)]">
                          <td className="px-3 py-2">
                            <div className="flex items-center gap-2">
                              <Globe className="h-3.5 w-3.5 text-[var(--text-tertiary)]" />
                              <span className="font-medium text-[var(--text-primary)]">{a.domain}</span>
                            </div>
                          </td>
                          <td className="px-3 py-2 text-right">
                            <ScoreChip score={a.globalScore} level={a.globalLevel} />
                          </td>
                          <td className="px-3 py-2 text-xs text-[var(--text-secondary)]">
                            {new Date(a.createdAt).toLocaleDateString(i18n.language || 'en', {
                              day: 'numeric', month: 'short', year: 'numeric',
                            })}
                          </td>
                          <td className="px-3 py-2 text-right">
                            <Link to={`/results/${a.id}`} className="text-xs text-[var(--accent-primary)] hover:underline inline-flex items-center gap-0.5">
                              {t('account.history_view')} <ArrowRight className="h-3 w-3" />
                            </Link>
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
                {page < totalPages && (
                  <div className="mt-3 flex items-center justify-between text-xs text-[var(--text-tertiary)]">
                    <span>{t('account.history_showing', { shown: audits.length, total })}</span>
                    <Button size="sm" variant="outline" onClick={() => loadPage(page + 1)} disabled={loadingHistory}>
                      {loadingHistory ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : null}
                      {t('account.history_load_more')}
                    </Button>
                  </div>
                )}
              </>
            )}
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
  return (
    <span className={`inline-block rounded-md px-2 py-0.5 text-xs font-semibold tabular-nums ${tone}`}>
      {score}
    </span>
  )
}
