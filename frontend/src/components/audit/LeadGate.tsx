import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Lock, Mail, User, Phone, Loader2, Unlock } from 'lucide-react'
import { toast } from 'sonner'
import { Card, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { captureLead } from '@/lib/api'

interface LeadGateProps {
  auditId: string
  requireEmail: boolean
  requireName: boolean
  requireWhatsapp: boolean
  /** Score global, para reforzar el enganche en el copy del gate. */
  score: number
  onUnlock: () => void
}

/**
 * Tarjeta de captura que flota sobre el informe difuminado en modo 'gated'.
 * El prospecto ya vio su score (arriba, sin difuminar) y aquí entrega su
 * contacto para ver el detalle + el plan de soluciones.
 */
export default function LeadGate({ auditId, requireEmail, requireName, requireWhatsapp, score, onUnlock }: LeadGateProps) {
  const { t } = useTranslation()
  const [email, setEmail] = useState('')
  const [name, setName] = useState('')
  const [whatsapp, setWhatsapp] = useState('')
  const [submitting, setSubmitting] = useState(false)

  const submit = async (e: React.FormEvent) => {
    e.preventDefault()
    // Validación cliente — el backend revalida igual.
    if (requireEmail && !email.trim()) { toast.error(t('public.gate_email_required')); return }
    if (email.trim() && !/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(email.trim())) { toast.error(t('public.gate_email_invalid')); return }
    if (requireName && !name.trim()) { toast.error(t('public.gate_name_required')); return }
    if (requireWhatsapp && !whatsapp.trim()) { toast.error(t('public.gate_whatsapp_required')); return }

    setSubmitting(true)
    try {
      await captureLead({
        auditId,
        leadEmail: email.trim() || undefined,
        leadName: name.trim() || undefined,
        leadWhatsapp: whatsapp.trim() || undefined,
      })
      // Recordar en este navegador que ya desbloqueó este audit, para que al
      // recargar la página no le vuelva a pedir los datos.
      try { localStorage.setItem(`unlocked_${auditId}`, '1') } catch { /* private mode */ }
      toast.success(t('public.gate_unlocked'))
      onUnlock()
    } catch (err) {
      const e2 = err as { response?: { data?: { error?: string } } }
      toast.error(e2.response?.data?.error || t('public.gate_error'))
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <Card className="mx-auto w-full max-w-md border-[var(--accent-primary)]/40 shadow-2xl">
      <CardContent className="p-6 sm:p-8">
        <div className="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-[var(--accent-primary)]/10">
          <Lock className="h-7 w-7 text-[var(--accent-primary)]" strokeWidth={1.5} />
        </div>
        <h3 className="text-center text-xl font-bold text-[var(--text-primary)]">
          {t('public.gate_title')}
        </h3>
        <p className="mt-2 text-center text-sm text-[var(--text-secondary)]">
          {t('public.gate_subtitle', { score })}
        </p>

        <form onSubmit={submit} className="mt-6 space-y-3">
          <div className="relative">
            <Mail className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[var(--text-tertiary)]" strokeWidth={1.5} />
            <Input
              type="email"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              placeholder={requireEmail ? t('public.gate_email_ph_required') : t('public.gate_email_ph')}
              className="pl-10"
              disabled={submitting}
              autoFocus
            />
          </div>
          {(requireName || true) && (
            <div className="relative">
              <User className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[var(--text-tertiary)]" strokeWidth={1.5} />
              <Input
                value={name}
                onChange={(e) => setName(e.target.value)}
                placeholder={requireName ? t('public.gate_name_ph_required') : t('public.gate_name_ph')}
                className="pl-10"
                disabled={submitting}
              />
            </div>
          )}
          <div className="relative">
            <Phone className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[var(--text-tertiary)]" strokeWidth={1.5} />
            <Input
              value={whatsapp}
              onChange={(e) => setWhatsapp(e.target.value)}
              placeholder={requireWhatsapp ? t('public.gate_whatsapp_ph_required') : t('public.gate_whatsapp_ph')}
              className="pl-10"
              disabled={submitting}
            />
          </div>

          <Button type="submit" size="xl" className="w-full" disabled={submitting}>
            {submitting ? <Loader2 className="h-5 w-5 animate-spin" /> : <Unlock className="h-5 w-5" strokeWidth={1.5} />}
            {t('public.gate_button')}
          </Button>
        </form>

        <p className="mt-3 text-center text-[11px] text-[var(--text-tertiary)]">
          {t('public.gate_privacy')}
        </p>
      </CardContent>
    </Card>
  )
}
