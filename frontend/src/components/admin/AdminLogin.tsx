import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { Lock, Shield, Loader2, ArrowRight, Key } from 'lucide-react'
import { motion } from 'framer-motion'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { useAuth } from '@/hooks/useAuth'
import { useConfigStore } from '@/store/configStore'

/**
 * Login admin. El "needs setup" check vive arriba en SetupGate (App.tsx):
 * si la app no está instalada, el SetupGate redirige a /setup ANTES de
 * que esta página se renderice. Por eso aquí solo manejamos login + 2FA.
 */
export default function AdminLogin() {
  const { login, verify2fa } = useAuth()
  const [loading, setLoading] = useState(false)
  const [needs2fa, setNeeds2fa] = useState(false)

  if (needs2fa) return <TwoFaStep verify={verify2fa} loading={loading} setLoading={setLoading} onCancel={() => setNeeds2fa(false)} />

  return <LoginForm login={login} loading={loading} setLoading={setLoading} onNeeds2fa={() => setNeeds2fa(true)} />
}

// ─── Login normal ──────────────────────────────────────────────────
function LoginForm({
  login,
  loading,
  setLoading,
  onNeeds2fa,
}: {
  login: (pass: string) => Promise<{ needs2fa: boolean }>
  loading: boolean
  setLoading: (v: boolean) => void
  onNeeds2fa: () => void
}) {
  const { register, handleSubmit } = useForm<{ password: string; website?: string }>()
  const { logoUrl, companyName } = useConfigStore((s) => s.config)
  const { t } = useTranslation()

  const onSubmit = async (data: { password: string; website?: string }) => {
    if (data.website) return   // honeypot
    setLoading(true)
    try {
      const res = await login(data.password)
      if (res.needs2fa) onNeeds2fa()
    } catch (err: unknown) {
      const axiosErr = err as { response?: { data?: { error?: string }; status?: number } }
      const msg = axiosErr.response?.data?.error || t('login.wrong_password')
      toast.error(msg)
    } finally {
      setLoading(false)
    }
  }

  return (
    <div className="flex min-h-screen items-center justify-center bg-[#F4F6F8] px-4">
      <div className="absolute inset-0 overflow-hidden pointer-events-none">
        <div className="absolute -top-40 -right-40 h-80 w-80 rounded-full bg-[var(--accent-primary)]/5 blur-3xl" />
        <div className="absolute -bottom-40 -left-40 h-80 w-80 rounded-full bg-[var(--accent-yellow)]/10 blur-3xl" />
      </div>

      <motion.div
        initial={{ opacity: 0, y: 30, scale: 0.96 }}
        animate={{ opacity: 1, y: 0, scale: 1 }}
        transition={{ duration: 0.5, ease: [0.22, 1, 0.36, 1] }}
        className="relative w-full max-w-sm"
      >
        <div className="rounded-2xl border border-[var(--border-default)] bg-white p-8 shadow-xl shadow-black/[0.03]">
          <div className="mb-8 flex flex-col items-center gap-3">
            {logoUrl ? (
              <img src={logoUrl} alt={companyName || 'Logo'} className="h-14 max-w-[200px] object-contain" />
            ) : (
              <div className="flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-[var(--accent-primary)] to-[#0a9db8] shadow-lg shadow-[var(--accent-primary)]/25">
                <Shield className="h-7 w-7 text-white" strokeWidth={2} />
              </div>
            )}
            <div className="text-center">
              <h1 className="text-xl font-bold text-[var(--text-primary)]">{t('login.welcome')}</h1>
              <p className="text-sm text-[var(--text-tertiary)] mt-0.5">{t('login.admin_subtitle')}</p>
            </div>
          </div>

          <form onSubmit={handleSubmit(onSubmit)} className="space-y-5" autoComplete="off">
            {/* Honeypot */}
            <div style={{ position: 'absolute', left: '-9999px', top: 0 }} aria-hidden="true">
              <label>
                Website (leave empty)
                <input
                  type="text"
                  tabIndex={-1}
                  autoComplete="off"
                  {...register('website')}
                />
              </label>
            </div>
            <div className="space-y-1.5">
              <label className="text-xs font-medium text-[var(--text-secondary)]">{t('login.password_label')}</label>
              <div className="relative">
                <Lock className="absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-[var(--text-tertiary)]" strokeWidth={1.5} />
                <Input
                  {...register('password', { required: true })}
                  type="password"
                  placeholder={t('login.password_placeholder')}
                  className="h-11 pl-10 bg-[var(--bg-secondary)] border-[var(--border-default)]"
                  autoFocus
                  disabled={loading}
                />
              </div>
            </div>
            <Button type="submit" className="w-full h-11" disabled={loading}>
              {loading ? <Loader2 className="h-4 w-4 animate-spin" /> : (<>{t('login.submit')} <ArrowRight className="h-4 w-4 ml-1" strokeWidth={1.5} /></>)}
            </Button>
          </form>

          <p className="mt-6 text-center text-[11px] text-[var(--text-tertiary)]">
            {companyName || 'Imagina Audit'} &middot; {t('nav.admin_panel')}
          </p>
        </div>
      </motion.div>
    </div>
  )
}

