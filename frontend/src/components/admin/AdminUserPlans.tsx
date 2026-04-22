import { useEffect, useState, useCallback } from 'react'
import { useForm } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { Loader2, Plus, Pencil, Trash2, Gauge } from 'lucide-react'
import { toast } from 'sonner'
import { Card, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { Label } from '@/components/ui/label'
import { Badge } from '@/components/ui/badge'
import { Skeleton } from '@/components/ui/skeleton'
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog'
import { Switch } from '@/components/ui/switch'
import { useAdmin } from '@/hooks/useAdmin'

interface Plan {
  id: number
  name: string
  monthlyLimit: number
  maxProjects: number
  description: string | null
  isActive: boolean
  userCount: number
  createdAt: string
}

interface FormValues {
  id?: number
  name: string
  monthlyLimit: number
  maxProjects: number
  description: string
  isActive: boolean
}

export default function AdminUserPlans() {
  const { t } = useTranslation()
  const { fetchUserPlans, createUserPlan, updateUserPlan, deleteUserPlan } = useAdmin()
  const [plans, setPlans] = useState<Plan[]>([])
  const [loading, setLoading] = useState(true)
  const [editing, setEditing] = useState<Plan | null>(null)
  const [creating, setCreating] = useState(false)
  const [confirmDelete, setConfirmDelete] = useState<Plan | null>(null)

  const reload = useCallback(async () => {
    setLoading(true)
    const data = await fetchUserPlans()
    setPlans((data?.plans ?? []) as Plan[])
    setLoading(false)
  }, [fetchUserPlans])

  useEffect(() => { reload() }, [reload])

  const onSaved = async () => {
    setCreating(false)
    setEditing(null)
    await reload()
  }

  const onDelete = async (p: Plan) => {
    try {
      await deleteUserPlan(p.id)
      toast.success(t('admin_user_plans.toast_deleted'))
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
      <div className="flex items-start justify-between gap-4 flex-wrap">
        <div>
          <h1 className="text-2xl font-bold text-[var(--text-primary)] flex items-center gap-2">
            <Gauge className="h-6 w-6 text-[var(--accent-primary)]" />
            {t('admin_user_plans.title')}
          </h1>
          <p className="mt-1 text-sm text-[var(--text-secondary)]">{t('admin_user_plans.subtitle')}</p>
        </div>
        <Button onClick={() => setCreating(true)}>
          <Plus className="h-4 w-4" />
          {t('admin_user_plans.new_button')}
        </Button>
      </div>

      {loading ? (
        <Skeleton className="h-64 rounded-2xl" />
      ) : plans.length === 0 ? (
        <Card><CardContent className="py-12 text-center text-sm text-[var(--text-tertiary)]">{t('admin_user_plans.empty')}</CardContent></Card>
      ) : (
        <Card>
          <CardContent className="pt-5">
            <div className="overflow-hidden rounded-lg border border-[var(--border-default)]">
              <table className="w-full text-sm">
                <thead className="bg-[var(--bg-secondary)] text-left text-[10px] uppercase tracking-wider text-[var(--text-tertiary)]">
                  <tr>
                    <th className="px-3 py-2">{t('admin_user_plans.col_name')}</th>
                    <th className="px-3 py-2">{t('admin_user_plans.col_limit')}</th>
                    <th className="px-3 py-2">{t('admin_user_plans.col_projects')}</th>
                    <th className="px-3 py-2 text-right">{t('admin_user_plans.col_users')}</th>
                    <th className="px-3 py-2">{t('admin_user_plans.col_status')}</th>
                    <th className="px-3 py-2 w-32"></th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-[var(--border-default)]">
                  {plans.map((p) => (
                    <tr key={p.id} className="hover:bg-[var(--bg-secondary)]">
                      <td className="px-3 py-2">
                        <div className="font-medium text-[var(--text-primary)]">{p.name}</div>
                        {p.description && <div className="text-[11px] text-[var(--text-tertiary)] mt-0.5 line-clamp-1">{p.description}</div>}
                      </td>
                      <td className="px-3 py-2">
                        {p.monthlyLimit === 0
                          ? <Badge variant="success" className="text-[10px]">{t('admin_user_plans.limit_unlimited')}</Badge>
                          : <span className="text-xs tabular-nums">{t('admin_user_plans.limit_per_month', { count: p.monthlyLimit })}</span>}
                      </td>
                      <td className="px-3 py-2">
                        {p.maxProjects === 0
                          ? <Badge variant="success" className="text-[10px]">{t('admin_user_plans.limit_unlimited')}</Badge>
                          : <span className="text-xs tabular-nums">{p.maxProjects}</span>}
                      </td>
                      <td className="px-3 py-2 text-right tabular-nums text-xs">{p.userCount}</td>
                      <td className="px-3 py-2">
                        {p.isActive
                          ? <Badge variant="success" className="text-[10px]">{t('admin_user_plans.status_active')}</Badge>
                          : <Badge variant="secondary" className="text-[10px]">{t('admin_user_plans.status_inactive')}</Badge>}
                      </td>
                      <td className="px-3 py-2 text-right">
                        <div className="flex justify-end gap-1">
                          <Button size="sm" variant="ghost" className="h-7 px-2" onClick={() => setEditing(p)}>
                            <Pencil className="h-3.5 w-3.5" />
                          </Button>
                          <Button size="sm" variant="ghost" className="h-7 px-2 text-red-600 hover:text-red-700" onClick={() => setConfirmDelete(p)}>
                            <Trash2 className="h-3.5 w-3.5" />
                          </Button>
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </CardContent>
        </Card>
      )}

      {/* Modal crear */}
      {creating && (
        <PlanModal onSaved={onSaved} onClose={() => setCreating(false)} createFn={createUserPlan} updateFn={updateUserPlan} />
      )}
      {/* Modal editar */}
      {editing && (
        <PlanModal initial={editing} onSaved={onSaved} onClose={() => setEditing(null)} createFn={createUserPlan} updateFn={updateUserPlan} />
      )}

      {/* Confirm delete */}
      <Dialog open={confirmDelete !== null} onOpenChange={(open) => !open && setConfirmDelete(null)}>
        <DialogContent className="max-w-md">
          <DialogHeader>
            <DialogTitle>{t('admin_user_plans.confirm_delete_title')}</DialogTitle>
          </DialogHeader>
          <p className="text-sm text-[var(--text-secondary)]">{t('admin_user_plans.confirm_delete_body')}</p>
          <DialogFooter>
            <Button variant="ghost" onClick={() => setConfirmDelete(null)}>{t('admin_user_plans.cancel')}</Button>
            <Button variant="destructive" onClick={() => confirmDelete && onDelete(confirmDelete)}>
              {t('admin_user_plans.confirm_delete_confirm')}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  )
}

function PlanModal({
  initial, onSaved, onClose, createFn, updateFn,
}: {
  initial?: Plan
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
      monthlyLimit: initial?.monthlyLimit ?? 10,
      maxProjects: initial?.maxProjects ?? 0,
      description: initial?.description ?? '',
      isActive: initial?.isActive ?? true,
    },
  })

  const onSubmit = async (values: FormValues) => {
    try {
      const body = {
        ...(isEdit ? { id: initial!.id } : {}),
        name: values.name,
        monthlyLimit: Number(values.monthlyLimit),
        maxProjects: Number(values.maxProjects),
        description: values.description,
        isActive: Boolean(values.isActive),
      }
      if (isEdit) {
        await updateFn(body)
        toast.success(t('admin_user_plans.toast_updated'))
      } else {
        await createFn(body)
        toast.success(t('admin_user_plans.toast_created'))
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
          <DialogTitle>{isEdit ? t('admin_user_plans.modal_edit_title') : t('admin_user_plans.modal_create_title')}</DialogTitle>
        </DialogHeader>
        <form onSubmit={handleSubmit(onSubmit)} className="space-y-4">
          <div className="space-y-1.5">
            <Label>{t('admin_user_plans.field_name')}</Label>
            <Input placeholder={t('admin_user_plans.field_name_placeholder')} {...register('name', { required: true })} />
          </div>
          <div className="grid gap-3 sm:grid-cols-2">
            <div className="space-y-1.5">
              <Label>{t('admin_user_plans.field_limit')}</Label>
              <Input type="number" min={0} {...register('monthlyLimit', { valueAsNumber: true })} />
              <p className="text-[11px] text-[var(--text-tertiary)]">{t('admin_user_plans.field_limit_hint')}</p>
            </div>
            <div className="space-y-1.5">
              <Label>{t('admin_user_plans.field_max_projects')}</Label>
              <Input type="number" min={0} {...register('maxProjects', { valueAsNumber: true })} />
              <p className="text-[11px] text-[var(--text-tertiary)]">{t('admin_user_plans.field_max_projects_hint')}</p>
            </div>
          </div>
          <div className="space-y-1.5">
            <Label>{t('admin_user_plans.field_description')}</Label>
            <Textarea rows={2} {...register('description')} />
            <p className="text-[11px] text-[var(--text-tertiary)]">{t('admin_user_plans.field_description_hint')}</p>
          </div>
          <div className="flex items-center gap-2">
            <Switch id="planActive" defaultChecked={initial?.isActive ?? true} {...register('isActive')} />
            <Label htmlFor="planActive">{t('admin_user_plans.field_active')}</Label>
          </div>
          <DialogFooter>
            <Button type="button" variant="ghost" onClick={onClose}>{t('admin_user_plans.cancel')}</Button>
            <Button type="submit" disabled={isSubmitting}>
              {isSubmitting && <Loader2 className="h-4 w-4 animate-spin" />}
              {t('admin_user_plans.save')}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  )
}
