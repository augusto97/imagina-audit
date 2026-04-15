import { useEffect, useState } from 'react'
import { useForm } from 'react-hook-form'
import { Loader2, Save, Send, Copy, Code } from 'lucide-react'
import { toast } from 'sonner'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { AccordionItem } from '@/components/ui/accordion'
import { Skeleton } from '@/components/ui/skeleton'
import { useAdmin } from '@/hooks/useAdmin'
import api from '@/lib/api'

export default function SettingsGeneral() {
  const { fetchSettings, updateSettings } = useAdmin()
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [testingEmail, setTestingEmail] = useState(false)
  const { register, handleSubmit, reset, watch } = useForm()

  useEffect(() => {
    fetchSettings().then((data: Record<string, unknown>) => {
      reset(data)
      setLoading(false)
    })
  }, [fetchSettings, reset])

  const onSubmit = async (data: Record<string, unknown>) => {
    setSaving(true)
    try {
      const newPass = data.newPassword as string
      const confirmPass = data.confirmPassword as string
      const payload: Record<string, unknown> = { ...data }
      delete payload.newPassword
      delete payload.confirmPassword

      if (newPass && newPass.length >= 8) {
        if (newPass !== confirmPass) {
          toast.error('Las contraseñas no coinciden')
          setSaving(false)
          return
        }
        payload.adminPassword = newPass
      } else if (newPass && newPass.length < 8) {
        toast.error('La contraseña debe tener al menos 8 caracteres')
        setSaving(false)
        return
      }

      await updateSettings(payload)
      toast.success('Configuración guardada correctamente')
    } catch {
      toast.error('Error al guardar')
    }
    setSaving(false)
  }

  const sendTestEmail = async () => {
    const email = watch('leadNotificationEmail') as string
    if (!email) {
      toast.error('Primero configura el email de notificaciones')
      return
    }
    setTestingEmail(true)
    try {
      await api.post('/admin/test-email.php', { to: email })
      toast.success(`Email de prueba enviado a ${email}`)
    } catch {
      toast.error('Error al enviar. Verifica la configuración SMTP.')
    }
    setTestingEmail(false)
  }

  if (loading) return <div className="space-y-4">{[...Array(4)].map((_, i) => <Skeleton key={i} className="h-16 rounded-2xl" />)}</div>

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-[var(--text-primary)]">Configuración General</h1>
      </div>

      <form onSubmit={handleSubmit(onSubmit)} className="space-y-6">
        {/* Datos de empresa */}
        <Card>
          <CardHeader><CardTitle>Datos de la Empresa</CardTitle></CardHeader>
          <CardContent className="space-y-4">
            <div className="grid gap-4 sm:grid-cols-2">
              <div><label className="text-xs font-medium text-[var(--text-secondary)]">Nombre</label><Input {...register('companyName')} /></div>
              <div><label className="text-xs font-medium text-[var(--text-secondary)]">URL del sitio</label><Input {...register('companyUrl')} type="url" /></div>
              <div><label className="text-xs font-medium text-[var(--text-secondary)]">WhatsApp</label><Input {...register('companyWhatsapp')} placeholder="+573001234567" /></div>
              <div><label className="text-xs font-medium text-[var(--text-secondary)]">Email de contacto</label><Input {...register('companyEmail')} type="email" /></div>
              <div><label className="text-xs font-medium text-[var(--text-secondary)]">URL de planes</label><Input {...register('companyPlansUrl')} type="url" /></div>
              <div><label className="text-xs font-medium text-[var(--text-secondary)]">URL del logo</label><Input {...register('logoUrl')} type="url" /></div>
            </div>
            {watch('logoUrl') && (
              <div className="mt-2"><img src={watch('logoUrl') as string} alt="Logo preview" className="h-10 object-contain" onError={(e) => (e.currentTarget.style.display = 'none')} /></div>
            )}
          </CardContent>
        </Card>

        {/* Límites y Cache */}
        <Card>
          <CardHeader><CardTitle>Límites y Cache</CardTitle></CardHeader>
          <CardContent className="space-y-4">
            <div className="grid gap-4 sm:grid-cols-2">
              <div>
                <label className="text-xs font-medium text-[var(--text-secondary)]">Auditorías por hora (por IP)</label>
                <Input {...register('rateLimitMaxPerHour')} type="number" min={1} max={1000} placeholder="100" />
                <p className="mt-1 text-xs text-[var(--text-tertiary)]">Usuarios no administradores. Tú como admin no tienes límite.</p>
              </div>
              <div>
                <label className="text-xs font-medium text-[var(--text-secondary)]">Cache de auditorías (horas)</label>
                <Input {...register('cacheTtlHours')} type="number" min={0} max={168} placeholder="24" />
                <p className="mt-1 text-xs text-[var(--text-tertiary)]">Tiempo que se reutiliza un resultado antes de re-escanear. 0 = sin cache.</p>
              </div>
            </div>
          </CardContent>
        </Card>

        {/* Notificaciones email */}
        <Card>
          <CardHeader><CardTitle>Notificaciones de Leads</CardTitle></CardHeader>
          <CardContent className="space-y-4">
            <div>
              <label className="text-xs font-medium text-[var(--text-secondary)]">Email para notificaciones</label>
              <Input {...register('leadNotificationEmail')} type="email" placeholder="tu@email.com (recibe aviso por cada lead)" />
              <p className="mt-1 text-xs text-[var(--text-tertiary)]">Recibirás un email cada vez que un prospecto deje datos de contacto.</p>
            </div>
          </CardContent>
        </Card>

        {/* Configuración SMTP */}
        <Card>
          <CardHeader>
            <CardTitle>Configuración SMTP</CardTitle>
            <p className="text-sm text-[var(--text-secondary)]">Necesario para enviar notificaciones por email. Si tu hosting no tiene mail() configurado, usa un servicio SMTP externo.</p>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="grid gap-4 sm:grid-cols-2">
              <div>
                <label className="text-xs font-medium text-[var(--text-secondary)]">Servidor SMTP</label>
                <Input {...register('smtpHost')} placeholder="smtp.gmail.com" />
              </div>
              <div>
                <label className="text-xs font-medium text-[var(--text-secondary)]">Puerto</label>
                <Input {...register('smtpPort')} type="number" placeholder="587" />
              </div>
              <div>
                <label className="text-xs font-medium text-[var(--text-secondary)]">Usuario SMTP</label>
                <Input {...register('smtpUsername')} placeholder="tu@gmail.com" />
              </div>
              <div>
                <label className="text-xs font-medium text-[var(--text-secondary)]">Contraseña SMTP</label>
                <Input {...register('smtpPassword')} type="password" placeholder="Contraseña o app password" />
              </div>
              <div>
                <label className="text-xs font-medium text-[var(--text-secondary)]">Encriptación</label>
                <select {...register('smtpEncryption')} className="h-10 w-full rounded-xl border border-[var(--border-default)] bg-white px-3 text-sm">
                  <option value="tls">TLS (recomendado, puerto 587)</option>
                  <option value="ssl">SSL (puerto 465)</option>
                  <option value="">Ninguna (puerto 25)</option>
                </select>
              </div>
              <div>
                <label className="text-xs font-medium text-[var(--text-secondary)]">Email remitente</label>
                <Input {...register('smtpFromEmail')} type="email" placeholder="noreply@tudominio.com" />
              </div>
              <div className="sm:col-span-2">
                <label className="text-xs font-medium text-[var(--text-secondary)]">Nombre remitente</label>
                <Input {...register('smtpFromName')} placeholder="Imagina Audit" />
              </div>
            </div>

            <div className="rounded-xl bg-[var(--bg-tertiary)] p-3 text-xs text-[var(--text-secondary)] space-y-1">
              <p className="font-medium">Ejemplos de configuración:</p>
              <p><strong>Gmail:</strong> smtp.gmail.com / 587 / TLS / Usa una "App Password" de Google</p>
              <p><strong>cPanel:</strong> mail.tudominio.com / 465 / SSL / Tu email del hosting</p>
              <p><strong>Brevo (gratis):</strong> smtp-relay.brevo.com / 587 / TLS / Tu API key como contraseña</p>
            </div>

            <Button type="button" variant="outline" size="sm" onClick={sendTestEmail} disabled={testingEmail}>
              {testingEmail ? <Loader2 className="h-4 w-4 animate-spin" /> : <Send className="h-4 w-4" strokeWidth={1.5} />}
              Enviar email de prueba
            </Button>
          </CardContent>
        </Card>

        {/* API Keys */}
        <Card>
          <CardContent className="p-0">
            <AccordionItem title={<span className="font-semibold text-[var(--text-primary)]">API Keys</span>}>
              <div className="px-6 pb-4 space-y-2">
                <label className="text-xs font-medium text-[var(--text-secondary)]">Google PageSpeed API Key</label>
                <Input {...register('googlePagespeedApiKey')} placeholder="AIza..." />
                <p className="text-xs text-[var(--text-tertiary)]">Opcional. Sin key funciona con cuota limitada.</p>
              </div>
            </AccordionItem>
          </CardContent>
        </Card>

        {/* Cambiar contraseña */}
        <Card>
          <CardContent className="p-0">
            <AccordionItem title={<span className="font-semibold text-[var(--text-primary)]">Cambiar Contraseña del Admin</span>}>
              <div className="px-6 pb-4 space-y-3">
                <div><label className="text-xs font-medium text-[var(--text-secondary)]">Nueva contraseña</label><Input {...register('newPassword')} type="password" placeholder="Mínimo 8 caracteres" /></div>
                <div><label className="text-xs font-medium text-[var(--text-secondary)]">Confirmar contraseña</label><Input {...register('confirmPassword')} type="password" /></div>
              </div>
            </AccordionItem>
          </CardContent>
        </Card>

        <Button type="submit" disabled={saving} className="w-full sm:w-auto">
          {saving ? <Loader2 className="h-4 w-4 animate-spin" /> : <Save className="h-4 w-4" strokeWidth={1.5} />}
          Guardar Cambios
        </Button>
      </form>

      {/* Widget Embebible */}
      <WidgetSection />
    </div>
  )
}

