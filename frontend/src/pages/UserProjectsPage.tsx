import { useEffect, useState, useCallback } from 'react'
import { useForm } from 'react-hook-form'
import { useNavigate, Link } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { motion } from 'framer-motion'
import { Loader2, LogOut, Plus, Folder, Share2, ListTodo, Globe, ArrowRight, Pencil, Trash2 } from 'lucide-react'
import { toast } from 'sonner'
import { Card, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { Label } from '@/components/ui/label'
import { Badge } from '@/components/ui/badge'
import { Skeleton } from '@/components/ui/skeleton'
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog'
import { useUser, type ProjectSummary } from '@/hooks/useUser'
import { useConfigStore } from '@/store/configStore'

interface FormValues {
  id?: number
  name: string
  url: string
  notes: string
}

export default function UserProjectsPage() {
  const { t, i18n } = useTranslation()
  const navigate = useNavigate()
  const { isLoading, isAuthenticated, user, logout, fetchProjects, createProject, updateProject, deleteProject } = useUser()
  const { logoUrl, companyName } = useConfigStore((s) => s.config)

  const [loading, setLoading] = useState(true)
  const [projects, setProjects] = useState<ProjectSummary[]>([])
  const [quota, setQuota] = useState<{ used: number; maxProjects: number; unlimited: boolean; remaining: number | null } | null>(null)
  const [creating, setCreating] = useState(false)
  const [editing, setEditing] = useState<ProjectSummary | null>(null)
  const [confirmDelete, setConfirmDelete] = useState<ProjectSummary | null>(null)

  useEffect(() => {
    if (!isLoading && !isAuthenticated) navigate('/login', { replace: true })
  }, [isLoading, isAuthenticated, navigate])

  const reload = useCallback(async () => {
    setLoading(true)
    const data = await fetchProjects()
    if (data) {
      setProjects(data.projects)
      setQuota(data.quota)
    }
    setLoading(false)
  }, [fetchProjects])

  useEffect(() => {
    if (isAuthenticated) reload()
  }, [isAuthenticated, reload])

  const onSaved = async () => {
    setCreating(false)
    setEditing(null)
    await reload()
  }

  const onDelete = async (p: ProjectSummary) => {
    try {
      await deleteProject(p.id)
      toast.success(t('projects.toast_deleted'))
      setConfirmDelete(null)
      await reload()
    } catch (err: unknown) {
      const axiosErr = err as { response?: { data?: { error?: string } } }
      toast.error(axiosErr.response?.data?.error ?? 'Error')
      setConfirmDelete(null)
    }
  }

  if (isLoading || !isAuthenticated || !user) {
    return (
      <div className="flex min-h-screen items-center justify-center bg-[var(--bg-secondary)]">
        <Loader2 className="h-6 w-6 animate-spin text-[var(--accent-primary)]" />
      </div>
    )
  }

  const atQuotaLimit = quota !== null && !quota.unlimited && (quota.remaining ?? 0) <= 0

  return (
    <div className="min-h-screen bg-[#F4F6F8]">
      <header className="border-b border-[var(--border-default)] bg-white">
        <div className="mx-auto flex max-w-6xl items-center justify-between px-6 py-4">
          <Link to="/account" className="flex items-center gap-2">
            {logoUrl && <img src={logoUrl} alt={companyName} className="h-8 w-auto" />}
            <span className="text-sm font-semibold text-[var(--text-primary)]">{t('projects.title')}</span>
          </Link>
          <Button variant="ghost" size="sm" onClick={logout}>
            <LogOut className="h-4 w-4" />
            {t('account.logout')}
          </Button>
        </div>
      </header>

      <main className="mx-auto max-w-6xl space-y-6 px-6 py-8">
        <div className="flex items-start justify-between gap-4 flex-wrap">
          <div>
            <h1 className="text-2xl font-bold text-[var(--text-primary)] flex items-center gap-2">
              <Folder className="h-6 w-6 text-[var(--accent-primary)]" />
              {t('projects.title')}
            </h1>
            <p className="mt-1 text-sm text-[var(--text-secondary)]">{t('projects.subtitle')}</p>
            {quota && (
              <Badge variant="secondary" className="mt-2 text-[10px]">
                {quota.unlimited
                  ? t('projects.quota_unlimited', { used: quota.used })
                  : t('projects.quota_label', { used: quota.used, limit: quota.maxProjects })}
              </Badge>
            )}
          </div>
          <Button onClick={() => setCreating(true)} disabled={atQuotaLimit}>
            <Plus className="h-4 w-4" />
            {t('projects.new_button')}
          </Button>
        </div>

        {loading ? (
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <Skeleton className="h-44 rounded-2xl" />
            <Skeleton className="h-44 rounded-2xl" />
            <Skeleton className="h-44 rounded-2xl" />
          </div>
        ) : projects.length === 0 ? (
          <Card>
            <CardContent className="py-16 text-center">
              <Folder className="mx-auto h-10 w-10 text-[var(--text-tertiary)]" />
              <p className="mt-3 text-base font-semibold text-[var(--text-primary)]">{t('projects.empty_title')}</p>
              <p className="mt-1 text-sm text-[var(--text-tertiary)]">{t('projects.empty_body')}</p>
              <Button className="mt-4" onClick={() => setCreating(true)}>
                <Plus className="h-4 w-4" />
                {t('projects.new_button')}
              </Button>
            </CardContent>
          </Card>
        ) : (
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {projects.map((p, i) => (
              <motion.div
                key={p.id}
                initial={{ opacity: 0, y: 8 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ delay: i * 0.03 }}
              >
                <Card className="overflow-hidden hover:border-[var(--accent-primary)] transition-colors h-full flex flex-col">
                  <Link to={`/account/projects/${p.id}`} className="block flex-1">
                    <CardContent className="pt-5 pb-4 space-y-3 h-full flex flex-col">
                      <div className="flex items-start justify-between gap-2">
                        <div className="min-w-0 flex-1">
                          <div className="flex items-center gap-2">
                            {p.color && <span className="inline-block h-2.5 w-2.5 rounded-full" style={{ background: p.color }} />}
                            <h3 className="font-semibold text-[var(--text-primary)] truncate">{p.name}</h3>
                          </div>
                          <div className="mt-0.5 flex items-center gap-1 text-[11px] text-[var(--text-tertiary)]">
                            <Globe className="h-3 w-3" />
                            <span className="truncate font-mono">{p.domain}</span>
                          </div>
                        </div>
                        {p.latestAudit ? (
                          <ScoreChip score={p.latestAudit.globalScore} level={p.latestAudit.globalLevel} />
                        ) : (
                          <span className="rounded-md bg-gray-100 px-2 py-0.5 text-[10px] font-medium text-gray-600">—</span>
                        )}
                      </div>

                      <div className="flex items-center gap-3 text-[11px] text-[var(--text-tertiary)]">
                        <span className="inline-flex items-center gap-1">
                          <ListTodo className="h-3 w-3" />
                          {t('projects.card_audits', { count: p.auditCount })}
                        </span>
                        {p.openChecklistCount > 0 && (
                          <span className="inline-flex items-center gap-1 text-amber-700">
                            {t('projects.card_open_tasks', { count: p.openChecklistCount })}
                          </span>
                        )}
                        {p.sharingEnabled && (
                          <span className="inline-flex items-center gap-1 text-emerald-700">
                            <Share2 className="h-3 w-3" />
                            {t('projects.card_sharing_on')}
                          </span>
                        )}
                      </div>

                      <div className="flex-1" />

                      <div className="flex items-center justify-between border-t border-[var(--border-default)] pt-3 text-[11px]">
                        <span className="text-[var(--text-tertiary)]">
                          {p.latestAudit
                            ? new Date(p.latestAudit.createdAt).toLocaleDateString(i18n.language || 'en', { day: 'numeric', month: 'short' })
                            : t('projects.card_never_run')}
                        </span>
                        <span className="inline-flex items-center gap-0.5 text-[var(--accent-primary)]">
                          {t('projects.action_open')} <ArrowRight className="h-3 w-3" />
                        </span>
                      </div>
                    </CardContent>
                  </Link>

                  <div className="flex border-t border-[var(--border-default)]">
                    <Button
                      variant="ghost"
                      size="sm"
                      className="flex-1 rounded-none h-8 text-[11px] text-[var(--text-secondary)]"
                      onClick={(e) => { e.preventDefault(); setEditing(p) }}
                    >
                      <Pencil className="h-3 w-3" /> {t('projects.action_edit')}
                    </Button>
                    <Button
                      variant="ghost"
                      size="sm"
                      className="flex-1 rounded-none h-8 text-[11px] text-red-600 hover:text-red-700 border-l border-[var(--border-default)]"
                      onClick={(e) => { e.preventDefault(); setConfirmDelete(p) }}
                    >
                      <Trash2 className="h-3 w-3" /> {t('projects.action_delete')}
                    </Button>
                  </div>
                </Card>
              </motion.div>
            ))}
          </div>
        )}
      </main>

      {creating && (
        <ProjectModal onSaved={onSaved} onClose={() => setCreating(false)} createFn={createProject} updateFn={updateProject} />
      )}
      {editing && (
        <ProjectModal initial={editing} onSaved={onSaved} onClose={() => setEditing(null)} createFn={createProject} updateFn={updateProject} />
      )}

      <Dialog open={confirmDelete !== null} onOpenChange={(open) => !open && setConfirmDelete(null)}>
        <DialogContent className="max-w-md">
          <DialogHeader>
            <DialogTitle>{t('projects.confirm_delete_title')}</DialogTitle>
          </DialogHeader>
          <p className="text-sm text-[var(--text-secondary)]">{t('projects.confirm_delete_body')}</p>
          <DialogFooter>
            <Button variant="ghost" onClick={() => setConfirmDelete(null)}>{t('projects.cancel')}</Button>
            <Button variant="destructive" onClick={() => confirmDelete && onDelete(confirmDelete)}>
              {t('projects.confirm_delete_confirm')}
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

function ProjectModal({
  initial, onSaved, onClose, createFn, updateFn,
}: {
  initial?: ProjectSummary
  onSaved: () => void
  onClose: () => void
  createFn: (body: Record<string, unknown>) => Promise<unknown>
  updateFn: (body: Record<string, unknown>) => Promise<unknown>
}) {
  const { t } = useTranslation()
  const isEdit = Boolean(initial)
  const { register, handleSubmit, formState: { isSubmitting } } = useForm<FormValues>({
    defaultValues: {
      id: initial?.id,
      name: initial?.name ?? '',
      url: initial?.url ?? '',
      notes: initial?.notes ?? '',
    },
  })

  const onSubmit = async (values: FormValues) => {
    try {
      if (isEdit) {
        await updateFn({ id: initial!.id, name: values.name, notes: values.notes })
        toast.success(t('projects.toast_updated'))
      } else {
        await createFn({ name: values.name, url: values.url, notes: values.notes })
        toast.success(t('projects.toast_created'))
      }
      onSaved()
    } catch (err: unknown) {
      const axiosErr = err as { response?: { data?: { error?: string } } }
      toast.error(axiosErr.response?.data?.error ?? 'Error')
    }
  }

  return (
    <Dialog open onOpenChange={(open) => !open && onClose()}>
      <DialogContent className="max-w-lg">
        <DialogHeader>
          <DialogTitle>{isEdit ? t('projects.modal_edit_title') : t('projects.modal_create_title')}</DialogTitle>
        </DialogHeader>
        <form onSubmit={handleSubmit(onSubmit)} className="space-y-4">
          <div className="space-y-1.5">
            <Label>{t('projects.field_name')}</Label>
            <Input placeholder={t('projects.field_name_placeholder')} {...register('name', { required: true })} />
          </div>
          <div className="space-y-1.5">
            <Label>{t('projects.field_url')}</Label>
            <Input
              type="url"
              placeholder={t('projects.field_url_placeholder')}
              disabled={isEdit}
              required={!isEdit}
              {...register('url', { required: !isEdit })}
            />
            <p className="text-[11px] text-[var(--text-tertiary)]">
              {isEdit ? t('projects.field_url_hint_edit') : t('projects.field_url_hint_create')}
            </p>
          </div>
          <div className="space-y-1.5">
            <Label>{t('projects.field_notes')}</Label>
            <Textarea rows={2} placeholder={t('projects.field_notes_placeholder')} {...register('notes')} />
          </div>
          <DialogFooter>
            <Button type="button" variant="ghost" onClick={onClose}>{t('projects.cancel')}</Button>
            <Button type="submit" disabled={isSubmitting}>
              {isSubmitting && <Loader2 className="h-4 w-4 animate-spin" />}
              {t('projects.save')}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  )
}
