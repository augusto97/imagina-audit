import { useEffect, useState, useCallback, useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { Loader2, Save, ChevronDown, ChevronRight, AlertTriangle, TrendingDown, TrendingUp, Eye, EyeOff, RefreshCw } from 'lucide-react'
import { toast } from 'sonner'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Badge } from '@/components/ui/badge'
import { Switch } from '@/components/ui/switch'
import { Skeleton } from '@/components/ui/skeleton'
import { useAdmin } from '@/hooks/useAdmin'
import { ModuleIcon } from './ModuleIcon'

type Config = Record<string, unknown>

interface CatalogModule {
  name: string
  metrics: Array<{ id: string; name: string }>
}
interface Catalog {
  modules: Record<string, CatalogModule>
}
interface PreviewAudit {
  id: string; url: string; domain: string
  currentScore: number; currentLevel: string
}
interface PreviewResult {
  previousGlobal: number; previousLevel: string
  newGlobal: number; newLevel: string
  moduleScores: Array<{ id: string; name: string; score: number; level: string }>
}

/**
 * /admin/scoring — control fino del scoring de auditorías.
 *
 * Bloques (de arriba abajo):
 *  1. Umbrales global (Excellent/Good/Warning/Critical)
 *  2. Pesos por módulo + toggle "Incluye en global"
 *  3. Cap por módulo cuando hay críticos
 *  4. Curva exponencial de penalización por críticos totales
 *  5. Por módulo: lista de métricas con toggle (cuenta sí/no) + peso individual
 *  6. Preview en vivo (re-evaluando el último audit con la config propuesta)
 */
