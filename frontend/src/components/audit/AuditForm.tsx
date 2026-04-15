import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { Globe, User, Mail, Phone, Search } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Card, CardContent } from '@/components/ui/card'
import { useAudit } from '@/hooks/useAudit'

const auditSchema = z.object({
  url: z.string().min(1, 'La URL es obligatoria').refine(
    (val) => {
      try {
        const url = val.startsWith('http') ? val : `https://${val}`
        new URL(url)
        return true
      } catch {
        return false
      }
    },
    { message: 'Ingresa una URL válida' }
  ),
  leadName: z.string().optional(),
  leadEmail: z.string().email('Email no válido').optional().or(z.literal('')),
  leadWhatsapp: z.string().optional(),
})

type AuditFormData = z.infer<typeof auditSchema>

export default function AuditForm() {
  const { startAudit, status } = useAudit()
  const isScanning = status === 'scanning'

  const { register, handleSubmit, formState: { errors } } = useForm<AuditFormData>({
    resolver: zodResolver(auditSchema),
    defaultValues: {
      url: '',
      leadName: '',
      leadEmail: '',
      leadWhatsapp: '',
    },
  })

  const onSubmit = (data: AuditFormData) => {
    const url = data.url.startsWith('http') ? data.url : `https://${data.url}`
    startAudit({
      url,
      leadName: data.leadName || undefined,
      leadEmail: data.leadEmail || undefined,
      leadWhatsapp: data.leadWhatsapp || undefined,
    })
  }

  return (
    <Card className="glass-card mx-auto w-full max-w-lg">
      <CardContent className="p-6 sm:p-8">
        <form onSubmit={handleSubmit(onSubmit)} className="space-y-4">
          {/* URL */}
          <div className="space-y-1">
            <div className="relative">
              <Globe className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[var(--text-tertiary)]" strokeWidth={1.5} />
              <Input
                {...register('url')}
                placeholder="https://tusitio.com"
                className="h-12 pl-10 text-base"
                disabled={isScanning}
              />
            </div>
            {errors.url && (
              <p className="text-xs text-[var(--color-critical)]">{errors.url.message}</p>
            )}
          </div>

          {/* Campos opcionales de lead */}
          <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <div className="relative">
              <User className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[var(--text-tertiary)]" strokeWidth={1.5} />
              <Input
                {...register('leadName')}
                placeholder="Tu nombre"
                className="pl-10"
                disabled={isScanning}
              />
            </div>
            <div className="relative">
              <Mail className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[var(--text-tertiary)]" strokeWidth={1.5} />
              <Input
                {...register('leadEmail')}
                placeholder="tu@email.com"
                type="email"
                className="pl-10"
                disabled={isScanning}
              />
            </div>
            <div className="relative">
              <Phone className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[var(--text-tertiary)]" strokeWidth={1.5} />
              <Input
                {...register('leadWhatsapp')}
                placeholder="+57..."
                className="pl-10"
                disabled={isScanning}
              />
            </div>
          </div>

          {/* Botón submit */}
          <Button
            type="submit"
            size="xl"
            className="w-full glow"
            disabled={isScanning}
          >
            <Search className="h-5 w-5" strokeWidth={1.5} />
            {isScanning ? 'Analizando...' : 'Auditar Mi Sitio Gratis'}
          </Button>

          {/* Micro-copy */}
          <div className="flex flex-wrap justify-center gap-x-4 gap-y-1 text-xs text-[var(--text-tertiary)]">
            <span>Sin instalar nada</span>
            <span>100% externo</span>
            <span>Resultados en 30 seg</span>
          </div>
        </form>
      </CardContent>
    </Card>
  )
}
