import { useEffect, useState } from 'react'
import { useForm } from 'react-hook-form'
import { Loader2, Save } from 'lucide-react'
import { toast } from 'sonner'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { AccordionItem } from '@/components/ui/accordion'
import { Skeleton } from '@/components/ui/skeleton'
import { useAdmin } from '@/hooks/useAdmin'

export default function SettingsGeneral() {
  const { fetchSettings, updateSettings } = useAdmin()
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
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
      // Verificar contraseña
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

  if (loading) return <div className="space-y-4">{[...Array(4)].map((_, i) => <Skeleton key={i} className="h-16 rounded-2xl" />)}</div>

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-[var(--text-primary)]">Configuración General</h1>
      </div>

      <form onSubmit={handleSubmit(onSubmit)} className="space-y-6">
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
              <div className="sm:col-span-2"><label className="text-xs font-medium text-[var(--text-secondary)]">Email para notificaciones de leads</label><Input {...register('leadNotificationEmail')} type="email" placeholder="tu@email.com (recibe aviso por cada lead)" /></div>
            </div>
            {watch('logoUrl') && (
              <div className="mt-2"><img src={watch('logoUrl') as string} alt="Logo preview" className="h-10 object-contain" onError={(e) => (e.currentTarget.style.display = 'none')} /></div>
            )}
          </CardContent>
        </Card>

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

        <Card>
          <CardContent className="p-0">
            <AccordionItem title={<span className="font-semibold text-[var(--text-primary)]">Cambiar Contraseña</span>}>
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
    </div>
  )
}
