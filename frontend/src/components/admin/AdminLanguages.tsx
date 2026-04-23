import { useEffect, useState, useCallback } from 'react'
import { useForm } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'
import { Loader2, Plus, Trash2, Globe2, Languages as LanguagesIcon, Wand2, CheckCircle2, XCircle, FileJson, Eye, EyeOff } from 'lucide-react'
import { toast } from 'sonner'
import { Card, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Badge } from '@/components/ui/badge'
import { Skeleton } from '@/components/ui/skeleton'
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog'
import { Switch } from '@/components/ui/switch'
import { useAdmin } from '@/hooks/useAdmin'
import { useLanguagesStore } from '@/store/languagesStore'
import { COMMON_LANGUAGE_NAMES } from '@/i18n'

interface AdminLanguage {
  code: string
  name: string
  nativeName: string
  isActive: boolean
  isPublic: boolean
  sortOrder: number
  createdAt: string
  hasFrontendBundle: boolean
}

interface FormValues {
  code: string
  name: string
  nativeName: string
  isActive: boolean
  isPublic: boolean
  sortOrder: number
}

/**
 * /admin/languages — gestión de idiomas activos en la app.
 *
 * El admin puede:
 *   - Ver todos los idiomas registrados (con flags is_active / is_public).
 *   - Crear un idioma nuevo (código ISO 639-1 de 2 letras).
 *   - Activar/desactivar un idioma o ocultarlo del switcher público.
 *   - Eliminar un idioma (borra sus overrides de traducción).
 *   - Saltar al editor de traducciones del idioma con un click.
 *
 * El idioma default (definido por Translator::DEFAULT_LANG, 'en') no se
 * puede eliminar ni desactivar — es la fuente de verdad del bundle.
 */
