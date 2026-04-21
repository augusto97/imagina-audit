import { useEffect, useState, useCallback, useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { Loader2, Languages, RotateCcw, Wand2, CheckCheck, AlertTriangle, Save } from 'lucide-react'
import { toast } from 'sonner'
import { Card, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { Label } from '@/components/ui/label'
import { Badge } from '@/components/ui/badge'
import { Skeleton } from '@/components/ui/skeleton'
import { Select, SelectTrigger, SelectValue, SelectContent, SelectItem } from '@/components/ui/select'
import { useAdmin } from '@/hooks/useAdmin'
import { LANGUAGE_NAMES, type SupportedLanguage } from '@/i18n'

interface TranslationItem {
  key: string
  value: string
  defaultValue: string | null
  sourceValue: string | null
  overridden: boolean
  source: 'manual' | 'ai' | 'import' | null
  aiProvider: string | null
  reviewed: boolean
  updatedAt: string | null
}

type Provider = 'chatgpt' | 'claude' | 'google'

/**
 * Página /admin/translations — editor de strings localizados.
 *
 * Flujo:
 *   1. El admin elige (lang, namespace).
 *   2. Se muestran todas las keys del bundle: el texto fuente (inglés)
 *      a la izquierda, el valor actual editable a la derecha.
 *   3. Badges muestran el estado: overridden | default | AI-generated
 *      (no revisado) | AI-generated (revisado).
 *   4. Por fila: Save (guarda manual), Revert (borra override), AI
 *      (traduce esa key con el provider elegido arriba).
 *   5. Bulk: "Translate all missing" genera AI para las filas que aún
 *      tienen el valor del default.
 */
export default function SettingsTranslations() {
  const { t } = useTranslation()
  const { fetchTranslationsMeta, fetchTranslations, updateTranslation, deleteTranslation, aiTranslate } = useAdmin()

  const [meta, setMeta] = useState<{ namespaces: string[]; languages: string[]; defaultLang: string } | null>(null)
  const [targetLang, setTargetLang] = useState<SupportedLanguage>('es')
  const [namespace, setNamespace] = useState<string>('')
  const [items, setItems] = useState<TranslationItem[]>([])
  const [loading, setLoading] = useState(true)
  const [filter, setFilter] = useState<'all' | 'missing' | 'overridden' | 'unreviewed'>('all')
  const [provider, setProvider] = useState<Provider>('claude')
  const [search, setSearch] = useState('')
  const [busyKey, setBusyKey] = useState<string | null>(null)
  const [bulkBusy, setBulkBusy] = useState(false)
  const [dirty, setDirty] = useState<Record<string, string>>({})

  const load = useCallback(async () => {
    if (!namespace || !targetLang) return
    setLoading(true)
    const data = await fetchTranslations(targetLang, namespace)
    if (data) {
      setItems(data.items)
      setDirty({})
    }
    setLoading(false)
  }, [namespace, targetLang, fetchTranslations])

  useEffect(() => {
    fetchTranslationsMeta().then((m) => {
      if (!m) return
      setMeta(m)
      if (m.namespaces.length && !namespace) setNamespace(m.namespaces[0])
    })
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

  useEffect(() => { load() }, [load])

  const filtered = useMemo(() => {
    let list = items
    if (search) {
      const q = search.toLowerCase()
      list = list.filter(it =>
        it.key.toLowerCase().includes(q) ||
        (it.sourceValue ?? '').toLowerCase().includes(q) ||
        (it.value ?? '').toLowerCase().includes(q)
      )
    }
    if (filter === 'missing') {
      // no override y el target lang no tiene bundle propio (defaultValue null) — o
      // el override es AI no revisado
      list = list.filter(it => !it.overridden || (it.source === 'ai' && !it.reviewed))
    } else if (filter === 'overridden') {
      list = list.filter(it => it.overridden)
    } else if (filter === 'unreviewed') {
      list = list.filter(it => it.source === 'ai' && !it.reviewed)
    }
    return list
  }, [items, search, filter])

  const stats = useMemo(() => {
    const overridden = items.filter(i => i.overridden).length
    const ai = items.filter(i => i.source === 'ai').length
    const unreviewed = items.filter(i => i.source === 'ai' && !i.reviewed).length
    return { total: items.length, overridden, ai, unreviewed }
  }, [items])

  const save = async (item: TranslationItem) => {
    const newValue = dirty[item.key] ?? item.value
    if (newValue === item.value && item.overridden) return
    setBusyKey(item.key)
    await updateTranslation(targetLang, namespace, item.key, newValue, { source: 'manual', reviewed: true })
    toast.success(t('settings.trans_saved'))
    setBusyKey(null)
    await load()
  }

  const revert = async (item: TranslationItem) => {
    if (!item.overridden) return
    setBusyKey(item.key)
    await deleteTranslation(targetLang, namespace, item.key)
    toast.success(t('settings.trans_reverted'))
    setBusyKey(null)
    await load()
  }

  const markReviewed = async (item: TranslationItem) => {
    setBusyKey(item.key)
    await updateTranslation(targetLang, namespace, item.key, item.value, { source: item.source ?? 'manual', aiProvider: item.aiProvider, reviewed: true })
    toast.success(t('settings.trans_marked_reviewed'))
    setBusyKey(null)
    await load()
  }

  const aiTranslateOne = async (item: TranslationItem) => {
    const sourceText = item.sourceValue ?? item.defaultValue
    if (!sourceText) {
      toast.error(t('settings.trans_no_source'))
      return
    }
    setBusyKey(item.key)
    try {
      const res = await aiTranslate({
        provider,
        sourceLang: meta?.defaultLang ?? 'en',
        targetLang,
        namespace,
        items: [{ key: item.key, text: sourceText, context: item.key }],
        persist: true,
      })
      if (res.okCount === 0) {
        toast.error(res.translations[0]?.error ?? t('settings.trans_ai_error'))
      } else {
        toast.success(t('settings.trans_ai_done', { provider: res.providerName }))
      }
    } catch (e) {
      const msg = (e as { response?: { data?: { error?: string } } })?.response?.data?.error ?? t('settings.trans_ai_error')
      toast.error(msg)
    }
    setBusyKey(null)
    await load()
  }

  const aiTranslateAll = async () => {
    const toTranslate = items.filter(it => !it.overridden && it.sourceValue)
    if (toTranslate.length === 0) {
      toast.info(t('settings.trans_nothing_missing'))
      return
    }
    if (!confirm(t('settings.trans_bulk_confirm', { count: toTranslate.length, provider }))) return

    setBulkBusy(true)
    try {
      const res = await aiTranslate({
        provider,
        sourceLang: meta?.defaultLang ?? 'en',
        targetLang,
        namespace,
        items: toTranslate.map(it => ({ key: it.key, text: it.sourceValue ?? '', context: it.key })),
        persist: true,
      })
      toast.success(t('settings.trans_bulk_done', { ok: res.okCount, fail: res.errorCount, provider: res.providerName }))
    } catch (e) {
      const msg = (e as { response?: { data?: { error?: string } } })?.response?.data?.error ?? t('settings.trans_ai_error')
      toast.error(msg)
    }
    setBulkBusy(false)
    await load()
  }

  if (!meta) return <Skeleton className="h-96 rounded-2xl" />

  return (
    <div className="space-y-5">
      <div>
        <h1 className="text-2xl font-bold text-[var(--text-primary)] flex items-center gap-2">
          <Languages className="h-6 w-6 text-[var(--accent-primary)]" /> {t('settings.trans_title')}
        </h1>
        <p className="text-sm text-[var(--text-secondary)] mt-1">{t('settings.trans_subtitle')}</p>
      </div>

      {/* Header controls */}
      <Card>
        <CardContent className="pt-5 space-y-4">
          <div className="flex flex-wrap gap-3 items-end">
            <div className="space-y-1">
              <Label className="text-xs">{t('settings.trans_target_lang')}</Label>
              <Select value={targetLang} onValueChange={(v) => setTargetLang(v as SupportedLanguage)}>
                <SelectTrigger className="w-[180px]"><SelectValue /></SelectTrigger>
                <SelectContent>
                  {meta.languages.filter(l => l !== meta.defaultLang).map(lang => (
                    <SelectItem key={lang} value={lang}>{LANGUAGE_NAMES[lang as SupportedLanguage] ?? lang}</SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <div className="space-y-1">
              <Label className="text-xs">{t('settings.trans_namespace')}</Label>
              <Select value={namespace} onValueChange={setNamespace}>
                <SelectTrigger className="w-[200px]"><SelectValue /></SelectTrigger>
                <SelectContent>
                  {meta.namespaces.map(ns => <SelectItem key={ns} value={ns}>{ns}</SelectItem>)}
                </SelectContent>
              </Select>
            </div>
            <div className="space-y-1">
              <Label className="text-xs">{t('settings.trans_provider')}</Label>
              <Select value={provider} onValueChange={(v) => setProvider(v as Provider)}>
                <SelectTrigger className="w-[180px]"><SelectValue /></SelectTrigger>
                <SelectContent>
                  <SelectItem value="claude">Claude</SelectItem>
                  <SelectItem value="chatgpt">ChatGPT</SelectItem>
                  <SelectItem value="google">Google Translate</SelectItem>
                </SelectContent>
              </Select>
            </div>
            <Button onClick={aiTranslateAll} disabled={bulkBusy}>
              {bulkBusy ? <Loader2 className="h-4 w-4 animate-spin" /> : <Wand2 className="h-4 w-4" />}
              {t('settings.trans_translate_missing')}
            </Button>
          </div>

          {/* Stats */}
          <div className="flex flex-wrap gap-2 text-xs">
            <Badge variant="secondary">{t('settings.trans_stat_total', { count: stats.total })}</Badge>
            <Badge variant="secondary">{t('settings.trans_stat_overridden', { count: stats.overridden })}</Badge>
            {stats.ai > 0 && <Badge variant="warning">{t('settings.trans_stat_ai', { count: stats.ai })}</Badge>}
            {stats.unreviewed > 0 && <Badge variant="destructive">{t('settings.trans_stat_unreviewed', { count: stats.unreviewed })}</Badge>}
          </div>

          {/* Filters */}
          <div className="flex flex-wrap items-center gap-2">
            <Input
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              placeholder={t('settings.trans_search_placeholder')}
              className="max-w-xs h-8 text-xs"
            />
            <div className="flex gap-1">
              {([
                ['all',         t('settings.trans_filter_all')],
                ['missing',     t('settings.trans_filter_missing')],
                ['overridden',  t('settings.trans_filter_overridden')],
                ['unreviewed',  t('settings.trans_filter_unreviewed')],
              ] as const).map(([v, label]) => (
                <button
                  key={v}
                  type="button"
                  onClick={() => setFilter(v)}
                  className={`rounded-full px-2.5 py-1 text-[11px] font-medium transition-colors ${
                    filter === v
                      ? 'bg-[var(--accent-primary)] text-white'
                      : 'bg-[var(--bg-secondary)] text-[var(--text-secondary)] hover:bg-[var(--border-default)]'
                  }`}
                >
                  {label}
                </button>
              ))}
            </div>
          </div>
        </CardContent>
      </Card>

      {/* Grid editable */}
      {loading ? (
        <Skeleton className="h-[500px] rounded-2xl" />
      ) : filtered.length === 0 ? (
        <Card><CardContent className="py-12 text-center text-sm text-[var(--text-tertiary)]">{t('settings.trans_empty')}</CardContent></Card>
      ) : (
        <div className="space-y-2">
          {filtered.map((item) => (
            <TranslationRow
              key={item.key}
              item={item}
              dirtyValue={dirty[item.key]}
              busy={busyKey === item.key}
              onChange={(v) => setDirty(d => ({ ...d, [item.key]: v }))}
              onSave={() => save(item)}
              onRevert={() => revert(item)}
              onReviewed={() => markReviewed(item)}
              onAi={() => aiTranslateOne(item)}
            />
          ))}
        </div>
      )}
    </div>
  )
}

function TranslationRow({
  item, dirtyValue, busy, onChange, onSave, onRevert, onReviewed, onAi,
}: {
  item: TranslationItem
  dirtyValue: string | undefined
  busy: boolean
  onChange: (v: string) => void
  onSave: () => void
  onRevert: () => void
  onReviewed: () => void
  onAi: () => void
}) {
  const { t } = useTranslation()
  const value = dirtyValue ?? item.value
  const hasEdit = dirtyValue !== undefined && dirtyValue !== item.value
  const isMultiline = (item.sourceValue ?? item.value ?? '').length > 60 || (item.sourceValue ?? item.value ?? '').includes('\n')

  return (
    <Card className={item.source === 'ai' && !item.reviewed ? 'border-amber-300 bg-amber-50/30' : ''}>
      <CardContent className="pt-4 pb-4 space-y-2">
        <div className="flex items-start justify-between gap-2 flex-wrap">
          <div className="flex items-center gap-2 min-w-0">
            <code className="text-[11px] font-mono text-[var(--text-tertiary)] bg-[var(--bg-secondary)] px-1.5 py-0.5 rounded truncate max-w-xs">{item.key}</code>
            {item.overridden ? (
              <Badge variant={item.source === 'ai' && !item.reviewed ? 'warning' : 'secondary'} className="text-[9px]">
                {item.source === 'ai'
                  ? (item.reviewed ? t('settings.trans_badge_ai_reviewed') : t('settings.trans_badge_ai'))
                  : t('settings.trans_badge_manual')}
                {item.aiProvider ? ` · ${item.aiProvider}` : ''}
              </Badge>
            ) : (
              <Badge variant="outline" className="text-[9px]">{t('settings.trans_badge_default')}</Badge>
            )}
          </div>
          <div className="flex gap-1">
            {item.source === 'ai' && !item.reviewed && (
              <Button size="sm" variant="ghost" className="h-7 text-xs" onClick={onReviewed} disabled={busy}>
                <CheckCheck className="h-3.5 w-3.5" /> {t('settings.trans_mark_reviewed')}
              </Button>
            )}
            <Button size="sm" variant="outline" className="h-7 text-xs" onClick={onAi} disabled={busy}>
              {busy ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : <Wand2 className="h-3.5 w-3.5" />}
              {t('settings.trans_ai_button')}
            </Button>
            {item.overridden && (
              <Button size="sm" variant="ghost" className="h-7 text-xs" onClick={onRevert} disabled={busy}>
                <RotateCcw className="h-3.5 w-3.5" /> {t('settings.trans_revert')}
              </Button>
            )}
            <Button size="sm" className="h-7 text-xs" onClick={onSave} disabled={busy || !hasEdit}>
              {busy ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : <Save className="h-3.5 w-3.5" />}
              {t('settings.trans_save')}
            </Button>
          </div>
        </div>

        {item.sourceValue !== null && (
          <div className="text-xs text-[var(--text-tertiary)]">
            <span className="font-semibold uppercase tracking-wider text-[10px]">{t('settings.trans_source_label')}:</span>{' '}
            <span className="font-normal">{item.sourceValue}</span>
          </div>
        )}

        {isMultiline ? (
          <Textarea value={value} onChange={(e) => onChange(e.target.value)} rows={2} className="text-sm" />
        ) : (
          <Input value={value} onChange={(e) => onChange(e.target.value)} className="text-sm" />
        )}

        {item.source === 'ai' && !item.reviewed && (
          <p className="text-[11px] text-amber-700 flex items-center gap-1">
            <AlertTriangle className="h-3 w-3" />
            {t('settings.trans_ai_warning')}
          </p>
        )}
      </CardContent>
    </Card>
  )
}
