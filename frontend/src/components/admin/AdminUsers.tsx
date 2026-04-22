import { useEffect, useState, useCallback } from 'react'
import { useForm } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { Loader2, Plus, Pencil, Trash2, UserCog, Search } from 'lucide-react'
import { toast } from 'sonner'
import { Card, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Badge } from '@/components/ui/badge'
import { Skeleton } from '@/components/ui/skeleton'
import { Select, SelectTrigger, SelectValue, SelectContent, SelectItem } from '@/components/ui/select'
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog'
import { Switch } from '@/components/ui/switch'
import { useAdmin } from '@/hooks/useAdmin'

interface UserRow {
  id: number
  email: string
  name: string | null
  planId: number | null
  planName: string | null
  planLimit: number | null
  isActive: boolean
  monthUsed: number
  createdAt: string
  lastLoginAt: string | null
}

interface PlanOption {
  id: number
  name: string
  monthlyLimit: number
  isActive: boolean
}

interface FormValues {
  id?: number
  email: string
  name: string
  planId: string  // empty = null
  password: string
  isActive: boolean
}

export default function AdminUsers() {
  const { t, i18n } = useTranslation()
  const { fetchUsers, createUser, updateUser, deleteUser, fetchUserPlans } = useAdmin()

  const [users, setUsers] = useState<UserRow[]>([])
  const [plans, setPlans] = useState<PlanOption[]>([])
  const [loading, setLoading] = useState(true)
  const [search, setSearch] = useState('')
  const [planFilter, setPlanFilter] = useState<string>('any')
  const [activeFilter, setActiveFilter] = useState<'any' | 'yes' | 'no'>('any')
  const [creating, setCreating] = useState(false)
  const [editing, setEditing] = useState<UserRow | null>(null)
  const [confirmDelete, setConfirmDelete] = useState<UserRow | null>(null)

  const reload = useCallback(async () => {
    setLoading(true)
    const params: Record<string, string | number> = {}
    if (search) params.search = search
    if (planFilter !== 'any') params.plan = planFilter
    if (activeFilter !== 'any') params.active = activeFilter
    const data = await fetchUsers(params)
    setUsers((data?.users ?? []) as UserRow[])
    setLoading(false)
  }, [fetchUsers, search, planFilter, activeFilter])

  useEffect(() => {
    fetchUserPlans().then((data) => {
      const list = (data?.plans ?? []) as PlanOption[]
      setPlans(list.filter(p => p.isActive))
    })
  }, [fetchUserPlans])

  useEffect(() => { reload() }, [reload])

  const onSaved = async () => {
    setCreating(false)
    setEditing(null)
    await reload()
  }

  const onDelete = async (u: UserRow) => {
    try {
      await deleteUser(u.id)
      toast.success(t('admin_users.toast_deleted'))
      setConfirmDelete(null)
      await reload()
    } catch (err: unknown) {
      const axiosErr = err as { response?: { data?: { error?: string } } }
      toast.error(axiosErr.response?.data?.error ?? 'Error')
      setConfirmDelete(null)
    }
  }

  const formatUsage = useCallback((u: UserRow): string => {
    if (u.planLimit === null) return '—'
    if (u.planLimit === 0) return t('admin_users.usage_unlimited', { used: u.monthUsed })
    return `${u.monthUsed} / ${u.planLimit}`
  }, [t])

  return (
    <div className="space-y-5">
      <div className="flex items-start justify-between gap-4 flex-wrap">
        <div>
          <h1 className="text-2xl font-bold text-[var(--text-primary)] flex items-center gap-2">
            <UserCog className="h-6 w-6 text-[var(--accent-primary)]" />
            {t('admin_users.title')}
          </h1>
          <p className="mt-1 text-sm text-[var(--text-secondary)]">{t('admin_users.subtitle')}</p>
        </div>
        <Button onClick={() => setCreating(true)}>
          <Plus className="h-4 w-4" />
          {t('admin_users.new_button')}
        </Button>
      </div>

      <Card>
        <CardContent className="pt-5 space-y-4">
          {/* Filtros */}
          <div className="flex flex-wrap items-center gap-2">
            <div className="relative flex-1 min-w-[220px] max-w-sm">
              <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-[var(--text-tertiary)]" />
              <Input
                className="pl-9 h-9 text-xs"
                placeholder={t('admin_users.search_placeholder')}
                value={search}
                onChange={(e) => setSearch(e.target.value)}
              />
            </div>
            <Select value={planFilter} onValueChange={setPlanFilter}>
              <SelectTrigger className="w-[160px] h-9 text-xs"><SelectValue /></SelectTrigger>
              <SelectContent>
                <SelectItem value="any">{t('admin_users.filter_plan_any')}</SelectItem>
                {plans.map(p => <SelectItem key={p.id} value={String(p.id)}>{p.name}</SelectItem>)}
              </SelectContent>
            </Select>
            <Select value={activeFilter} onValueChange={(v) => setActiveFilter(v as 'any' | 'yes' | 'no')}>
              <SelectTrigger className="w-[160px] h-9 text-xs"><SelectValue /></SelectTrigger>
              <SelectContent>
                <SelectItem value="any">{t('admin_users.filter_active_any')}</SelectItem>
                <SelectItem value="yes">{t('admin_users.filter_active_yes')}</SelectItem>
                <SelectItem value="no">{t('admin_users.filter_active_no')}</SelectItem>
              </SelectContent>
            </Select>
          </div>

          {loading ? (
            <Skeleton className="h-64" />
          ) : users.length === 0 ? (
            <p className="py-8 text-center text-sm text-[var(--text-tertiary)]">{t('admin_users.empty')}</p>
          ) : (
            <div className="overflow-hidden rounded-lg border border-[var(--border-default)]">
              <table className="w-full text-sm">
                <thead className="bg-[var(--bg-secondary)] text-left text-[10px] uppercase tracking-wider text-[var(--text-tertiary)]">
                  <tr>
                    <th className="px-3 py-2">{t('admin_users.col_email')}</th>
                    <th className="px-3 py-2">{t('admin_users.col_name')}</th>
                    <th className="px-3 py-2">{t('admin_users.col_plan')}</th>
                    <th className="px-3 py-2 text-right">{t('admin_users.col_usage')}</th>
                    <th className="px-3 py-2">{t('admin_users.col_status')}</th>
                    <th className="px-3 py-2">{t('admin_users.col_last_login')}</th>
                    <th className="px-3 py-2 w-24"></th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-[var(--border-default)]">
                  {users.map((u) => (
                    <tr key={u.id} className="hover:bg-[var(--bg-secondary)]">
                      <td className="px-3 py-2 font-medium text-[var(--text-primary)]">{u.email}</td>
                      <td className="px-3 py-2 text-[var(--text-secondary)]">{u.name ?? '—'}</td>
                      <td className="px-3 py-2">
                        {u.planName
                          ? <span className="text-xs">{u.planName}</span>
                          : <span className="text-[11px] text-amber-700">{t('admin_users.no_plan_label')}</span>}
                      </td>
                      <td className="px-3 py-2 text-right tabular-nums text-xs">{formatUsage(u)}</td>
                      <td className="px-3 py-2">
                        {u.isActive
                          ? <Badge variant="success" className="text-[10px]">{t('admin_users.status_active')}</Badge>
                          : <Badge variant="secondary" className="text-[10px]">{t('admin_users.status_disabled')}</Badge>}
                      </td>
                      <td className="px-3 py-2 text-[11px] text-[var(--text-tertiary)]">
                        {u.lastLoginAt
                          ? new Date(u.lastLoginAt).toLocaleDateString(i18n.language || 'en', { day: 'numeric', month: 'short', year: 'numeric' })
                          : '—'}
                      </td>
                      <td className="px-3 py-2 text-right">
                        <div className="flex justify-end gap-1">
                          <Button size="sm" variant="ghost" className="h-7 px-2" onClick={() => setEditing(u)}>
                            <Pencil className="h-3.5 w-3.5" />
                          </Button>
                          <Button size="sm" variant="ghost" className="h-7 px-2 text-red-600 hover:text-red-700" onClick={() => setConfirmDelete(u)}>
                            <Trash2 className="h-3.5 w-3.5" />
                          </Button>
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </CardContent>
      </Card>

      {creating && (
        <UserModal onSaved={onSaved} onClose={() => setCreating(false)} createFn={createUser} updateFn={updateUser} plans={plans} />
      )}
      {editing && (
        <UserModal initial={editing} onSaved={onSaved} onClose={() => setEditing(null)} createFn={createUser} updateFn={updateUser} plans={plans} />
      )}

      <Dialog open={confirmDelete !== null} onOpenChange={(open) => !open && setConfirmDelete(null)}>
        <DialogContent className="max-w-md">
          <DialogHeader>
            <DialogTitle>{t('admin_users.confirm_delete_title')}</DialogTitle>
          </DialogHeader>
          <p className="text-sm text-[var(--text-secondary)]">{t('admin_users.confirm_delete_body')}</p>
          <DialogFooter>
            <Button variant="ghost" onClick={() => setConfirmDelete(null)}>{t('admin_users.cancel')}</Button>
            <Button variant="destructive" onClick={() => confirmDelete && onDelete(confirmDelete)}>
              {t('admin_users.confirm_delete_confirm')}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  )
}

function UserModal({
  initial, onSaved, onClose, createFn, updateFn, plans,
}: {
  initial?: UserRow
  onSaved: () => void
  onClose: () => void
  createFn: (body: Record<string, unknown>) => Promise<unknown>
  updateFn: (body: Record<string, unknown>) => Promise<unknown>
  plans: PlanOption[]
}) {
  const { t } = useTranslation()
  const isEdit = Boolean(initial)
  const { register, handleSubmit, setValue, watch, formState: { isSubmitting } } = useForm<FormValues>({
    defaultValues: {
      id: initial?.id,
      email: initial?.email ?? '',
      name: initial?.name ?? '',
      planId: initial?.planId !== null && initial?.planId !== undefined ? String(initial.planId) : '',
      password: '',
      isActive: initial?.isActive ?? true,
    },
  })

  // Controlled plan select — react-hook-form + shadcn Select need manual glue
  const planId = watch('planId')
  const isActive = watch('isActive')

  const onSubmit = async (values: FormValues) => {
    try {
      const body: Record<string, unknown> = {
        ...(isEdit ? { id: initial!.id } : {}),
        email: values.email,
        name: values.name,
        planId: values.planId === '' ? null : Number(values.planId),
        isActive: Boolean(values.isActive),
      }
      if (values.password && values.password !== '') body.password = values.password

      if (isEdit) {
        await updateFn(body)
        toast.success(t('admin_users.toast_updated'))
      } else {
        await createFn(body)
        toast.success(t('admin_users.toast_created'))
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
          <DialogTitle>{isEdit ? t('admin_users.modal_edit_title') : t('admin_users.modal_create_title')}</DialogTitle>
        </DialogHeader>
        <form onSubmit={handleSubmit(onSubmit)} className="space-y-4">
          <div className="space-y-1.5">
            <Label>{t('admin_users.field_email')}</Label>
            <Input type="email" required {...register('email', { required: true })} />
          </div>

          <div className="space-y-1.5">
            <Label>{t('admin_users.field_name')}</Label>
            <Input {...register('name')} />
            <p className="text-[11px] text-[var(--text-tertiary)]">{t('admin_users.field_name_hint')}</p>
          </div>

          <div className="space-y-1.5">
            <Label>{t('admin_users.field_plan')}</Label>
            <Select value={planId} onValueChange={(v) => setValue('planId', v)}>
              <SelectTrigger><SelectValue /></SelectTrigger>
              <SelectContent>
                <SelectItem value="">{t('admin_users.no_plan_label')}</SelectItem>
                {plans.map(p => <SelectItem key={p.id} value={String(p.id)}>{p.name}</SelectItem>)}
              </SelectContent>
            </Select>
          </div>

          <div className="space-y-1.5">
            <Label>{t('admin_users.field_password')}</Label>
            <Input
              type="password"
              autoComplete="new-password"
              required={!isEdit}
              {...register('password', { required: !isEdit, minLength: 10 })}
            />
            <p className="text-[11px] text-[var(--text-tertiary)]">
              {isEdit ? t('admin_users.field_password_hint_edit') : t('admin_users.field_password_hint_create')}
            </p>
          </div>

          <div className="space-y-1.5">
            <div className="flex items-center gap-2">
              <Switch id="userActive" checked={isActive} onCheckedChange={(v: boolean) => setValue('isActive', v)} />
              <Label htmlFor="userActive">{t('admin_users.field_active')}</Label>
            </div>
            <p className="text-[11px] text-[var(--text-tertiary)]">{t('admin_users.field_active_hint')}</p>
          </div>

          <DialogFooter>
            <Button type="button" variant="ghost" onClick={onClose}>{t('admin_users.cancel')}</Button>
            <Button type="submit" disabled={isSubmitting}>
              {isSubmitting && <Loader2 className="h-4 w-4 animate-spin" />}
              {t('admin_users.save')}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  )
}
