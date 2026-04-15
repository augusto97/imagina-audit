import { useEffect, useState } from 'react'
import { useForm } from 'react-hook-form'
import { Loader2, Save } from 'lucide-react'
import { toast } from 'sonner'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { useAdmin } from '@/hooks/useAdmin'
import { Skeleton } from '@/components/ui/skeleton'
import { MODULE_EMOJIS, MODULE_NAMES } from '@/lib/constants'

const moduleIds = ['wordpress', 'security', 'performance', 'seo', 'mobile', 'infrastructure', 'conversion']

export default function SettingsMessages() {
  const { fetchSettings, updateSettings } = useAdmin()
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const { register, handleSubmit, reset, watch } = useForm()

  useEffect(() => {
    fetchSettings().then((data: Record<string, unknown>) => {
      const flat: Record<string, unknown> = {}
      const msgs = data.salesMessages as Record<string, string> || {}
      for (const id of moduleIds) flat[`sales_${id}`] = msgs[id] || ''
      flat.ctaTitle = data.ctaTitle
      flat.ctaDescription = data.ctaDescription
      flat.ctaButtonWhatsappText = data.ctaButtonWhatsappText
      flat.ctaButtonPlansText = data.ctaButtonPlansText
      reset(flat)
      setLoading(false)
    })
  }, [fetchSettings, reset])

  const onSubmit = async (data: Record<string, unknown>) => {
    setSaving(true)
    try {
      // Reconstruir salesMessages como objeto
      const salesMessages: Record<string, string> = {}
      for (const id of moduleIds) {
        salesMessages[id] = (data[`sales_${id}`] as string) || ''
      }
      await updateSettings({
        salesMessages,
        ctaTitle: data.ctaTitle,
        ctaDescription: data.ctaDescription,
        ctaButtonWhatsappText: data.ctaButtonWhatsappText,
        ctaButtonPlansText: data.ctaButtonPlansText,
      })
      toast.success('Mensajes guardados correctamente')
    } catch { toast.error('Error al guardar') }
    setSaving(false)
  }

  if (loading) return <div className="space-y-4">{[...Array(4)].map((_, i) => <Skeleton key={i} className="h-24 rounded-2xl" />)}</div>

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-[var(--text-primary)]">Textos y Mensajes</h1>
        <p className="text-sm text-[var(--text-secondary)]">Mensajes de venta que aparecen en cada módulo del informe</p>
      </div>

      <form onSubmit={handleSubmit(onSubmit)} className="space-y-6">
        {/* Mensajes por módulo */}
        <Card>
          <CardHeader><CardTitle>Mensajes de Venta por Módulo</CardTitle></CardHeader>
          <CardContent className="space-y-4">
            {moduleIds.map((id) => (
              <div key={id}>
                <label className="text-sm font-medium text-[var(--text-primary)]">
                  {MODULE_EMOJIS[id]} {MODULE_NAMES[id]}
                </label>
                <textarea
                  {...register(`sales_${id}`)}
                  rows={3}
                  className="mt-1 w-full rounded-xl border border-[var(--border-default)] bg-white px-3 py-2 text-sm text-[var(--text-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--accent-primary)]"
                />
              </div>
            ))}
          </CardContent>
        </Card>

        {/* Textos del CTA */}
        <Card>
          <CardHeader><CardTitle>Textos del CTA Final</CardTitle></CardHeader>
          <CardContent className="space-y-4">
            <div><label className="text-xs font-medium text-[var(--text-secondary)]">Título del CTA</label><Input {...register('ctaTitle')} /></div>
            <div>
              <label className="text-xs font-medium text-[var(--text-secondary)]">Descripción del CTA</label>
              <textarea {...register('ctaDescription')} rows={3} className="mt-1 w-full rounded-xl border border-[var(--border-default)] bg-white px-3 py-2 text-sm text-[var(--text-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--accent-primary)]" />
            </div>
            <div className="grid gap-4 sm:grid-cols-2">
              <div><label className="text-xs font-medium text-[var(--text-secondary)]">Texto botón WhatsApp</label><Input {...register('ctaButtonWhatsappText')} /></div>
              <div><label className="text-xs font-medium text-[var(--text-secondary)]">Texto botón Planes</label><Input {...register('ctaButtonPlansText')} /></div>
            </div>
          </CardContent>
        </Card>

        {/* Preview CTA */}
        <Card className="border-[var(--accent-primary)]/30 bg-gradient-to-br from-[#F0FDFE] to-white">
          <CardContent className="p-6 text-center">
            <p className="text-xs font-medium text-[var(--text-tertiary)] mb-2">Vista previa del CTA</p>
            <h3 className="text-lg font-bold text-[var(--text-primary)]">{watch('ctaTitle') as string || 'Título'}</h3>
            <p className="mt-2 text-sm text-[var(--text-secondary)]">{watch('ctaDescription') as string || 'Descripción'}</p>
            <div className="mt-4 flex flex-wrap justify-center gap-2">
              <Button size="sm" variant="success">{watch('ctaButtonWhatsappText') as string || 'WhatsApp'}</Button>
              <Button size="sm" variant="outline">{watch('ctaButtonPlansText') as string || 'Planes'}</Button>
            </div>
          </CardContent>
        </Card>

        <Button type="submit" disabled={saving}>
          {saving ? <Loader2 className="h-4 w-4 animate-spin" /> : <Save className="h-4 w-4" strokeWidth={1.5} />}
          Guardar Mensajes
        </Button>
      </form>
    </div>
  )
}