export default function AdminLanguages() {
  const { t } = useTranslation()
  const { fetchAdminLanguages, createAdminLanguage, updateAdminLanguage, deleteAdminLanguage } = useAdmin()
  const reloadPublic = useLanguagesStore(s => s.load)
  const [languages, setLanguages] = useState<AdminLanguage[]>([])
  const [defaultCode, setDefaultCode] = useState('en')
  const [loading, setLoading] = useState(true)
  const [creating, setCreating] = useState(false)
  const [confirmDelete, setConfirmDelete] = useState<AdminLanguage | null>(null)
  const [busyCode, setBusyCode] = useState<string | null>(null)

  const reload = useCallback(async () => {
    setLoading(true)
    const data = await fetchAdminLanguages()
    if (data) {
      setLanguages(data.languages)
      setDefaultCode(data.default)
    }
    setLoading(false)
  }, [fetchAdminLanguages])

  useEffect(() => { reload() }, [reload])

  const toggleFlag = async (lang: AdminLanguage, flag: 'isActive' | 'isPublic', next: boolean) => {
    setBusyCode(lang.code)
    try {
      await updateAdminLanguage({ ...lang, [flag]: next })
      toast.success(t('admin_languages.toast_saved'))
      await reload()
      // Refrescar el store del switcher público — si cambió isPublic,
      // el dropdown del header debe reflejar la lista nueva al instante.
      await reloadPublic()
    } catch (err: unknown) {
      const axiosErr = err as { response?: { data?: { error?: string } } }
      toast.error(axiosErr.response?.data?.error ?? t('admin_languages.toast_save_error'))
    }
    setBusyCode(null)
  }

  const onDelete = async (lang: AdminLanguage) => {
    setBusyCode(lang.code)
    try {
      await deleteAdminLanguage(lang.code)
      toast.success(t('admin_languages.toast_deleted'))
      setConfirmDelete(null)
      await reload()
      await reloadPublic()
    } catch (err: unknown) {
      const axiosErr = err as { response?: { data?: { error?: string } } }
      toast.error(axiosErr.response?.data?.error ?? t('admin_languages.toast_delete_error'))
      setConfirmDelete(null)
    }
    setBusyCode(null)
  }

  return (
    <div className="space-y-5">
      <div className="flex items-start justify-between gap-4 flex-wrap">
        <div>
          <h1 className="text-2xl font-bold text-[var(--text-primary)] flex items-center gap-2">
            <LanguagesIcon className="h-6 w-6 text-[var(--accent-primary)]" />
            {t('admin_languages.title')}
          </h1>
          <p className="text-sm text-[var(--text-secondary)] mt-1">{t('admin_languages.subtitle')}</p>
        </div>
        <Button onClick={() => setCreating(true)}>
          <Plus className="h-4 w-4" />
          {t('admin_languages.add')}
        </Button>
      </div>

      {loading ? (
        <Skeleton className="h-64 rounded-2xl" />
      ) : languages.length === 0 ? (
        <Card><CardContent className="py-12 text-center text-sm text-[var(--text-tertiary)]">{t('admin_languages.empty')}</CardContent></Card>
      ) : (
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
          {languages.map((lang) => {
            const isDefault = lang.code === defaultCode
            return (
              <Card key={lang.code} className={isDefault ? 'border-[var(--accent-primary)]/40' : ''}>
                <CardContent className="pt-5 space-y-3">
                  <div className="flex items-start justify-between gap-2">
                    <div className="min-w-0">
                      <div className="flex items-center gap-2">
                        <span className="font-mono text-xs uppercase bg-[var(--bg-secondary)] rounded px-1.5 py-0.5 text-[var(--text-tertiary)]">{lang.code}</span>
                        <span className="font-semibold text-sm text-[var(--text-primary)] truncate">{lang.nativeName || lang.name}</span>
                        {isDefault && (
                          <Badge variant="secondary" className="text-[9px]">{t('admin_languages.badge_default')}</Badge>
                        )}
                      </div>
                      {lang.name && lang.name !== lang.nativeName && (
                        <p className="text-[11px] text-[var(--text-tertiary)] mt-0.5">{lang.name}</p>
                      )}
                      <div className="mt-1 flex flex-wrap items-center gap-2 text-[10px] text-[var(--text-tertiary)]">
                        {lang.hasFrontendBundle ? (
                          <span className="inline-flex items-center gap-1 text-emerald-700"><FileJson className="h-3 w-3" /> {t('admin_languages.bundle_present')}</span>
                        ) : (
                          <span className="inline-flex items-center gap-1 text-amber-700"><FileJson className="h-3 w-3" /> {t('admin_languages.bundle_missing')}</span>
                        )}
                      </div>
                    </div>
                  </div>

                  <div className="space-y-1.5 text-xs">
                    <div className="flex items-center justify-between">
                      <span className="inline-flex items-center gap-1 text-[var(--text-secondary)]">
                        {lang.isActive ? <CheckCircle2 className="h-3.5 w-3.5 text-emerald-600" /> : <XCircle className="h-3.5 w-3.5 text-red-500" />}
                        {t('admin_languages.flag_active')}
                      </span>
                      <Switch
                        checked={lang.isActive}
                        disabled={isDefault || busyCode === lang.code}
                        onCheckedChange={(v) => toggleFlag(lang, 'isActive', v)}
                      />
                    </div>
                    <div className="flex items-center justify-between">
                      <span className="inline-flex items-center gap-1 text-[var(--text-secondary)]">
                        {lang.isPublic ? <Eye className="h-3.5 w-3.5 text-emerald-600" /> : <EyeOff className="h-3.5 w-3.5 text-[var(--text-tertiary)]" />}
                        {t('admin_languages.flag_public')}
                      </span>
                      <Switch
                        checked={lang.isPublic}
                        disabled={isDefault || busyCode === lang.code}
                        onCheckedChange={(v) => toggleFlag(lang, 'isPublic', v)}
                      />
                    </div>
                  </div>

                  <div className="flex items-center gap-2 pt-1">
                    {!isDefault && (
                      <Link to={`/admin/translations?lang=${lang.code}`}>
                        <Button variant="outline" size="sm">
                          <Wand2 className="h-3.5 w-3.5" />
                          {t('admin_languages.translate_cta')}
                        </Button>
                      </Link>
                    )}
                    {!isDefault && (
                      <Button
                        variant="ghost"
                        size="sm"
                        className="text-red-600 hover:text-red-700"
                        onClick={() => setConfirmDelete(lang)}
                      >
                        <Trash2 className="h-3.5 w-3.5" />
                        {t('common.delete')}
                      </Button>
                    )}
                  </div>
                </CardContent>
              </Card>
            )
          })}
        </div>
      )}

      {/* Create dialog */}
      {creating && (
        <CreateLanguageDialog
          onClose={() => setCreating(false)}
          onCreated={async () => {
            setCreating(false)
            await reload()
            await reloadPublic()
          }}
          createLanguage={createAdminLanguage}
        />
      )}

      {/* Delete confirmation */}
      <Dialog open={!!confirmDelete} onOpenChange={(open) => !open && setConfirmDelete(null)}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{t('admin_languages.delete_title')}</DialogTitle>
          </DialogHeader>
          <p className="text-sm text-[var(--text-secondary)]">
            {t('admin_languages.delete_body', { name: confirmDelete?.nativeName ?? confirmDelete?.code ?? '' })}
          </p>
          <DialogFooter>
            <Button variant="ghost" onClick={() => setConfirmDelete(null)}>{t('common.cancel')}</Button>
            <Button
              variant="destructive"
              disabled={!!busyCode}
              onClick={() => confirmDelete && onDelete(confirmDelete)}
            >
              {busyCode ? <Loader2 className="h-4 w-4 animate-spin" /> : <Trash2 className="h-4 w-4" />}
              {t('common.delete')}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  )
}

