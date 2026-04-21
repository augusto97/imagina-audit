import { useEffect, useState, useCallback } from 'react'
import { useTranslation } from 'react-i18next'
import { useForm } from 'react-hook-form'
import { Loader2, Save, Send, Copy, Code, X, Plus } from 'lucide-react'
import { toast } from 'sonner'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Skeleton } from '@/components/ui/skeleton'
import { useAdmin } from '@/hooks/useAdmin'
import api from '@/lib/api'

export default function SettingsGeneral() {
  const { t } = useTranslation()
  const { fetchSettings, updateSettings } = useAdmin()
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [testingEmail, setTestingEmail] = useState(false)
  const [origins, setOrigins] = useState<string[]>([])
  const [originInput, setOriginInput] = useState('')
  const { register, handleSubmit, reset, watch } = useForm()

  useEffect(() => {
    fetchSettings().then((data: Record<string, unknown>) => {
      reset(data)
      const raw = (data.allowedOrigins as string) || '*'
      if (raw !== '*') {
        setOrigins(raw.split(',').map(s => s.trim()).filter(Boolean))
      } else {
        setOrigins([])
      }
      setLoading(false)
    })
  }, [fetchSettings, reset])

  const addOrigin = useCallback(() => {
    let val = originInput.trim()
    if (!val) return
    if (!val.startsWith('http://') && !val.startsWith('https://')) val = 'https://' + val
    val = val.replace(/\/+$/, '')
    if (!origins.includes(val)) setOrigins(prev => [...prev, val])
    setOriginInput('')
  }, [originInput, origins])

  const removeOrigin = useCallback((idx: number) => {
    setOrigins(prev => prev.filter((_, i) => i !== idx))
  }, [])

  const onSubmit = async (data: Record<string, unknown>) => {
    setSaving(true)
    try {
      const newPass = data.newPassword as string
      const confirmPass = data.confirmPassword as string
      const payload: Record<string, unknown> = { ...data }
      delete payload.newPassword
      delete payload.confirmPassword
      payload.allowedOrigins = origins.length > 0 ? origins.join(',') : '*'

      if (newPass && newPass.length >= 8) {
        if (newPass !== confirmPass) {
          toast.error(t('settings.general_password_mismatch'))
          setSaving(false)
          return
        }
        payload.adminPassword = newPass
      } else if (newPass && newPass.length < 8) {
        toast.error(t('settings.general_password_too_short'))
        setSaving(false)
        return
      }

      await updateSettings(payload)
      toast.success(t('settings.general_saved'))
    } catch {
      toast.error(t('settings.save_error'))
    }
    setSaving(false)
  }

  const sendTestEmail = async () => {
    const email = watch('leadNotificationEmail') as string
    if (!email) {
      toast.error(t('settings.general_smtp_test_need_email'))
      return
    }
    setTestingEmail(true)
    try {
      await api.post('/admin/test-email.php', { to: email })
      toast.success(t('settings.general_smtp_test_sent', { email }))
    } catch {
      toast.error(t('settings.general_smtp_test_error'))
    }
    setTestingEmail(false)
  }

  if (loading) return <div className="space-y-4">{[...Array(4)].map((_, i) => <Skeleton key={i} className="h-16 rounded-2xl" />)}</div>

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-[var(--text-primary)]">{t('settings.general_title')}</h1>
      </div>

      <form onSubmit={handleSubmit(onSubmit)} className="space-y-6">
        {/* Datos de empresa */}
        <Card>
          <CardHeader><CardTitle>{t('settings.general_company_card')}</CardTitle></CardHeader>
          <CardContent className="space-y-4">
            <div className="grid gap-4 sm:grid-cols-2">
              <div><label className="text-xs font-medium text-[var(--text-secondary)]">{t('settings.general_company_name')}</label><Input {...register('companyName')} /></div>
              <div><label className="text-xs font-medium text-[var(--text-secondary)]">{t('settings.general_company_url')}</label><Input {...register('companyUrl')} type="url" /></div>
              <div><label className="text-xs font-medium text-[var(--text-secondary)]">{t('settings.general_company_whatsapp')}</label><Input {...register('companyWhatsapp')} placeholder="+573001234567" /></div>
              <div><label className="text-xs font-medium text-[var(--text-secondary)]">{t('settings.general_company_email')}</label><Input {...register('companyEmail')} type="email" /></div>
              <div><label className="text-xs font-medium text-[var(--text-secondary)]">{t('settings.general_company_plans_url')}</label><Input {...register('companyPlansUrl')} type="url" /></div>
              <div><label className="text-xs font-medium text-[var(--text-secondary)]">{t('settings.general_company_logo_url')}</label><Input {...register('logoUrl')} type="url" /></div>
            </div>
            {watch('logoUrl') && (
              <div className="mt-2"><img src={watch('logoUrl') as string} alt="Logo preview" className="h-10 object-contain" onError={(e) => (e.currentTarget.style.display = 'none')} /></div>
            )}
          </CardContent>
        </Card>

        {/* Límites y Cache */}
        <Card>
          <CardHeader><CardTitle>{t('settings.general_limits_card')}</CardTitle></CardHeader>
          <CardContent className="space-y-4">
            <div className="grid gap-4 sm:grid-cols-2">
              <div>
                <label className="text-xs font-medium text-[var(--text-secondary)]">{t('settings.general_rate_limit_label')}</label>
                <Input {...register('rateLimitMaxPerHour')} type="number" min={1} max={1000} placeholder="100" />
                <p className="mt-1 text-xs text-[var(--text-tertiary)]">{t('settings.general_rate_limit_hint')}</p>
              </div>
              <div>
                <label className="text-xs font-medium text-[var(--text-secondary)]">{t('settings.general_cache_label')}</label>
                <Input {...register('cacheTtlHours')} type="number" min={0} max={168} placeholder="24" />
                <p className="mt-1 text-xs text-[var(--text-tertiary)]">{t('settings.general_cache_hint')}</p>
              </div>
            </div>
          </CardContent>
        </Card>

        {/* Dominios permitidos (CORS) */}
        <Card>
          <CardHeader>
            <CardTitle>{t('settings.general_origins_card')}</CardTitle>
            <p className="text-sm text-[var(--text-secondary)]">
              {t('settings.general_origins_subtitle')}
            </p>
          </CardHeader>
          <CardContent className="space-y-3">
            <div className="flex gap-2">
              <Input
                value={originInput}
                onChange={e => setOriginInput(e.target.value)}
                onKeyDown={e => { if (e.key === 'Enter') { e.preventDefault(); addOrigin() } }}
                placeholder={t('settings.general_origins_placeholder')}
                className="flex-1"
              />
              <Button type="button" variant="outline" size="sm" onClick={addOrigin} disabled={!originInput.trim()}>
                <Plus className="h-4 w-4" strokeWidth={1.5} />
                {t('settings.general_origins_add')}
              </Button>
            </div>
            {origins.length > 0 ? (
              <div className="flex flex-wrap gap-2">
                {origins.map((o, i) => (
                  <span key={i} className="inline-flex items-center gap-1.5 rounded-full bg-blue-50 border border-blue-200 px-3 py-1 text-sm text-blue-700">
                    {o}
                    <button type="button" onClick={() => removeOrigin(i)} className="text-blue-400 hover:text-blue-700 transition-colors">
                      <X className="h-3.5 w-3.5" strokeWidth={2} />
                    </button>
                  </span>
                ))}
              </div>
            ) : (
              <p className="text-xs text-amber-600 bg-amber-50 rounded-lg px-3 py-2">
                {t('settings.general_origins_empty')}
              </p>
            )}
          </CardContent>
        </Card>

        {/* Notificaciones email */}
        <Card>
          <CardHeader><CardTitle>{t('settings.general_notifications_card')}</CardTitle></CardHeader>
          <CardContent className="space-y-4">
            <div>
              <label className="text-xs font-medium text-[var(--text-secondary)]">{t('settings.general_notifications_email_label')}</label>
              <Input {...register('leadNotificationEmail')} type="email" placeholder={t('settings.general_notifications_email_placeholder')} />
              <p className="mt-1 text-xs text-[var(--text-tertiary)]">{t('settings.general_notifications_email_hint')}</p>
            </div>
          </CardContent>
        </Card>

        {/* Configuración SMTP */}
        <Card>
          <CardHeader>
            <CardTitle>{t('settings.general_smtp_card')}</CardTitle>
            <p className="text-sm text-[var(--text-secondary)]">{t('settings.general_smtp_subtitle')}</p>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="grid gap-4 sm:grid-cols-2">
              <div>
                <label className="text-xs font-medium text-[var(--text-secondary)]">{t('settings.general_smtp_host')}</label>
                <Input {...register('smtpHost')} placeholder="smtp.gmail.com" />
              </div>
              <div>
                <label className="text-xs font-medium text-[var(--text-secondary)]">{t('settings.general_smtp_port')}</label>
                <Input {...register('smtpPort')} type="number" placeholder="587" />
              </div>
              <div>
                <label className="text-xs font-medium text-[var(--text-secondary)]">{t('settings.general_smtp_username')}</label>
                <Input {...register('smtpUsername')} placeholder="tu@gmail.com" />
              </div>
              <div>
                <label className="text-xs font-medium text-[var(--text-secondary)]">{t('settings.general_smtp_password')}</label>
                <Input {...register('smtpPassword')} type="password" placeholder={t('settings.general_smtp_password_placeholder')} />
              </div>
              <div>
                <label className="text-xs font-medium text-[var(--text-secondary)]">{t('settings.general_smtp_encryption')}</label>
                <select {...register('smtpEncryption')} className="h-10 w-full rounded-xl border border-[var(--border-default)] bg-white px-3 text-sm">
                  <option value="tls">{t('settings.general_smtp_encryption_tls')}</option>
                  <option value="ssl">{t('settings.general_smtp_encryption_ssl')}</option>
                  <option value="">{t('settings.general_smtp_encryption_none')}</option>
                </select>
              </div>
              <div>
                <label className="text-xs font-medium text-[var(--text-secondary)]">{t('settings.general_smtp_from_email')}</label>
                <Input {...register('smtpFromEmail')} type="email" placeholder="noreply@tudominio.com" />
              </div>
              <div className="sm:col-span-2">
                <label className="text-xs font-medium text-[var(--text-secondary)]">{t('settings.general_smtp_from_name')}</label>
                <Input {...register('smtpFromName')} placeholder="Imagina Audit" />
              </div>
            </div>

            <div className="rounded-xl bg-[var(--bg-tertiary)] p-3 text-xs text-[var(--text-secondary)] space-y-1">
              <p className="font-medium">{t('settings.general_smtp_examples')}</p>
              <p dangerouslySetInnerHTML={{ __html: t('settings.general_smtp_example_gmail') }} />
              <p dangerouslySetInnerHTML={{ __html: t('settings.general_smtp_example_cpanel') }} />
              <p dangerouslySetInnerHTML={{ __html: t('settings.general_smtp_example_brevo') }} />
            </div>

            <Button type="button" variant="outline" size="sm" onClick={sendTestEmail} disabled={testingEmail}>
              {testingEmail ? <Loader2 className="h-4 w-4 animate-spin" /> : <Send className="h-4 w-4" strokeWidth={1.5} />}
              {t('settings.general_smtp_send_test')}
            </Button>
          </CardContent>
        </Card>

        {/* API Keys */}
        <Card>
          <CardHeader><CardTitle>{t('settings.general_apikeys_card')}</CardTitle></CardHeader>
          <CardContent className="space-y-4">
            <div className="space-y-1.5">
              <label className="text-xs font-medium text-[var(--text-secondary)]">{t('settings.general_pagespeed_label')}</label>
              <Input {...register('googlePagespeedApiKey')} placeholder="AIza..." />
              <p className="text-xs text-[var(--text-tertiary)]">{t('settings.general_pagespeed_hint')}</p>
            </div>
          </CardContent>
        </Card>

        {/* AI providers for translations */}
        <Card>
          <CardHeader>
            <CardTitle>{t('settings.ai_card_title')}</CardTitle>
            <p className="text-sm text-[var(--text-secondary)]">{t('settings.ai_card_subtitle')}</p>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="space-y-1.5">
              <label className="text-xs font-medium text-[var(--text-secondary)]">{t('settings.ai_provider_default')}</label>
              <select {...register('defaultAiProvider')} className="h-10 w-full rounded-xl border border-[var(--border-default)] bg-white px-3 text-sm">
                <option value="claude">Claude (Anthropic)</option>
                <option value="chatgpt">ChatGPT (OpenAI)</option>
                <option value="google">Google Translate</option>
              </select>
            </div>
            <div className="grid gap-4 sm:grid-cols-2">
              <div className="space-y-1.5">
                <label className="text-xs font-medium text-[var(--text-secondary)]">{t('settings.ai_openai_key')}</label>
                <Input {...register('openaiApiKey')} type="password" placeholder="sk-..." />
                <p className="text-xs text-[var(--text-tertiary)]">{t('settings.ai_openai_key_hint')}</p>
              </div>
              <div className="space-y-1.5">
                <label className="text-xs font-medium text-[var(--text-secondary)]">{t('settings.ai_openai_model')}</label>
                <Input {...register('openaiModel')} placeholder="gpt-4o-mini" />
              </div>
              <div className="space-y-1.5">
                <label className="text-xs font-medium text-[var(--text-secondary)]">{t('settings.ai_anthropic_key')}</label>
                <Input {...register('anthropicApiKey')} type="password" placeholder="sk-ant-..." />
                <p className="text-xs text-[var(--text-tertiary)]">{t('settings.ai_anthropic_key_hint')}</p>
              </div>
              <div className="space-y-1.5">
                <label className="text-xs font-medium text-[var(--text-secondary)]">{t('settings.ai_anthropic_model')}</label>
                <Input {...register('anthropicModel')} placeholder="claude-sonnet-4-5" />
              </div>
              <div className="space-y-1.5 sm:col-span-2">
                <label className="text-xs font-medium text-[var(--text-secondary)]">{t('settings.ai_google_key')}</label>
                <Input {...register('googleTranslateApiKey')} type="password" placeholder="AIza..." />
                <p className="text-xs text-[var(--text-tertiary)]">{t('settings.ai_google_key_hint')}</p>
              </div>
            </div>
          </CardContent>
        </Card>

        {/* Cambiar contraseña */}
        <Card>
          <CardHeader><CardTitle>{t('settings.general_password_card')}</CardTitle></CardHeader>
          <CardContent className="space-y-3">
            <div className="space-y-1.5">
              <label className="text-xs font-medium text-[var(--text-secondary)]">{t('settings.general_password_new')}</label>
              <Input {...register('newPassword')} type="password" placeholder={t('settings.general_password_new_placeholder')} />
            </div>
            <div className="space-y-1.5">
              <label className="text-xs font-medium text-[var(--text-secondary)]">{t('settings.general_password_confirm')}</label>
              <Input {...register('confirmPassword')} type="password" />
            </div>
          </CardContent>
        </Card>

        <Button type="submit" disabled={saving} className="w-full sm:w-auto">
          {saving ? <Loader2 className="h-4 w-4 animate-spin" /> : <Save className="h-4 w-4" strokeWidth={1.5} />}
          {t('settings.general_save')}
        </Button>
      </form>

      {/* Widget Embebible */}
      <WidgetSection />
    </div>
  )
}

