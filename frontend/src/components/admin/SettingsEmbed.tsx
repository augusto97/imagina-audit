import { useState, useMemo, useEffect } from 'react'
import { useTranslation } from 'react-i18next'
import { Copy, Check, Code2, BadgeInfo, MousePointer, LayoutPanelTop, Eye, Mail, User, MessageCircle } from 'lucide-react'
import { toast } from 'sonner'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { useConfigStore } from '@/store/configStore'

type Mode = 'floating' | 'inline'
type Theme = 'light' | 'dark'
type Style = 'card' | 'gradient' | 'minimal'

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
  const { companyWhatsapp, brandPrimaryColor, leadCapture } = useConfigStore((s) => s.config)

  const [mode, setMode] = useState<Mode>('inline')
  const [theme, setTheme] = useState<Theme>('light')
  const [style, setStyle] = useState<Style>('card')
  const [color, setColor] = useState(brandPrimaryColor || '#0CC0DF')
  const [position, setPosition] = useState<'bottom-right' | 'bottom-left'>('bottom-right')
  const [lang, setLang] = useState((i18n.language || 'es').slice(0, 2))
  const [whatsapp, setWhatsapp] = useState(companyWhatsapp || '')
  const [targetId, setTargetId] = useState('imagina-audit-block')
  const [gtmEvent, setGtmEvent] = useState('imagina_audit_lead')
  const [gtagConv, setGtagConv] = useState('')

  // Controles del preview (no se exportan al snippet — son solo para el
  // admin que está mirando aquí). El fondo del preview simula la página
  // host: el widget NO impone background, lo provee el sitio donde se
  // embeba, así que aquí lo elegimos para evaluarlo bien.
  const [previewState, setPreviewState] = useState<'preview' | 'form'>('preview')
  const [previewBg, setPreviewBg] = useState<'light' | 'dark' | 'pattern'>('light')

  const apiBase = window.location.origin + '/api'
  const widgetSrc = window.location.origin + '/widget/imagina-audit-widget.js'

  // Descargamos el código del widget FRESCO (cache:'no-store' + timestamp)
  // y lo inyectamos INLINE en el iframe. Esto elimina el problema de caché:
  // un <script src> se quedaba con la versión vieja del widget en el
  // navegador, por eso "solo cambiaba el color" (lo único que el widget
  // viejo soportaba). Inline = siempre el código actual del servidor.
  const [widgetCode, setWidgetCode] = useState<string | null>(null)
  const [widgetErr, setWidgetErr] = useState(false)
  useEffect(() => {
    let alive = true
    setWidgetCode(null)
    setWidgetErr(false)
    fetch(widgetSrc + '?t=' + Date.now(), { cache: 'no-store' })
      .then((r) => (r.ok ? r.text() : Promise.reject(new Error('http ' + r.status))))
      .then((txt) => { if (alive) setWidgetCode(txt) })
      .catch(() => { if (alive) setWidgetErr(true) })
    return () => { alive = false }
  }, [widgetSrc])

  // Debounce de inputs de texto que afectan al preview. Cada keystroke
  // cambiaba la `key` del iframe → desmonte/remonte completo del bloque
  // demo (parpadeo + pérdida de foco del campo activo del admin). Con el
  // debounce, la `key` y el srcDoc se "congelan" hasta 350 ms después de
  // que el usuario para de escribir.
  function useDebounced<T>(value: T, delay = 350): T {
    const [out, setOut] = useState(value)
    useEffect(() => {
      const id = window.setTimeout(() => setOut(value), delay)
      return () => window.clearTimeout(id)
    }, [value, delay])
    return out
  }
  const dColor = useDebounced(color)
  const dWhatsapp = useDebounced(whatsapp)

  // Campos que el widget pedirá: el email y el nombre siempre se muestran;
  // el WhatsApp solo si el admin lo marcó obligatorio en Captura de leads.
  // Reflejamos exactamente lo que hará el widget para que no haya sorpresas.
  const requiredFields = useMemo(() => ([
    { key: 'email', icon: Mail, label: t('settings.embed_field_email'), required: leadCapture?.requireEmail ?? true, shown: true },
    { key: 'name', icon: User, label: t('settings.embed_field_name'), required: leadCapture?.requireName ?? false, shown: true },
    { key: 'whatsapp', icon: MessageCircle, label: t('settings.embed_field_whatsapp'), required: leadCapture?.requireWhatsapp ?? false, shown: leadCapture?.requireWhatsapp ?? false },
  ]), [leadCapture, t])

  // Hash corto del código del widget — sirve como cache-buster en el src
  // del snippet. Cuando el widget cambia (nueva versión, nuevo despliegue)
  // el hash cambia, el `<script src=…?v={hash}>` cambia, y el navegador
  // del visitante refresca el JS en lugar de servir la versión vieja
  // cacheada (causa exacta de "los estilos no aplican al pegar el código":
  // el host servía la versión vieja del widget que NO conocía data-theme
  // ni data-style, solo data-color, único atributo que parecía funcionar).
  const widgetVer = useMemo(() => {
    if (!widgetCode) return null
    // FNV-1a 32-bit, ~3 KB/ms en JS — barato y suficientemente único.
    let h = 2166136261
    for (let i = 0; i < widgetCode.length; i++) {
      h ^= widgetCode.charCodeAt(i)
      h = Math.imul(h, 16777619)
    }
    return (h >>> 0).toString(36)
  }, [widgetCode])
  const widgetSrcVersioned = widgetVer ? `${widgetSrc}?v=${widgetVer}` : widgetSrc

  const attrs: Array<[string, string | undefined]> = [
    ['src', widgetSrcVersioned],
    ['data-api', apiBase],
    ['data-mode', mode],
    ['data-theme', theme],
    ['data-style', style],
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

  // Preview en vivo, en modo demo. El widget NO impone background — lo
  // provee el host; aquí simulamos "el fondo del sitio donde se embeba"
  // con previewBg, independiente del data-theme del widget.
  //
  // Forzamos data-require-* con la config real de Captura de leads para
  // que el demo muestre EXACTAMENTE los campos que el widget pedirá en
  // producción (sin race condition con /api/config.php).
  //
  // El preview respeta el MODO seleccionado: inline muestra el bloque,
  // floating muestra la burbuja + popup abierto dentro del iframe.
  const previewSrcDoc = useMemo(() => {
    if (!widgetCode) return ''
    const bgCss = previewBg === 'dark'
      ? '#0f1827'
      : previewBg === 'pattern'
      ? 'repeating-conic-gradient(#f8fafc 0% 25%, #eef2f7 0% 50%) 50%/24px 24px'
      : '#f4f6f9'

    const cfg: Record<string, string> = {
      'data-api': apiBase,
      'data-mode': mode,
      'data-target': '#ia-preview',
      'data-position': position,
      'data-theme': theme,
      'data-style': style,
      'data-color': dColor,
      'data-lang': lang,
      'data-demo': '1',
      'data-demo-state': previewState,
      'data-require-email': String(leadCapture?.requireEmail ?? true),
      'data-require-name': String(leadCapture?.requireName ?? false),
      'data-require-whatsapp': String(leadCapture?.requireWhatsapp ?? false),
    }
    if (dWhatsapp) cfg['data-whatsapp'] = dWhatsapp

    // El contenedor #ia-preview solo lo usa el modo inline; en floating el
    // widget se ancla al body del iframe. Damos altura para que la burbuja
    // (position:fixed) quede abajo-derecha del iframe.
    const cfgJson = JSON.stringify(cfg).replace(/</g, '\\u003c')
    const codeSafe = widgetCode.replace(/<\/script>/gi, '<\\/script>')
    return `<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head><body style="margin:0;padding:28px 16px;background:${bgCss};min-height:100vh;box-sizing:border-box"><div id="ia-preview"></div><script>window.IMAGINA_AUDIT_CONFIG=${cfgJson};<\/script><script>${codeSafe}<\/script></body></html>`
  }, [widgetCode, apiBase, mode, position, theme, style, dColor, lang, dWhatsapp, previewState, previewBg, leadCapture])

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
        <CardContent className="space-y-4">
          {/* Tema y estilo visual */}
          <div className="grid gap-4 sm:grid-cols-2">
            <div>
              <Label className="text-xs">{t('settings.embed_theme')}</Label>
              <div className="mt-1 grid grid-cols-2 gap-2">
                <SegBtn active={theme === 'light'} onClick={() => setTheme('light')} label={t('settings.embed_theme_light')} />
                <SegBtn active={theme === 'dark'} onClick={() => setTheme('dark')} label={t('settings.embed_theme_dark')} />
              </div>
            </div>
            <div>
              <Label className="text-xs">{t('settings.embed_style')}</Label>
              <div className="mt-1 grid grid-cols-3 gap-2">
                <SegBtn active={style === 'card'} onClick={() => setStyle('card')} label={t('settings.embed_style_cardv')} />
                <SegBtn active={style === 'gradient'} onClick={() => setStyle('gradient')} label={t('settings.embed_style_gradient')} />
                <SegBtn active={style === 'minimal'} onClick={() => setStyle('minimal')} label={t('settings.embed_style_minimal')} />
              </div>
            </div>
          </div>

          <div className="grid gap-4 sm:grid-cols-2">
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
          </div>

          {/* Aviso de campos de contacto que pedirá el widget */}
          <div className="rounded-lg border border-[var(--border-default)] bg-[var(--bg-secondary)] p-3">
            <p className="text-xs font-medium text-[var(--text-primary)]">{t('settings.embed_fields_title')}</p>
            <div className="mt-2 flex flex-wrap gap-2">
              {requiredFields.filter((f) => f.shown).map((f) => (
                <span key={f.key} className="inline-flex items-center gap-1.5 rounded-full border border-[var(--border-default)] bg-white px-2.5 py-1 text-[11px] text-[var(--text-secondary)]">
                  <f.icon className="h-3 w-3" />
                  {f.label}
                  {f.required
                    ? <span className="font-semibold text-red-500">{t('settings.embed_field_req')}</span>
                    : <span className="text-[var(--text-tertiary)]">{t('settings.embed_field_opt')}</span>}
                </span>
              ))}
            </div>
            <p className="mt-2 text-[11px] text-[var(--text-tertiary)]">{t('settings.embed_fields_hint')}</p>
          </div>
        </CardContent>
      </Card>

      {/* Preview en vivo */}
      <Card>
        <CardHeader>
          <CardTitle className="text-base flex items-center gap-2">
            <Eye className="h-5 w-5 text-[var(--accent-primary)]" />
            {t('settings.embed_preview_card')}
          </CardTitle>
        </CardHeader>
        <CardContent>
          {/* Controles del preview */}
          <div className="mb-3 flex flex-wrap items-center gap-3">
            <div className="inline-flex rounded-lg border border-[var(--border-default)] p-0.5 bg-white">
              <PreviewSeg active={previewState === 'preview'} onClick={() => setPreviewState('preview')} label={t('settings.embed_preview_state_result')} />
              <PreviewSeg active={previewState === 'form'} onClick={() => setPreviewState('form')} label={t('settings.embed_preview_state_form')} />
            </div>
            <div className="ml-auto inline-flex items-center gap-2">
              <span className="text-[11px] text-[var(--text-tertiary)]">{t('settings.embed_preview_bg')}:</span>
              <div className="inline-flex rounded-lg border border-[var(--border-default)] p-0.5 bg-white">
                <PreviewSeg active={previewBg === 'light'} onClick={() => setPreviewBg('light')} label={t('settings.embed_preview_bg_light')} />
                <PreviewSeg active={previewBg === 'dark'} onClick={() => setPreviewBg('dark')} label={t('settings.embed_preview_bg_dark')} />
                <PreviewSeg active={previewBg === 'pattern'} onClick={() => setPreviewBg('pattern')} label={t('settings.embed_preview_bg_pattern')} />
              </div>
            </div>
          </div>
          <div className="overflow-hidden rounded-xl border border-[var(--border-default)]">
            {widgetErr ? (
              <div className="flex h-[300px] items-center justify-center p-6 text-center text-sm text-[var(--text-secondary)]">
                {t('settings.embed_preview_err')}
              </div>
            ) : !widgetCode ? (
              <div className="flex h-[300px] items-center justify-center text-sm text-[var(--text-tertiary)]">
                {t('settings.embed_preview_loading')}
              </div>
            ) : (
              <iframe
                key={`${mode}-${theme}-${style}-${dColor}-${lang}-${position}-${previewState}-${previewBg}-${dWhatsapp}-${leadCapture?.requireWhatsapp}-${leadCapture?.requireName}-${leadCapture?.requireEmail}`}
                title="preview"
                srcDoc={previewSrcDoc}
                className="block h-[720px] w-full border-0"
                sandbox="allow-scripts allow-same-origin allow-popups"
              />
            )}
          </div>
          <p className="mt-3 text-xs text-[var(--text-tertiary)]">{t('settings.embed_preview_hint')}</p>
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

function PreviewSeg({ active, onClick, label }: { active: boolean; onClick: () => void; label: string }) {
  return (
    <button
      type="button"
      onClick={onClick}
      className={`rounded-md px-2.5 py-1 text-[11px] font-medium transition-colors ${
        active ? 'bg-[var(--accent-primary)] text-white' : 'text-[var(--text-secondary)] hover:text-[var(--text-primary)]'
      }`}
    >
      {label}
    </button>
  )
}

function SegBtn({ active, onClick, label }: { active: boolean; onClick: () => void; label: string }) {
  return (
    <button
      type="button"
      onClick={onClick}
      className={`rounded-lg border px-2 py-2 text-xs font-medium transition-colors ${
        active
          ? 'border-[var(--accent-primary)] bg-[var(--accent-primary)]/5 text-[var(--accent-primary)] ring-1 ring-[var(--accent-primary)]'
          : 'border-[var(--border-default)] text-[var(--text-secondary)] hover:border-[var(--border-hover)]'
      }`}
    >
      {label}
    </button>
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
