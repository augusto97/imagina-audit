import { useEffect, useState } from 'react'
import { useForm } from 'react-hook-form'
import { useNavigate, Link } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { motion } from 'framer-motion'
import { Loader2, LogIn, Mail, Lock } from 'lucide-react'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { useUser } from '@/hooks/useUser'
import { useConfigStore } from '@/store/configStore'

/**
 * Pantalla de login del usuario (cuentas creadas por el admin).
 * Flujo separado de /admin/login — mismo browser puede tener sesiones admin
 * y user en paralelo.
 */
export default function UserLoginPage() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const { login, isAuthenticated, isLoading } = useUser()
  const { logoUrl, companyName } = useConfigStore((s) => s.config)
  const [submitting, setSubmitting] = useState(false)
  const { register, handleSubmit } = useForm<{ email: string; password: string; website?: string }>()

  // Si ya estaba logged-in, mandarlo a /account
  useEffect(() => {
    if (!isLoading && isAuthenticated) navigate('/account', { replace: true })
  }, [isLoading, isAuthenticated, navigate])

  const onSubmit = async (data: { email: string; password: string; website?: string }) => {
    if (data.website) return // honeypot
    setSubmitting(true)
    try {
      const ok = await login(data.email, data.password)
      if (ok) navigate('/account', { replace: true })
      else toast.error(t('login.user_invalid'))
    } catch (err: unknown) {
      const axiosErr = err as { response?: { data?: { error?: string } } }
      toast.error(axiosErr.response?.data?.error || t('login.user_invalid'))
    } finally {
      setSubmitting(false)
    }
  }

  if (isLoading) {
    return (
      <div className="flex min-h-screen items-center justify-center bg-[#F4F6F8]">
        <Loader2 className="h-6 w-6 animate-spin text-[var(--accent-primary)]" />
      </div>
    )
  }

  return (
    <div className="relative flex min-h-screen items-center justify-center bg-[#F4F6F8] px-4">
      <div className="absolute inset-0 overflow-hidden pointer-events-none">
        <div className="absolute -top-40 -right-40 h-80 w-80 rounded-full bg-[var(--accent-primary)]/5 blur-3xl" />
        <div className="absolute -bottom-40 -left-40 h-80 w-80 rounded-full bg-[var(--accent-yellow)]/10 blur-3xl" />
      </div>

      <motion.div
        initial={{ opacity: 0, y: 30, scale: 0.96 }}
        animate={{ opacity: 1, y: 0, scale: 1 }}
        transition={{ duration: 0.35 }}
        className="relative z-10 w-full max-w-md rounded-2xl border border-[var(--border-default)] bg-white p-8 shadow-xl"
      >
        <div className="mb-6 flex flex-col items-center text-center">
          {logoUrl && (
            <img src={logoUrl} alt={companyName} className="mb-4 h-12 w-auto" />
          )}
          <h1 className="text-2xl font-bold text-[var(--text-primary)]">{t('login.user_title')}</h1>
          <p className="mt-1 text-sm text-[var(--text-secondary)]">{t('login.user_subtitle')}</p>
        </div>

        <form onSubmit={handleSubmit(onSubmit)} className="space-y-4">
          {/* Honeypot (invisible para humanos) */}
          <input type="text" {...register('website')} className="absolute left-[-9999px]" tabIndex={-1} autoComplete="off" />

          <div className="space-y-1.5">
            <label htmlFor="email" className="text-xs font-medium text-[var(--text-secondary)]">{t('login.user_email_label')}</label>
            <div className="relative">
              <Mail className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-[var(--text-tertiary)]" />
              <Input
                id="email"
                type="email"
                autoComplete="username"
                required
                placeholder={t('login.user_email_placeholder')}
                className="pl-9"
                {...register('email', { required: true })}
              />
            </div>
          </div>

          <div className="space-y-1.5">
            <label htmlFor="password" className="text-xs font-medium text-[var(--text-secondary)]">{t('login.user_password_label')}</label>
            <div className="relative">
              <Lock className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-[var(--text-tertiary)]" />
              <Input
                id="password"
                type="password"
                autoComplete="current-password"
                required
                placeholder={t('login.user_password_placeholder')}
                className="pl-9"
                {...register('password', { required: true })}
              />
            </div>
          </div>

          <Button type="submit" disabled={submitting} className="w-full">
            {submitting
              ? <Loader2 className="h-4 w-4 animate-spin" />
              : <LogIn className="h-4 w-4" />}
            {t('login.user_submit')}
          </Button>
        </form>

        <p className="mt-6 text-center text-xs text-[var(--text-tertiary)]">
          {t('login.user_no_account_hint')}
        </p>

        <div className="mt-4 text-center">
          <Link to="/" className="text-xs text-[var(--accent-primary)] hover:underline">
            ← {t('common.back')}
          </Link>
        </div>
      </motion.div>
    </div>
  )
}