export default function SettingsScoring() {
  const { t } = useTranslation()
  const { fetchScoring, previewScoring, saveScoring } = useAdmin()

  const [catalog, setCatalog] = useState<Catalog | null>(null)
  const [config, setConfig] = useState<Config>({})
  const [previewAudit, setPreviewAudit] = useState<PreviewAudit | null>(null)
  const [preview, setPreview] = useState<PreviewResult | null>(null)
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [previewing, setPreviewing] = useState(false)
  const [openModules, setOpenModules] = useState<Record<string, boolean>>({})

  const reload = useCallback(async () => {
    setLoading(true)
    const data = await fetchScoring()
    if (data) {
      setCatalog(data.catalog)
      setConfig(data.config)
      setPreviewAudit(data.previewAudit)
    }
    setLoading(false)
  }, [fetchScoring])

  useEffect(() => { reload() }, [reload])

  // Pesos de módulos
  const moduleWeights = useMemo(() => {
    const out: Record<string, number> = {}
    for (const [k, v] of Object.entries(config)) {
      if (k.startsWith('weight_')) out[k.replace('weight_', '')] = Number(v)
    }
    return out
  }, [config])
  const totalWeight = Object.values(moduleWeights).reduce((s, v) => s + v, 0)

  const disabledModules = (config.scoring_disabled_modules as string[]) || []
  const disabledMetrics = (config.scoring_disabled_metrics as string[]) || []
  const metricWeights = (config.scoring_metric_weights as Record<string, number>) || {}
  const capPerModule = (config.scoring_critical_cap_per_module as Record<string, number>) || {}
  const penalties = (config.scoring_critical_penalties as number[]) || [0, 3, 8, 15, 25]

  const setConfigKey = (k: string, v: unknown) => setConfig(prev => ({ ...prev, [k]: v }))

  const toggleModule = (id: string) => setOpenModules(prev => ({ ...prev, [id]: !prev[id] }))

  const toggleModuleEnabled = (id: string) => {
    const next = disabledModules.includes(id)
      ? disabledModules.filter(x => x !== id)
      : [...disabledModules, id]
    setConfigKey('scoring_disabled_modules', next)
  }

  const toggleMetricEnabled = (fullId: string) => {
    const next = disabledMetrics.includes(fullId)
      ? disabledMetrics.filter(x => x !== fullId)
      : [...disabledMetrics, fullId]
    setConfigKey('scoring_disabled_metrics', next)
  }

  const setMetricWeight = (fullId: string, w: number) => {
    const next = { ...metricWeights, [fullId]: w }
    if (w === 1) delete next[fullId] // 1.0 es el default, no hace falta override
    setConfigKey('scoring_metric_weights', next)
  }

  const setModuleCap = (id: string, cap: number) => {
    setConfigKey('scoring_critical_cap_per_module', { ...capPerModule, [id]: cap })
  }

  const setPenalty = (idx: number, val: number) => {
    const next = [...penalties]
    next[idx] = val
    setConfigKey('scoring_critical_penalties', next)
  }

  const runPreview = useCallback(async () => {
    if (!previewAudit) return
    setPreviewing(true)
    try {
      const res = await previewScoring(previewAudit.id, config)
      setPreview(res)
    } catch (err: unknown) {
      const e = err as { response?: { data?: { error?: string } } }
      toast.error(e.response?.data?.error ?? 'Preview falló')
    }
    setPreviewing(false)
  }, [previewAudit, config, previewScoring])

  const save = async () => {
    setSaving(true)
    try {
      await saveScoring(config)
      toast.success(t('settings.scoring_saved'))
      setPreview(null) // limpiar preview tras guardar
    } catch (err: unknown) {
      const e = err as { response?: { data?: { error?: string } } }
      toast.error(e.response?.data?.error ?? t('settings.save_error'))
    }
    setSaving(false)
  }

  if (loading || !catalog) return <Skeleton className="h-96 rounded-2xl" />

  const moduleIds = Object.keys(catalog.modules)
  const isSumValid = Math.abs(totalWeight - 1.0) < 0.02

  return (
    <div className="space-y-6">
      <div className="flex items-start justify-between gap-4 flex-wrap">
        <div>
          <h1 className="text-2xl font-bold text-[var(--text-primary)]">{t('settings.scoring_title')}</h1>
          <p className="text-sm text-[var(--text-secondary)] mt-1">{t('settings.scoring_subtitle')}</p>
        </div>
        <Button onClick={save} disabled={saving}>
          {saving ? <Loader2 className="h-4 w-4 animate-spin" /> : <Save className="h-4 w-4" />}
          {t('settings.scoring_save')}
        </Button>
      </div>

      {/* ── Umbrales ───────────────────────────────────────────────── */}
      <Card>
        <CardHeader><CardTitle className="text-base">{t('settings.scoring_thresholds_card')}</CardTitle></CardHeader>
        <CardContent className="space-y-2">
          {(['excellent','good','warning','critical'] as const).map(level => (
            <div key={level} className="flex items-center gap-3">
              <Badge variant={level === 'critical' ? 'destructive' : level === 'warning' ? 'warning' : 'success'} className="w-28 justify-center">
                {t(`settings.scoring_level_${level}`)}
              </Badge>
              <span className="text-xs text-[var(--text-tertiary)]">{level === 'critical' ? '<' : '≥'}</span>
              <Input
                type="number" min={0} max={100} className="w-20"
                value={Number(config[`threshold_${level}`] ?? 0)}
                onChange={e => setConfigKey(`threshold_${level}`, parseInt(e.target.value) || 0)}
              />
            </div>
          ))}
        </CardContent>
      </Card>

      {/* ── Pesos por módulo + include toggle ─────────────────────── */}
      <Card>
        <CardHeader><CardTitle className="text-base">{t('settings.scoring_weights_card')}</CardTitle></CardHeader>
        <CardContent className="space-y-3">
          {moduleIds.map(id => {
            const weight = moduleWeights[id] ?? 0
            const enabled = !disabledModules.includes(id)
            return (
              <div key={id} className={`flex items-center gap-3 ${enabled ? '' : 'opacity-50'}`}>
                <button type="button" onClick={() => toggleModuleEnabled(id)} title={enabled ? 'Incluir' : 'Excluir'}>
                  {enabled ? <Eye className="h-4 w-4 text-emerald-600" /> : <EyeOff className="h-4 w-4 text-[var(--text-tertiary)]" />}
                </button>
                <Label className="w-40 flex items-center gap-2 text-sm">
                  <ModuleIcon id={id} className="h-4 w-4 text-[var(--text-secondary)]" />
                  {catalog.modules[id]?.name ?? id}
                </Label>
                <input
                  type="range" min={0} max={0.5} step={0.01} disabled={!enabled}
                  value={weight}
                  onChange={e => setConfigKey(`weight_${id}`, parseFloat(e.target.value))}
                  className="flex-1 accent-[var(--accent-primary)]"
                />
                <span className="w-12 text-right text-xs font-mono text-[var(--text-secondary)]">{weight.toFixed(2)}</span>
              </div>
            )
          })}
          <div className="pt-3 border-t border-[var(--border-default)]">
            <Badge variant={isSumValid ? 'success' : 'warning'}>
              {isSumValid
                ? t('settings.scoring_sum_ok', { total: totalWeight.toFixed(2) })
                : t('settings.scoring_sum_bad', { total: totalWeight.toFixed(2) })}
            </Badge>
          </div>
        </CardContent>
      </Card>

      {/* ── Cap por módulo cuando hay críticos ────────────────────── */}
      <Card>
        <CardHeader>
          <CardTitle className="text-base flex items-center gap-2">
            <AlertTriangle className="h-4 w-4 text-amber-600" />
            {t('settings.scoring_cap_card')}
          </CardTitle>
        </CardHeader>
        <CardContent className="space-y-3">
          <div className="flex items-center justify-between text-xs text-[var(--text-secondary)] mb-2">
            <span>{t('settings.scoring_cap_help')}</span>
            <div className="flex items-center gap-2">
              <span>{t('settings.scoring_cap_enabled')}</span>
              <Switch
                checked={!!config.scoring_critical_cap_enabled}
                onCheckedChange={v => setConfigKey('scoring_critical_cap_enabled', v)}
              />
            </div>
          </div>
          {moduleIds.map(id => {
            const cap = capPerModule[id] ?? 60
            return (
              <div key={id} className="flex items-center gap-3">
                <Label className="w-40 flex items-center gap-2 text-sm">
                  <ModuleIcon id={id} className="h-4 w-4 text-[var(--text-secondary)]" />
                  {catalog.modules[id]?.name ?? id}
                </Label>
                <input
                  type="range" min={0} max={100} step={5}
                  value={cap}
                  onChange={e => setModuleCap(id, parseInt(e.target.value))}
                  disabled={!config.scoring_critical_cap_enabled}
                  className="flex-1 accent-amber-500"
                />
                <span className="w-12 text-right text-xs font-mono text-amber-700">{cap}</span>
              </div>
            )
          })}
        </CardContent>
      </Card>

      {/* ── Curva de penalización ─────────────────────────────────── */}
      <Card>
        <CardHeader>
          <CardTitle className="text-base flex items-center gap-2">
            <TrendingDown className="h-4 w-4 text-red-600" />
            {t('settings.scoring_penalty_card')}
          </CardTitle>
        </CardHeader>
        <CardContent className="space-y-2">
          <div className="flex items-center justify-between text-xs text-[var(--text-secondary)] mb-2">
            <span>{t('settings.scoring_penalty_help')}</span>
            <Switch
              checked={!!config.scoring_critical_penalty_enabled}
              onCheckedChange={v => setConfigKey('scoring_critical_penalty_enabled', v)}
            />
          </div>
          <div className="grid grid-cols-5 gap-2">
            {[0, 1, 2, 3, 4].map(i => (
              <div key={i} className="flex flex-col items-center gap-1">
                <span className="text-[10px] text-[var(--text-tertiary)]">
                  {i === 4 ? '4+' : i} {t('settings.scoring_penalty_criticals')}
                </span>
                <Input
                  type="number" min={0} max={100} className="w-full text-center"
                  value={penalties[i] ?? 0}
                  onChange={e => setPenalty(i, parseInt(e.target.value) || 0)}
                  disabled={!config.scoring_critical_penalty_enabled}
                />
                <span className="text-[10px] text-red-600">-{penalties[i] ?? 0} pts</span>
              </div>
            ))}
          </div>
        </CardContent>
      </Card>

      {/* ── Por módulo: lista de métricas con toggle + peso ───────── */}
      <Card>
        <CardHeader><CardTitle className="text-base">{t('settings.scoring_metrics_card')}</CardTitle></CardHeader>
        <CardContent className="space-y-2">
          {moduleIds.map(id => {
            const mod = catalog.modules[id]
            const isOpen = openModules[id] ?? false
            const moduleDisabled = disabledMetrics.filter(x => x.startsWith(`${id}.`)).length
            return (
              <div key={id} className="border border-[var(--border-default)] rounded-lg">
                <button
                  type="button" onClick={() => toggleModule(id)}
                  className="w-full flex items-center gap-2 px-3 py-2 hover:bg-[var(--bg-secondary)] text-left"
                >
                  {isOpen ? <ChevronDown className="h-4 w-4" /> : <ChevronRight className="h-4 w-4" />}
                  <ModuleIcon id={id} className="h-4 w-4 text-[var(--text-secondary)]" />
                  <span className="font-semibold text-sm">{mod.name}</span>
                  <span className="text-xs text-[var(--text-tertiary)] ml-auto">
                    {mod.metrics.length} {t('settings.scoring_metrics_label')}
                    {moduleDisabled > 0 && (
                      <span className="ml-2 text-amber-700">· {moduleDisabled} {t('settings.scoring_metrics_disabled')}</span>
                    )}
                  </span>
                </button>
                {isOpen && (
                  <div className="border-t border-[var(--border-default)] divide-y divide-[var(--border-default)]">
                    {mod.metrics.map(m => {
                      const fullId = `${id}.${m.id}`
                      const enabled = !disabledMetrics.includes(fullId)
                      const weight = metricWeights[fullId] ?? 1.0
                      return (
                        <div key={m.id} className={`flex items-center gap-3 px-3 py-1.5 ${enabled ? '' : 'opacity-50'}`}>
                          <Switch checked={enabled} onCheckedChange={() => toggleMetricEnabled(fullId)} />
                          <div className="flex-1 min-w-0">
                            <p className="text-sm truncate">{m.name}</p>
                            <p className="text-[10px] font-mono text-[var(--text-tertiary)]">{fullId}</p>
                          </div>
                          <div className="flex items-center gap-1">
                            <span className="text-[10px] text-[var(--text-tertiary)]">{t('settings.scoring_weight_label')}</span>
                            <Input
                              type="number" min={0} max={5} step={0.5}
                              value={weight}
                              onChange={e => setMetricWeight(fullId, parseFloat(e.target.value) || 0)}
                              disabled={!enabled}
                              className="w-16 text-xs"
                            />
                          </div>
                        </div>
                      )
                    })}
                  </div>
                )}
              </div>
            )
          })}
        </CardContent>
      </Card>

      {/* ── Preview ───────────────────────────────────────────────── */}
      {previewAudit && (
        <Card className="border-blue-200 bg-blue-50/30">
          <CardHeader>
            <CardTitle className="text-base flex items-center gap-2">
              {t('settings.scoring_preview_card')}
            </CardTitle>
          </CardHeader>
          <CardContent className="space-y-3">
            <div className="text-xs text-[var(--text-secondary)]">
              <span className="font-semibold">{previewAudit.domain}</span>
              <span className="text-[var(--text-tertiary)] ml-2">audit del más reciente</span>
            </div>
            <Button variant="outline" size="sm" onClick={runPreview} disabled={previewing}>
              {previewing ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : <RefreshCw className="h-3.5 w-3.5" />}
              {t('settings.scoring_preview_run')}
            </Button>
            {preview && (
              <div className="space-y-2">
                <div className="flex items-center gap-3 text-sm">
                  <Badge variant="secondary">{t('settings.scoring_preview_now')}: {preview.previousGlobal} ({preview.previousLevel})</Badge>
                  {preview.newGlobal > preview.previousGlobal
                    ? <TrendingUp className="h-4 w-4 text-emerald-600" />
                    : preview.newGlobal < preview.previousGlobal
                    ? <TrendingDown className="h-4 w-4 text-red-600" />
                    : <span className="text-xs">=</span>}
                  <Badge variant={preview.newGlobal < 60 ? 'destructive' : preview.newGlobal < 80 ? 'warning' : 'success'}>
                    {t('settings.scoring_preview_after')}: {preview.newGlobal} ({preview.newLevel})
                  </Badge>
                </div>
                <div className="grid grid-cols-2 lg:grid-cols-3 gap-1 text-xs">
                  {preview.moduleScores.map(m => (
                    <div key={m.id} className="flex items-center justify-between rounded border border-[var(--border-default)] px-2 py-1 bg-white">
                      <span className="text-[var(--text-secondary)]">{m.name}</span>
                      <span className={`font-mono ${m.level === 'critical' ? 'text-red-600' : m.level === 'warning' ? 'text-amber-600' : 'text-emerald-700'}`}>
                        {m.score}
                      </span>
                    </div>
                  ))}
                </div>
              </div>
            )}
          </CardContent>
        </Card>
      )}
    </div>
  )
}