/** Sección del widget embebible con código copiable y personalización */
function WidgetSection() {
  const { t } = useTranslation()
  const [widgetColor, setWidgetColor] = useState('#0CC0DF')
  const [widgetPosition, setWidgetPosition] = useState('bottom-right')
  const [widgetLang, setWidgetLang] = useState('es')
  const [widgetWhatsapp, setWidgetWhatsapp] = useState('')

  const baseUrl = window.location.origin
  const whatsappAttr = widgetWhatsapp ? `\n  data-whatsapp="${widgetWhatsapp}"` : ''
  const widgetCode = `<script\n  src="${baseUrl}/widget/imagina-audit-widget.js"\n  data-api="${baseUrl}/api"\n  data-color="${widgetColor}"\n  data-position="${widgetPosition}"\n  data-lang="${widgetLang}"${whatsappAttr}>\n</script>`

  const copyCode = () => {
    navigator.clipboard.writeText(widgetCode)
    toast.success(t('settings.widget_copied'))
  }

  return (
    <Card className="mt-6">
      <CardHeader>
        <div className="flex items-center gap-2">
          <Code className="h-5 w-5 text-[var(--accent-primary)]" strokeWidth={1.5} />
          <CardTitle>{t('settings.widget_card')}</CardTitle>
        </div>
        <p className="text-sm text-[var(--text-secondary)]">
          {t('settings.widget_subtitle')}
        </p>
      </CardHeader>
      <CardContent className="space-y-5">
        {/* Personalizadores */}
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <div>
            <label className="mb-1 block text-xs font-medium text-[var(--text-secondary)]">{t('settings.widget_whatsapp_label')}</label>
            <Input
              value={widgetWhatsapp}
              onChange={(e) => setWidgetWhatsapp(e.target.value)}
              placeholder="+573001234567"
            />
            <p className="mt-1 text-[10px] text-[var(--text-tertiary)]">{t('settings.widget_whatsapp_hint')}</p>
          </div>
          <div>
            <label className="mb-1 block text-xs font-medium text-[var(--text-secondary)]">{t('settings.widget_color_label')}</label>
            <div className="flex items-center gap-2">
              <input
                type="color"
                value={widgetColor}
                onChange={(e) => setWidgetColor(e.target.value)}
                className="h-10 w-12 cursor-pointer rounded-lg border border-[var(--border-default)] bg-white p-1"
              />
              <span className="text-xs text-[var(--text-tertiary)] font-mono">{widgetColor}</span>
            </div>
          </div>
          <div>
            <label className="mb-1 block text-xs font-medium text-[var(--text-secondary)]">{t('settings.widget_position_label')}</label>
            <select
              value={widgetPosition}
              onChange={(e) => setWidgetPosition(e.target.value)}
              className="h-10 w-full rounded-xl border border-[var(--border-default)] bg-white px-3 text-sm"
            >
              <option value="bottom-right">{t('settings.widget_position_bottom_right')}</option>
              <option value="bottom-left">{t('settings.widget_position_bottom_left')}</option>
            </select>
          </div>
          <div>
            <label className="mb-1 block text-xs font-medium text-[var(--text-secondary)]">{t('settings.widget_lang_label')}</label>
            <select
              value={widgetLang}
              onChange={(e) => setWidgetLang(e.target.value)}
              className="h-10 w-full rounded-xl border border-[var(--border-default)] bg-white px-3 text-sm"
            >
              <option value="es">{t('settings.widget_lang_es')}</option>
              <option value="en">{t('settings.widget_lang_en')}</option>
            </select>
          </div>
        </div>

        {/* Código */}
        <div className="relative">
          <pre className="overflow-x-auto rounded-xl bg-[#1e293b] p-4 text-[13px] leading-relaxed text-emerald-300 font-mono">
            {widgetCode}
          </pre>
          <Button
            type="button"
            variant="secondary"
            size="sm"
            className="absolute right-3 top-3"
            onClick={copyCode}
          >
            <Copy className="h-3.5 w-3.5" strokeWidth={1.5} />
            {t('settings.widget_copy')}
          </Button>
        </div>

        <p className="text-xs text-[var(--text-tertiary)]">
          {t('settings.widget_footer_note')}
        </p>
      </CardContent>
    </Card>
  )
}
