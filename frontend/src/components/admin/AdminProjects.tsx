import { useEffect, useState, useCallback } from 'react'
import { useTranslation } from 'react-i18next'
import { Folder, Trash2, Search, Share2, Globe, ExternalLink } from 'lucide-react'
import { toast } from 'sonner'
import { Card, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Badge } from '@/components/ui/badge'
import { Skeleton } from '@/components/ui/skeleton'
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog'
import { useAdmin } from '@/hooks/useAdmin'

interface AdminProjectRow {
  id: number
  userId: number
  userEmail: string | null
  userName: string | null
  name: string
  url: string
  domain: string
  icon: string | null
  color: string | null
  sharingEnabled: boolean
  auditCount: number
  createdAt: string
  latestAudit: { globalScore: number; globalLevel: string; createdAt: string } | null
}

export default function AdminProjects() {
  const { t, i18n } = useTranslation()
  const { fetchAdminProjects, deleteAdminProject } = useAdmin()
  const [rows, setRows] = useState<AdminProjectRow[]>([])
  const [loading, setLoading] = useState(true)
  const [search, setSearch] = useState('')
  const [confirmDelete, setConfirmDelete] = useState<AdminProjectRow | null>(null)

  const reload = useCallback(async () => {
    setLoading(true)
    const params: Record<string, string | number> = {}
    if (search) params.search = search
    const data = await fetchAdminProjects(params)
    setRows((data?.projects ?? []) as AdminProjectRow[])
    setLoading(false)
  }, [fetchAdminProjects, search])

  useEffect(() => { reload() }, [reload])

  const onDelete = async (p: AdminProjectRow) => {
    try {
      await deleteAdminProject(p.id)
      toast.success(t('admin_projects_page.toast_deleted'))
      setConfirmDelete(null)
      await reload()
    } catch (err: unknown) {
      const axiosErr = err as { response?: { data?: { error?: string } } }
      toast.error(axiosErr.response?.data?.error ?? 'Error')
      setConfirmDelete(null)
    }
  }

  return (
    <div className="space-y-5">
      <div>
        <h1 className="text-2xl font-bold text-[var(--text-primary)] flex items-center gap-2">
          <Folder className="h-6 w-6 text-[var(--accent-primary)]" />
          {t('admin_projects_page.title')}
        </h1>
        <p className="mt-1 text-sm text-[var(--text-secondary)]">{t('admin_projects_page.subtitle')}</p>
      </div>

      <Card>
        <CardContent className="pt-5 space-y-4">
          <div className="relative max-w-sm">
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-[var(--text-tertiary)]" />
            <Input
              className="pl-9 h-9 text-xs"
              placeholder={t('admin_projects_page.search_placeholder')}
              value={search}
              onChange={(e) => setSearch(e.target.value)}
            />
          </div>

          {loading ? (
            <Skeleton className="h-64" />
          ) : rows.length === 0 ? (
            <p className="py-8 text-center text-sm text-[var(--text-tertiary)]">{t('admin_projects_page.empty')}</p>
          ) : (
            <div className="overflow-hidden rounded-lg border border-[var(--border-default)]">
              <table className="w-full text-sm">
                <thead className="bg-[var(--bg-secondary)] text-left text-[10px] uppercase tracking-wider text-[var(--text-tertiary)]">
                  <tr>
                    <th className="px-3 py-2">{t('admin_projects_page.col_user')}</th>
                    <th className="px-3 py-2">{t('admin_projects_page.col_project')}</th>
                    <th className="px-3 py-2 text-right">{t('admin_projects_page.col_audits')}</th>
                    <th className="px-3 py-2 text-right">{t('admin_projects_page.col_latest_score')}</th>
                    <th className="px-3 py-2">{t('admin_projects_page.col_last_scan')}</th>
                    <th className="px-3 py-2 w-16"></th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-[var(--border-default)]">
                  {rows.map((p) => (
                    <tr key={p.id} className="hover:bg-[var(--bg-secondary)]">
                      <td className="px-3 py-2">
                        <div className="text-xs font-medium text-[var(--text-primary)]">{p.userEmail ?? '—'}</div>
                        {p.userName && <div className="text-[10px] text-[var(--text-tertiary)]">{p.userName}</div>}
                      </td>
                      <td className="px-3 py-2">
                        <div className="flex items-center gap-2">
                          {p.color && <span className="inline-block h-2 w-2 rounded-full" style={{ background: p.color }} />}
                          <span className="font-medium text-[var(--text-primary)]">{p.name}</span>
                          {p.sharingEnabled && (
                            <Badge variant="success" className="text-[9px] px-1 py-0"><Share2 className="h-2.5 w-2.5" /> {t('admin_projects_page.sharing_on')}</Badge>
                          )}
                        </div>
                        <div className="mt-0.5 flex items-center gap-1 text-[10px] text-[var(--text-tertiary)]">
                          <Globe className="h-2.5 w-2.5" />
                          <a href={p.url} target="_blank" rel="noreferrer" className="font-mono hover:underline inline-flex items-center gap-0.5 truncate max-w-[280px]">
                            {p.url} <ExternalLink className="h-2.5 w-2.5" />
                          </a>
                        </div>
                      </td>
                      <td className="px-3 py-2 text-right tabular-nums text-xs">{p.auditCount}</td>
                      <td className="px-3 py-2 text-right">
                        {p.latestAudit ? <ScoreChip score={p.latestAudit.globalScore} level={p.latestAudit.globalLevel} /> : <span className="text-[var(--text-tertiary)]">—</span>}
                      </td>
                      <td className="px-3 py-2 text-[11px] text-[var(--text-tertiary)]">
                        {p.latestAudit
                          ? new Date(p.latestAudit.createdAt).toLocaleDateString(i18n.language || 'en', { day: 'numeric', month: 'short', year: 'numeric' })
                          : '—'}
                      </td>
                      <td className="px-3 py-2 text-right">
                        <Button size="sm" variant="ghost" className="h-7 px-2 text-red-600 hover:text-red-700" onClick={() => setConfirmDelete(p)}>
                          <Trash2 className="h-3.5 w-3.5" />
                        </Button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </CardContent>
      </Card>

      <Dialog open={confirmDelete !== null} onOpenChange={(open) => !open && setConfirmDelete(null)}>
        <DialogContent className="max-w-md">
          <DialogHeader>
            <DialogTitle>{t('admin_projects_page.confirm_delete_title')}</DialogTitle>
          </DialogHeader>
          <p className="text-sm text-[var(--text-secondary)]">{t('admin_projects_page.confirm_delete_body')}</p>
          <DialogFooter>
            <Button variant="ghost" onClick={() => setConfirmDelete(null)}>{t('admin_users.cancel')}</Button>
            <Button variant="destructive" onClick={() => confirmDelete && onDelete(confirmDelete)}>
              {t('admin_projects_page.confirm_delete_confirm')}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
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