// ─── 2FA step (segundo factor tras password correcta) ──────────────

function TwoFaStep({
  verify, loading, setLoading, onCancel,
}: {
  verify: (code: string) => Promise<{ usedRecovery: boolean }>
  loading: boolean
  setLoading: (v: boolean) => void
  onCancel: () => void
}) {
  const { register, handleSubmit, setValue, watch } = useForm<{ code: string }>()
  const { logoUrl, companyName } = useConfigStore((s) => s.config)
  const { t } = useTranslation()
  const code = watch('code', '')

  const onSubmit = async (data: { code: string }) => {
    setLoading(true)
    try {
      const res = await verify(data.code.trim())
      if (res.usedRecovery) {
        toast.warning(t('login.twofa_used_recovery'), { duration: 8000 })
      }
    } catch (err: unknown) {
      const axiosErr = err as { response?: { data?: { error?: string } } }
      toast.error(axiosErr.response?.data?.error || t('login.twofa_invalid'))
      setValue('code', '')
    } finally {
      setLoading(false)
    }
  }

  return (
    <div className="flex min-h-screen items-center justify-center bg-[#F4F6F8] px-4">
      <div className="absolute inset-0 overflow-hidden pointer-events-none">
        <div className="absolute -top-40 -right-40 h-80 w-80 rounded-full bg-[var(--accent-primary)]/5 blur-3xl" />
      </div>

      <motion.div
        initial={{ opacity: 0, y: 30, scale: 0.96 }}
        animate={{ opacity: 1, y: 0, scale: 1 }}
        transition={{ duration: 0.45, ease: [0.22, 1, 0.36, 1] }}
        className="relative w-full max-w-sm"
      >
        <div className="rounded-2xl border border-[var(--border-default)] bg-white p-8 shadow-xl shadow-black/[0.03]">
          <div className="mb-6 flex flex-col items-center gap-3">
            {logoUrl ? (
              <img src={logoUrl} alt={companyName || 'Logo'} className="h-12 max-w-[200px] object-contain opacity-80" />
            ) : (
              <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-gradient-to-br from-emerald-500 to-emerald-600 shadow-lg shadow-emerald-500/25">
                <Key className="h-6 w-6 text-white" strokeWidth={2} />
              </div>
            )}
            <div className="text-center">
              <h1 className="text-xl font-bold text-[var(--text-primary)]">{t('login.twofa_title')}</h1>
              <p className="text-sm text-[var(--text-tertiary)] mt-0.5">{t('login.twofa_subtitle')}</p>
            </div>
          </div>

          <form onSubmit={handleSubmit(onSubmit)} className="space-y-4" autoComplete="off">
            <Input
              {...register('code', { required: true })}
              placeholder={t('login.twofa_placeholder')}
              maxLength={10}
              inputMode="numeric"
              autoFocus
              disabled={loading}
              className="h-12 text-center font-mono text-xl tracking-[0.4em]"
            />
            <Button type="submit" className="w-full h-11" disabled={loading || code.trim().length < 6}>
              {loading ? <Loader2 className="h-4 w-4 animate-spin" /> : (<>{t('login.twofa_submit')} <ArrowRight className="h-4 w-4 ml-1" strokeWidth={1.5} /></>)}
            </Button>
            <Button type="button" variant="ghost" className="w-full h-9 text-xs" onClick={onCancel} disabled={loading}>
              {t('login.twofa_back')}
            </Button>
          </form>

          <p className="mt-5 text-center text-[11px] text-[var(--text-tertiary)]">
            {t('login.twofa_recovery_hint')}
          </p>
        </div>
      </motion.div>
    </div>
  )
}