/** Sección del widget embebible con código copiable y personalización */
function WidgetSection() {
  const [widgetColor, setWidgetColor] = useState('#0CC0DF')
  const [widgetPosition, setWidgetPosition] = useState('bottom-right')
  const [widgetLang, setWidgetLang] = useState('es')

  const baseUrl = window.location.origin
  const widgetCode = `<script\n  src="${baseUrl}/widget/imagina-audit-widget.js"\n  data-api="${baseUrl}/api"\n  data-color="${widgetColor}"\n  data-position="${widgetPosition}"\n  data-lang="${widgetLang}">\n</script>`

  const copyCode = () => {
    navigator.clipboard.writeText(widgetCode)
    toast.success('Código copiado al portapapeles')
  }

  return (
    <Card className="mt-6">
      <CardHeader>
        <div className="flex items-center gap-2">
          <Code className="h-5 w-5 text-[var(--accent-primary)]" strokeWidth={1.5} />
          <CardTitle>Widget Embebible</CardTitle>
        </div>
        <p className="text-sm text-[var(--text-secondary)]">
          Pega este código en cualquier sitio web para ofrecer auditorías desde esa página. Aparece un botón flotante que abre un popup de auditoría.
        </p>
      </CardHeader>
      <CardContent className="space-y-5">
        {/* Personalizadores */}
        <div className="grid gap-4 sm:grid-cols-3">
          <div>
            <label className="mb-1 block text-xs font-medium text-[var(--text-secondary)]">Color del botón</label>
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
            <label className="mb-1 block text-xs font-medium text-[var(--text-secondary)]">Posición</label>
            <select
              value={widgetPosition}
              onChange={(e) => setWidgetPosition(e.target.value)}
              className="h-10 w-full rounded-xl border border-[var(--border-default)] bg-white px-3 text-sm"
            >
              <option value="bottom-right">Inferior derecha</option>
              <option value="bottom-left">Inferior izquierda</option>
            </select>
          </div>
          <div>
            <label className="mb-1 block text-xs font-medium text-[var(--text-secondary)]">Idioma</label>
            <select
              value={widgetLang}
              onChange={(e) => setWidgetLang(e.target.value)}
              className="h-10 w-full rounded-xl border border-[var(--border-default)] bg-white px-3 text-sm"
            >
              <option value="es">Español</option>
              <option value="en">Inglés</option>
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
            Copiar
          </Button>
        </div>

        <p className="text-xs text-[var(--text-tertiary)]">
          El widget es un archivo JavaScript de ~12KB, sin dependencias externas. Se carga de forma asíncrona y no afecta la velocidad del sitio donde se instale.
        </p>
      </CardContent>
    </Card>
  )
}
