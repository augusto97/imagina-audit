import { useEffect, useState, useCallback, useRef } from 'react'
import { useForm } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'
import { Loader2, Plus, Trash2, Globe2, Languages as LanguagesIcon, Wand2, CheckCircle2, XCircle, FileJson, Eye, EyeOff, Download, Upload, Info } from 'lucide-react'
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

type ImportMode = 'fill_missing' | 'replace_all' | 'smart_merge'

interface ImportPreview {
  dryRun?: boolean
  applied?: boolean
  mode: string
  lang: string
  languageCreated?: boolean
  totalInPack?: number
  willAdd?: number
  willChange?: number
  willSkip?: number
  added?: number
  changed?: number
  skipped?: number
  truncated?: boolean
  changes?: Array<{
    namespace: string
    key: string
    currentValue: string | null
    incomingValue: string
    action: 'add' | 'change' | 'skip'
    reason?: string
  }>
}

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
  const { fetchAdminLanguages, createAdminLanguage, updateAdminLanguage, deleteAdminLanguage, exportLanguagePack, importLanguagePack } = useAdmin()
  const reloadPublic = useLanguagesStore(s => s.load)
  const [languages, setLanguages] = useState<AdminLanguage[]>([])
  const [defaultCode, setDefaultCode] = useState('en')
  const [loading, setLoading] = useState(true)
  const [creating, setCreating] = useState(false)
  const [confirmDelete, setConfirmDelete] = useState<AdminLanguage | null>(null)
  const [busyCode, setBusyCode] = useState<string | null>(null)
  const [importFor, setImportFor] = useState<AdminLanguage | 'new' | null>(null)

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

  const handleExport = async (lang: AdminLanguage) => {
    setBusyCode(lang.code)
    try {
      await exportLanguagePack(lang.code)
      toast.success(t('admin_languages.toast_exported'))
    } catch (err: unknown) {
      const axiosErr = err as { response?: { data?: { error?: string } } }
      toast.error(axiosErr.response?.data?.error ?? t('admin_languages.toast_export_error'))
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
        <div className="flex items-center gap-2">
          <Button variant="outline" onClick={() => setImportFor('new')}>
            <Upload className="h-4 w-4" />
            {t('admin_languages.import_pack')}
          </Button>
          <Button onClick={() => setCreating(true)}>
            <Plus className="h-4 w-4" />
            {t('admin_languages.add')}
          </Button>
        </div>
      </div>

      {/* Info notice sobre cómo funciona el export/import + actualizaciones */}
      <Card className="border-blue-200 bg-blue-50/40">
        <CardContent className="pt-4 pb-4">
          <div className="flex items-start gap-3">
            <Info className="h-4 w-4 mt-0.5 text-blue-600 shrink-0" />
            <div className="space-y-1 text-xs text-[var(--text-secondary)]">
              <p className="font-semibold text-[var(--text-primary)]">{t('admin_languages.info_title')}</p>
              <p>{t('admin_languages.info_line_storage')}</p>
              <p>{t('admin_languages.info_line_share')}</p>
              <p className="text-amber-700">
                <strong>{t('admin_languages.info_update_strong')}</strong> {t('admin_languages.info_update_body')}
              </p>
            </div>
          </div>
        </CardContent>
      </Card>

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

                  <div className="flex flex-wrap items-center gap-2 pt-1">
                    {!isDefault && (
                      <Link to={`/admin/translations?lang=${lang.code}`}>
                        <Button variant="outline" size="sm">
                          <Wand2 className="h-3.5 w-3.5" />
                          {t('admin_languages.translate_cta')}
                        </Button>
                      </Link>
                    )}
                    <Button variant="ghost" size="sm" onClick={() => handleExport(lang)} disabled={busyCode === lang.code}>
                      <Download className="h-3.5 w-3.5" />
                      {t('admin_languages.export')}
                    </Button>
                    {!isDefault && (
                      <Button variant="ghost" size="sm" onClick={() => setImportFor(lang)}>
                        <Upload className="h-3.5 w-3.5" />
                        {t('admin_languages.import')}
                      </Button>
                    )}
                    {!isDefault && (
                      <Button
                        variant="ghost"
                        size="sm"
                        className="text-red-600 hover:text-red-700 ml-auto"
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

      {/* Import dialog */}
      {importFor && (
        <ImportLanguageDialog
          targetCode={importFor === 'new' ? null : importFor.code}
          onClose={() => setImportFor(null)}
          onDone={async () => {
            setImportFor(null)
            await reload()
            await reloadPublic()
          }}
          importPack={importLanguagePack}
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

function ImportLanguageDialog({
  targetCode,
  onClose,
  onDone,
  importPack,
}: {
  targetCode: string | null
  onClose: () => void
  onDone: () => Promise<void>
  importPack: (body: { payload: Record<string, unknown>; mode: ImportMode; dryRun: boolean }) => Promise<ImportPreview>
}) {
  const { t } = useTranslation()
  const fileRef = useRef<HTMLInputElement>(null)
  const [payload, setPayload] = useState<Record<string, unknown> | null>(null)
  const [filename, setFilename] = useState<string | null>(null)
  const [parseError, setParseError] = useState<string | null>(null)
  const [mode, setMode] = useState<ImportMode>('fill_missing')
  const [preview, setPreview] = useState<ImportPreview | null>(null)
  const [loadingPreview, setLoadingPreview] = useState(false)
  const [applying, setApplying] = useState(false)

  const onFilePick = async (file: File) => {
    setFilename(file.name)
    setParseError(null)
    setPreview(null)
    try {
      const text = await file.text()
      const parsed = JSON.parse(text)
      if (!parsed?.imaginaAudit || !parsed?.lang || !parsed?.namespaces) {
        setParseError(t('admin_languages.import_invalid_file'))
        setPayload(null)
        return
      }
      if (targetCode && parsed.lang !== targetCode) {
        setParseError(t('admin_languages.import_lang_mismatch', { file: parsed.lang, target: targetCode }))
        setPayload(null)
        return
      }
      setPayload(parsed)
    } catch {
      setParseError(t('admin_languages.import_parse_error'))
      setPayload(null)
    }
  }

  // Cada vez que cambia payload o mode, pedimos un nuevo preview.
  useEffect(() => {
    if (!payload) { setPreview(null); return }
    let cancelled = false
    ;(async () => {
      setLoadingPreview(true)
      try {
        const res = await importPack({ payload, mode, dryRun: true })
        if (!cancelled) setPreview(res)
      } catch (err: unknown) {
        if (!cancelled) {
          const axiosErr = err as { response?: { data?: { error?: string } } }
          toast.error(axiosErr.response?.data?.error ?? t('admin_languages.toast_import_error'))
        }
      }
      if (!cancelled) setLoadingPreview(false)
    })()
    return () => { cancelled = true }
  }, [payload, mode, importPack, t])

  const apply = async () => {
    if (!payload) return
    setApplying(true)
    try {
      const res = await importPack({ payload, mode, dryRun: false })
      toast.success(t('admin_languages.toast_imported', {
        added: res.added ?? 0,
        changed: res.changed ?? 0,
        skipped: res.skipped ?? 0,
      }))
      await onDone()
    } catch (err: unknown) {
      const axiosErr = err as { response?: { data?: { error?: string } } }
      toast.error(axiosErr.response?.data?.error ?? t('admin_languages.toast_import_error'))
    }
    setApplying(false)
  }

  return (
    <Dialog open onOpenChange={(open) => !open && onClose()}>
      <DialogContent className="max-w-3xl max-h-[90vh] overflow-y-auto">
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2">
            <Upload className="h-5 w-5 text-[var(--accent-primary)]" />
            {targetCode
              ? t('admin_languages.import_title_for', { code: targetCode.toUpperCase() })
              : t('admin_languages.import_title_new')}
          </DialogTitle>
        </DialogHeader>

        <div className="space-y-4">
          {/* File picker */}
          <div>
            <Label>{t('admin_languages.import_file_label')}</Label>
            <label className="flex cursor-pointer items-center gap-3 mt-1 rounded-lg border-2 border-dashed border-[var(--border-default)] bg-white px-4 py-4 transition-colors hover:border-[var(--accent-primary)]">
              <Upload className="h-4 w-4 text-[var(--text-tertiary)]" />
              <div className="flex-1 text-xs">
                {filename ? (
                  <span className="text-emerald-700 font-medium">{filename}</span>
                ) : (
                  <span className="text-[var(--text-tertiary)]">{t('admin_languages.import_file_hint')}</span>
                )}
              </div>
              <input
                ref={fileRef}
                type="file"
                accept="application/json,.json"
                onChange={(e) => {
                  const f = e.target.files?.[0]
                  if (f) onFilePick(f)
                }}
                className="hidden"
              />
            </label>
            {parseError && (
              <p className="mt-2 text-[11px] text-red-600">{parseError}</p>
            )}
          </div>

          {payload && !parseError && (
            <>
              {/* Mode selector */}
              <div className="space-y-2">
                <Label>{t('admin_languages.import_mode_label')}</Label>
                <div className="space-y-1.5">
                  {([
                    ['fill_missing', 'import_mode_fill_title', 'import_mode_fill_body'],
                    ['smart_merge', 'import_mode_smart_title', 'import_mode_smart_body'],
                    ['replace_all', 'import_mode_replace_title', 'import_mode_replace_body'],
                  ] as const).map(([value, title, body]) => (
                    <label
                      key={value}
                      className={`flex cursor-pointer items-start gap-2 rounded-lg border px-3 py-2 transition-colors ${
                        mode === value
                          ? 'border-[var(--accent-primary)] bg-[var(--accent-primary)]/5'
                          : 'border-[var(--border-default)] hover:bg-[var(--bg-secondary)]'
                      }`}
                    >
                      <input
                        type="radio"
                        name="import-mode"
                        value={value}
                        checked={mode === value}
                        onChange={() => setMode(value)}
                        className="mt-1"
                      />
                      <div className="flex-1 text-xs">
                        <p className="font-semibold text-[var(--text-primary)]">{t(`admin_languages.${title}`)}</p>
                        <p className="text-[var(--text-tertiary)]">{t(`admin_languages.${body}`)}</p>
                      </div>
                    </label>
                  ))}
                </div>
              </div>

              {/* Preview */}
              <div className="space-y-2">
                <Label>{t('admin_languages.import_preview_label')}</Label>
                {loadingPreview ? (
                  <div className="flex items-center gap-2 text-xs text-[var(--text-tertiary)]">
                    <Loader2 className="h-3.5 w-3.5 animate-spin" />
                    {t('admin_languages.import_preview_loading')}
                  </div>
                ) : preview ? (
                  <>
                    <div className="flex flex-wrap gap-2 text-[11px]">
                      <Badge variant="secondary">{t('admin_languages.import_stat_in_pack', { count: preview.totalInPack ?? 0 })}</Badge>
                      <Badge variant="success">{t('admin_languages.import_stat_add', { count: preview.willAdd ?? 0 })}</Badge>
                      <Badge variant="warning">{t('admin_languages.import_stat_change', { count: preview.willChange ?? 0 })}</Badge>
                      <Badge variant="outline">{t('admin_languages.import_stat_skip', { count: preview.willSkip ?? 0 })}</Badge>
                    </div>
                    {preview.languageCreated && (
                      <p className="text-[11px] text-amber-700">{t('admin_languages.import_will_create_lang', { code: preview.lang })}</p>
                    )}
                    {preview.changes && preview.changes.length > 0 ? (
                      <div className="max-h-64 overflow-y-auto rounded-md border border-[var(--border-default)] divide-y divide-[var(--border-default)] text-[11px]">
                        {preview.changes.map((c, i) => (
                          <div key={i} className="px-2 py-1.5 flex items-start gap-2">
                            <Badge
                              variant={c.action === 'add' ? 'success' : c.action === 'change' ? 'warning' : 'outline'}
                              className="text-[9px] shrink-0"
                            >
                              {t(`admin_languages.import_action_${c.action}`)}
                            </Badge>
                            <div className="flex-1 min-w-0">
                              <p className="font-mono text-[10px] text-[var(--text-tertiary)] truncate">
                                [{c.namespace}] {c.key}
                              </p>
                              {c.action === 'change' && (
                                <p className="text-[10px]">
                                  <span className="text-red-600 line-through">{c.currentValue}</span>{' '}
                                  → <span className="text-emerald-700">{c.incomingValue}</span>
                                </p>
                              )}
                              {c.action === 'add' && (
                                <p className="text-[10px] text-emerald-700 truncate">{c.incomingValue}</p>
                              )}
                              {c.action === 'skip' && (
                                <p className="text-[10px] text-[var(--text-tertiary)] italic">
                                  {c.reason === 'reviewed'
                                    ? t('admin_languages.import_skip_reviewed')
                                    : t('admin_languages.import_skip_override')}
                                </p>
                              )}
                            </div>
                          </div>
                        ))}
                      </div>
                    ) : (
                      <p className="text-[11px] text-[var(--text-tertiary)]">{t('admin_languages.import_no_changes')}</p>
                    )}
                    {preview.truncated && (
                      <p className="text-[10px] italic text-[var(--text-tertiary)]">{t('admin_languages.import_preview_truncated')}</p>
                    )}
                  </>
                ) : null}
              </div>
            </>
          )}
        </div>

        <DialogFooter>
          <Button variant="ghost" onClick={onClose} disabled={applying}>{t('common.cancel')}</Button>
          <Button
            onClick={apply}
            disabled={!payload || !!parseError || applying || loadingPreview}
          >
            {applying ? <Loader2 className="h-4 w-4 animate-spin" /> : <Upload className="h-4 w-4" />}
            {t('admin_languages.import_apply')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
