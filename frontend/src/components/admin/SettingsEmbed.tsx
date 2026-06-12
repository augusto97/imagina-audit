import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Copy, Check, Code2, BadgeInfo, MousePointer, LayoutPanelTop } from 'lucide-react'
import { toast } from 'sonner'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { useConfigStore } from '@/store/configStore'

type Mode = 'floating' | 'inline'

/**
 * Generador de snippets para embeber el widget en sitios externos.
 * Cada cambio del formulario re-renderiza el snippet sin re-mandar al
 * backend — los settings de embed son por uso (sitio host), no globales,
 * así que no se persisten en la DB.
 *
 * Incluye guía corta de Google Ads / GTM porque ese es el caso de uso
 * principal (campañas) y la pregunta más frecuente.
 */
export default function SettingsEmbed() {
  const { t, i18n } = useTranslation()
  const { companyWhatsapp, brandPrimaryColor } = useConfigStore((s) => s.config)

  const [mode, setMode] = useState<Mode>('inline')
  const [color, setColor] = useState(brandPrimaryColor || '#0CC0DF')
  const [position, setPosition] = useState<'bottom-right' | 'bottom-left'>('bottom-right')
  const [lang, setLang] = useState((i18n.language || 'es').slice(0, 2))
  const [whatsapp, setWhatsapp] = useState(companyWhatsapp || '')
  const [targetId, setTargetId] = useState('imagina-audit-block')
  const [gtmEvent, setGtmEvent] = useState('imagina_audit_lead')
  const [gtagConv, setGtagConv] = useState('')

  const apiBase = window.location.origin + '/api'
  const widgetSrc = window.location.origin + '/widget/imagina-audit-widget.js'

  const attrs: Array<[string, string | undefined]> = [
    ['src', widgetSrc],
    ['data-api', apiBase],
    ['data-mode', mode],
    ['data-color', color],
    ['data-lang', lang],
    ['data-whatsapp', whatsapp || undefined],
    mode === 'floating' ? ['data-position', position] : ['data-target', '#' + targetId],
    gtmEvent ? ['data-gtm-event', gtmEvent] : ['data-gtm-event', undefined],
    gtagConv ? ['data-gtag-conversion', gtagConv] : ['data-gtag-conversion', undefined],
  ]

  const scriptTag = '<script\n  ' +
    attrs
      .filter(([, v]) => v !== undefined && v !== '')
      .map(([k, v]) => `${k}="${v}"`)
      .join('\n  ') +
    '>\n</script>' +
    (mode === 'inline' ? `\n<div id="${targetId}"></div>` : '')

  const [copied, setCopied] = useState(false)
  const copy = async () => {
    try {
      await navigator.clipboard.writeText(scriptTag)
      setCopied(true)
      setTimeout(() => setCopied(false), 2200)
      toast.success(t('common.copied'))
    } catch {
      toast.error(t('common.error'))
    }
  }

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-[var(--text-primary)] flex items-center gap-2">
          <Code2 className="h-6 w-6 text-[var(--accent-primary)]" /> {t('settings.embed_title')}
        </h1>
        <p className="mt-1 text-sm text-[var(--text-secondary)]">{t('settings.embed_subtitle')}</p>
      </div>

      {/* Selector de modo */}
      <Card>
        <CardHeader><CardTitle className="text-base">{t('settings.embed_mode_card')}</CardTitle></CardHeader>
        <CardContent className="grid gap-3 sm:grid-cols-2">
          <ModeOption
            active={mode === 'inline'}
            onClick={() => setMode('inline')}
            icon={<LayoutPanelTop className="h-5 w-5" />}
            title={t('settings.embed_mode_inline')}
            desc={t('settings.embed_mode_inline_desc')}
          />
          <ModeOption
            active={mode === 'floating'}
            onClick={() => setMode('floating')}
            icon={<MousePointer className="h-5 w-5" />}
            title={t('settings.embed_mode_floating')}
            desc={t('settings.embed_mode_floating_desc')}
          />
        </CardContent>
      </Card>

      {/* Estilo y opciones */}
      <Card>
        <CardHeader><CardTitle className="text-base">{t('settings.embed_style_card')}</CardTitle></CardHeader>
        <CardContent className="grid gap-4 sm:grid-cols-2">
          <div>
            <Label className="text-xs">{t('settings.embed_color')}</Label>
            <div className="mt-1 flex gap-2">
              <input type="color" value={color} onChange={(e) => setColor(e.target.value)} className="h-10 w-12 rounded border border-[var(--border-default)] bg-white" />
              <Input value={color} onChange={(e) => setColor(e.target.value)} className="flex-1 font-mono text-sm" />
            </div>
          </div>
          <div>
            <Label className="text-xs">{t('settings.embed_lang')}</Label>
            <select value={lang} onChange={(e) => setLang(e.target.value)} className="mt-1 h-10 w-full rounded-lg border border-[var(--border-default)] bg-white px-3 text-sm">
              <option value="es">Español (es)</option>
              <option value="en">English (en)</option>
            </select>
          </div>
          {mode === 'floating' && (
            <div>
              <Label className="text-xs">{t('settings.embed_position')}</Label>
              <select value={position} onChange={(e) => setPosition(e.target.value as 'bottom-right' | 'bottom-left')} className="mt-1 h-10 w-full rounded-lg border border-[var(--border-default)] bg-white px-3 text-sm">
                <option value="bottom-right">{t('settings.embed_pos_br')}</option>
                <option value="bottom-left">{t('settings.embed_pos_bl')}</option>
              </select>
            </div>
          )}
          {mode === 'inline' && (
            <div>
              <Label className="text-xs">{t('settings.embed_target_id')}</Label>
              <Input value={targetId} onChange={(e) => setTargetId(e.target.value.replace(/[^a-zA-Z0-9_-]/g, ''))} className="mt-1 font-mono text-sm" />
              <p className="mt-1 text-[11px] text-[var(--text-tertiary)]">{t('settings.embed_target_id_hint')}</p>
            </div>
          )}
          <div className={mode === 'inline' ? '' : 'sm:col-span-2'}>
            <Label className="text-xs">{t('settings.embed_whatsapp')}</Label>
            <Input value={whatsapp} onChange={(e) => setWhatsapp(e.target.value)} placeholder="+573001234567" className="mt-1" />
            <p className="mt-1 text-[11px] text-[var(--text-tertiary)]">{t('settings.embed_whatsapp_hint')}</p>
          </div>
        </CardContent>
      </Card>

      {/* Google Ads / GTM */}
      <Card className="border-amber-200 bg-amber-50/30">
        <CardHeader>
          <CardTitle className="text-base flex items-center gap-2">
            <BadgeInfo className="h-5 w-5 text-amber-600" />
            {t('settings.embed_ads_card')}
          </CardTitle>
        </CardHeader>
        <CardContent className="space-y-4">
          <p className="text-sm text-[var(--text-secondary)]">{t('settings.embed_ads_intro')}</p>

          <div className="grid gap-4 sm:grid-cols-2">
            <div>
              <Label className="text-xs">{t('settings.embed_gtm_event')}</Label>
              <Input value={gtmEvent} onChange={(e) => setGtmEvent(e.target.value)} className="mt-1 font-mono text-sm" placeholder="imagina_audit_lead" />
              <p className="mt-1 text-[11px] text-[var(--text-tertiary)]">{t('settings.embed_gtm_event_hint')}</p>
            </div>
            <div>
              <Label className="text-xs">{t('settings.embed_gtag_conv')}</Label>
              <Input value={gtagConv} onChange={(e) => setGtagConv(e.target.value)} className="mt-1 font-mono text-sm" placeholder="AW-1234567890/AbC-D_efG" />
              <p className="mt-1 text-[11px] text-[var(--text-tertiary)]">{t('settings.embed_gtag_conv_hint')}</p>
            </div>
          </div>

          <details className="rounded-lg border border-amber-200 bg-white p-3">
            <summary className="cursor-pointer text-sm font-medium text-[var(--text-primary)]">{t('settings.embed_ads_help_summary')}</summary>
            <div className="mt-3 space-y-2 text-xs text-[var(--text-secondary)] leading-relaxed">
              <p><b>{t('settings.embed_ads_when')}</b> {t('settings.embed_ads_when_desc')}</p>
              <p><b>{t('settings.embed_ads_payload')}</b> <code className="rounded bg-[var(--bg-secondary)] px-1.5 py-0.5 font-mono">{`{event: "${gtmEvent || 'X'}", imagina_audit_domain, imagina_audit_score}`}</code></p>
              <p><b>{t('settings.embed_ads_gtm_setup')}</b> {t('settings.embed_ads_gtm_setup_desc')}</p>
              <p><b>{t('settings.embed_ads_gtag_setup')}</b> {t('settings.embed_ads_gtag_setup_desc')}</p>
            </div>
          </details>
        </CardContent>
      </Card>

      {/* Snippet generado */}
      <Card>
        <CardHeader className="flex flex-row items-center justify-between">
          <CardTitle className="text-base">{t('settings.embed_snippet_card')}</CardTitle>
          <button
            onClick={copy}
            className="inline-flex items-center gap-1.5 rounded-md border border-[var(--border-default)] bg-white px-3 py-1.5 text-xs font-medium hover:border-[var(--accent-primary)] hover:text-[var(--accent-primary)] transition-colors"
          >
            {copied ? <Check className="h-3.5 w-3.5 text-emerald-600" /> : <Copy className="h-3.5 w-3.5" />}
            {copied ? t('common.copied') : t('settings.embed_copy')}
          </button>
        </CardHeader>
        <CardContent>
          <pre className="overflow-x-auto rounded-lg bg-[#0f172a] p-4 text-xs leading-relaxed text-[#e2e8f0] font-mono whitespace-pre">{scriptTag}</pre>
          <p className="mt-3 text-xs text-[var(--text-tertiary)]">{t('settings.embed_snippet_hint')}</p>
        </CardContent>
      </Card>
    </div>
  )
}

function ModeOption({ active, onClick, icon, title, desc }: { active: boolean; onClick: () => void; icon: React.ReactNode; title: string; desc: string }) {
  return (
    <button
      type="button"
      onClick={onClick}
      className={`rounded-xl border p-4 text-left transition-colors ${
        active
          ? 'border-[var(--accent-primary)] bg-[var(--accent-primary)]/5 ring-1 ring-[var(--accent-primary)]'
          : 'border-[var(--border-default)] hover:border-[var(--border-hover)]'
      }`}
    >
      <div className="flex items-center gap-2 text-[var(--text-primary)]">
        {icon}
        <span className="text-sm font-semibold">{title}</span>
      </div>
      <p className="mt-1.5 text-xs text-[var(--text-secondary)]">{desc}</p>
    </button>
  )
}