function CreateLanguageDialog({
  onClose,
  onCreated,
  createLanguage,
}: {
  onClose: () => void
  onCreated: () => Promise<void>
  createLanguage: (body: { code: string; name?: string; nativeName?: string; isActive?: boolean; isPublic?: boolean; sortOrder?: number }) => Promise<unknown>
}) {
  const { t } = useTranslation()
  const { register, handleSubmit, watch, setValue, formState: { errors, isSubmitting } } = useForm<FormValues>({
    defaultValues: { code: '', name: '', nativeName: '', isActive: true, isPublic: true, sortOrder: 100 },
  })

  const currentCode = (watch('code') || '').toLowerCase()

  // Autocompletar nombre al escribir el código — si es un idioma conocido,
  // sugerimos el nombre nativo común. El admin puede editar ambos luego.
  const onCodeBlur = () => {
    if (!currentCode || currentCode.length !== 2) return
    const suggested = COMMON_LANGUAGE_NAMES[currentCode]
    if (suggested) {
      if (!watch('name')) setValue('name', suggested)
      if (!watch('nativeName')) setValue('nativeName', suggested)
    }
  }

  const onSubmit = async (values: FormValues) => {
    try {
      await createLanguage({
        code: values.code.toLowerCase(),
        name: values.name || undefined,
        nativeName: values.nativeName || undefined,
        isActive: values.isActive,
        isPublic: values.isPublic,
        sortOrder: values.sortOrder,
      })
      toast.success(t('admin_languages.toast_created'))
      await onCreated()
    } catch (err: unknown) {
      const axiosErr = err as { response?: { data?: { error?: string } } }
      toast.error(axiosErr.response?.data?.error ?? t('admin_languages.toast_create_error'))
    }
  }

  return (
    <Dialog open onOpenChange={(open) => !open && onClose()}>
      <DialogContent className="max-w-md">
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2">
            <Globe2 className="h-5 w-5 text-[var(--accent-primary)]" />
            {t('admin_languages.create_title')}
          </DialogTitle>
        </DialogHeader>
        <form onSubmit={handleSubmit(onSubmit)} className="space-y-3">
          <div>
            <Label>{t('admin_languages.field_code')}</Label>
            <Input
              {...register('code', {
                required: true,
                pattern: /^[a-z]{2}$/i,
                maxLength: 2,
              })}
              onBlur={onCodeBlur}
              placeholder="pt"
              maxLength={2}
              className="uppercase font-mono"
            />
            <p className="mt-1 text-[10px] text-[var(--text-tertiary)]">{t('admin_languages.field_code_hint')}</p>
            {errors.code && <p className="mt-1 text-[10px] text-red-600">{t('admin_languages.field_code_invalid')}</p>}
          </div>
          <div>
            <Label>{t('admin_languages.field_name')}</Label>
            <Input {...register('name')} placeholder="Portuguese" />
          </div>
          <div>
            <Label>{t('admin_languages.field_native_name')}</Label>
            <Input {...register('nativeName')} placeholder="Português" />
          </div>
          <div className="flex items-center justify-between pt-1">
            <span className="text-sm">{t('admin_languages.flag_active')}</span>
            <Switch
              checked={watch('isActive')}
              onCheckedChange={(v) => setValue('isActive', v)}
            />
          </div>
          <div className="flex items-center justify-between">
            <span className="text-sm">{t('admin_languages.flag_public')}</span>
            <Switch
              checked={watch('isPublic')}
              onCheckedChange={(v) => setValue('isPublic', v)}
            />
          </div>
          <p className="text-[11px] text-[var(--text-tertiary)] bg-[var(--bg-secondary)] p-2 rounded">
            {t('admin_languages.create_hint')}
          </p>
          <DialogFooter>
            <Button type="button" variant="ghost" onClick={onClose}>{t('common.cancel')}</Button>
            <Button type="submit" disabled={isSubmitting}>
              {isSubmitting && <Loader2 className="h-4 w-4 animate-spin" />}
              {t('admin_languages.create_submit')}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  )
}
