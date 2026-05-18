import { useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import {
  Loader2, Database, Server, HardDrive, ShieldCheck, CheckCircle2, AlertCircle,
  Mail, KeyRound, ArrowRight, ArrowLeft, Sparkles,
} from 'lucide-react'
import { Card, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import api from '@/lib/api'

/**
 * /setup — wizard de instalación inicial. Solo accesible cuando
 * /api/setup/status indica installed=false. Tres pasos:
 *
 *   1. Base de datos: elegir driver, probar conexión.
 *   2. Admin: email + password inicial.
 *   3. Revisión + install.
 *
 * El backend valida cada paso. El frontend solo gestiona la UX.
 */

type Driver = 'mysql' | 'sqlite'

interface DbConfig {
  driver: Driver
  host: string
  port: string
  name: string
  user: string
  password: string
  charset: string
  sqlitePath: string
}

interface AdminConfig {
  email: string
  password: string
  confirm: string
  name: string
}

export default function SetupPage() {
  const { t } = useTranslation()
  const navigate = useNavigate()

  const [step, setStep] = useState<1 | 2 | 3>(1)
  const [statusChecking, setStatusChecking] = useState(true)
  const [alreadyInstalled, setAlreadyInstalled] = useState(false)

  const [db, setDb] = useState<DbConfig>({
    driver: 'mysql',
    host: 'localhost',
    port: '3306',
    name: '',
    user: '',
    password: '',
    charset: 'utf8mb4',
    sqlitePath: '',
  })
  const [admin, setAdmin] = useState<AdminConfig>({
    email: '',
    password: '',
    confirm: '',
    name: '',
  })

  const [testing, setTesting] = useState(false)
  const [testResult, setTestResult] = useState<{ ok: boolean; message: string } | null>(null)
  const [installing, setInstalling] = useState(false)
  const [installError, setInstallError] = useState<string | null>(null)

  // Chequea si la app ya está instalada; si sí, manda al login.
  useEffect(() => {
    (async () => {
      try {
        const res = await api.get('/setup/status.php')
        const data = res.data?.data
        if (data?.installed) setAlreadyInstalled(true)
      } catch { /* silencioso */ }
      setStatusChecking(false)
    })()
  }, [])

  const handleTestDb = async () => {
    setTesting(true)
    setTestResult(null)
    try {
      const payload: Record<string, string> = { driver: db.driver }
      if (db.driver === 'mysql') {
        payload.host = db.host
        payload.port = db.port
        payload.name = db.name
        payload.user = db.user
        payload.password = db.password
        payload.charset = db.charset
      } else if (db.sqlitePath.trim() !== '') {
        payload.sqlitePath = db.sqlitePath
      }
      const res = await api.post('/setup/test-db.php', payload)
      const data = res.data?.data
      setTestResult({ ok: true, message: data?.version ?? 'Connected' })
    } catch (err: unknown) {
      const axiosErr = err as { response?: { data?: { error?: string } } }
      setTestResult({ ok: false, message: axiosErr.response?.data?.error ?? 'Connection failed' })
    }
    setTesting(false)
  }

  const handleInstall = async () => {
    setInstalling(true)
    setInstallError(null)
    try {
      const payload: Record<string, string> = {
        driver: db.driver,
        adminEmail: admin.email,
        adminPassword: admin.password,
        adminName: admin.name,
      }
      if (db.driver === 'mysql') {
        payload.host = db.host
        payload.port = db.port
        payload.name = db.name
        payload.user = db.user
        payload.password = db.password
        payload.charset = db.charset
      } else if (db.sqlitePath.trim() !== '') {
        payload.sqlitePath = db.sqlitePath
      }
      await api.post('/setup/install.php', payload)
      // Pequeño delay para que el usuario vea el success state antes de
      // navegar al login.
      setTimeout(() => navigate('/admin/login', { replace: true }), 1200)
    } catch (err: unknown) {
      const axiosErr = err as { response?: { data?: { error?: string } } }
      setInstallError(axiosErr.response?.data?.error ?? 'Install failed')
      setInstalling(false)
    }
  }

  if (statusChecking) {
    return (
      <div className="flex h-screen items-center justify-center bg-[var(--bg-secondary)]">
        <Loader2 className="h-8 w-8 animate-spin text-[var(--accent-primary)]" />
      </div>
    )
  }

  if (alreadyInstalled) {
    return (
      <div className="flex h-screen items-center justify-center bg-[var(--bg-secondary)] p-4">
        <Card className="max-w-md">
          <CardContent className="pt-6 text-center space-y-3">
            <ShieldCheck className="h-12 w-12 text-emerald-600 mx-auto" />
            <h1 className="text-xl font-bold">{t('setup.already_installed_title')}</h1>
            <p className="text-sm text-[var(--text-secondary)]">{t('setup.already_installed_body')}</p>
            <Button onClick={() => navigate('/admin/login', { replace: true })}>
              {t('setup.go_to_login')}
            </Button>
          </CardContent>
        </Card>
      </div>
    )
  }

  const canAdvanceStep1 = testResult?.ok === true
  const canAdvanceStep2 =
    admin.email.trim() !== '' &&
    /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(admin.email) &&
    admin.password.length >= 10 &&
    admin.password === admin.confirm

  return (
    <div className="min-h-screen bg-gradient-to-br from-[var(--bg-secondary)] via-[var(--bg-primary)] to-[var(--bg-secondary)] py-8 px-4">
      <div className="mx-auto max-w-2xl space-y-6">
        {/* Header */}
        <div className="text-center">
          <div className="inline-flex items-center justify-center h-12 w-12 rounded-xl bg-gradient-to-br from-[var(--accent-primary)] to-[#0a9db8] mb-3">
            <Sparkles className="h-6 w-6 text-white" strokeWidth={1.5} />
          </div>
          <h1 className="text-2xl font-bold text-[var(--text-primary)]">{t('setup.title')}</h1>
          <p className="text-sm text-[var(--text-secondary)] mt-1">{t('setup.subtitle')}</p>
        </div>

        {/* Step indicator */}
        <div className="flex items-center justify-center gap-2">
          {[1, 2, 3].map(n => (
            <div key={n} className="flex items-center">
              <div className={`h-7 w-7 rounded-full flex items-center justify-center text-xs font-bold ${
                step === n
                  ? 'bg-[var(--accent-primary)] text-white'
                  : step > n
                  ? 'bg-emerald-600 text-white'
                  : 'bg-[var(--bg-secondary)] text-[var(--text-tertiary)] border border-[var(--border-default)]'
              }`}>
                {step > n ? <CheckCircle2 className="h-4 w-4" /> : n}
              </div>
              {n < 3 && <div className={`h-px w-10 mx-1 ${step > n ? 'bg-emerald-600' : 'bg-[var(--border-default)]'}`} />}
            </div>
          ))}
        </div>

        {/* Step 1: Database */}
        {step === 1 && (
          <Card>
            <CardContent className="pt-6 space-y-4">
              <div>
                <h2 className="text-lg font-semibold flex items-center gap-2">
                  <Database className="h-5 w-5 text-[var(--accent-primary)]" />
                  {t('setup.step1_title')}
                </h2>
                <p className="text-xs text-[var(--text-secondary)] mt-1">{t('setup.step1_subtitle')}</p>
              </div>

              <div className="grid gap-2 sm:grid-cols-2">
                <DriverCard
                  active={db.driver === 'mysql'}
                  onClick={() => { setDb(d => ({ ...d, driver: 'mysql' })); setTestResult(null) }}
                  icon={<Server className="h-5 w-5" />}
                  title={t('setup.driver_mysql_title')}
                  body={t('setup.driver_mysql_body')}
                  recommended
                />
                <DriverCard
                  active={db.driver === 'sqlite'}
                  onClick={() => { setDb(d => ({ ...d, driver: 'sqlite' })); setTestResult(null) }}
                  icon={<HardDrive className="h-5 w-5" />}
                  title={t('setup.driver_sqlite_title')}
                  body={t('setup.driver_sqlite_body')}
                />
              </div>

              {db.driver === 'mysql' && (
                <div className="grid gap-3 sm:grid-cols-2 pt-2">
                  <Field label={t('setup.field_host')} value={db.host} onChange={v => setDb(d => ({ ...d, host: v }))} />
                  <Field label={t('setup.field_port')} value={db.port} onChange={v => setDb(d => ({ ...d, port: v }))} />
                  <Field label={t('setup.field_db_name')} value={db.name} onChange={v => setDb(d => ({ ...d, name: v }))} placeholder="imagina_audit" />
                  <Field label={t('setup.field_db_user')} value={db.user} onChange={v => setDb(d => ({ ...d, user: v }))} />
                  <Field label={t('setup.field_db_password')} value={db.password} onChange={v => setDb(d => ({ ...d, password: v }))} type="password" className="sm:col-span-2" />
                </div>
              )}

              {db.driver === 'sqlite' && (
                <div className="pt-2">
                  <Field
                    label={t('setup.field_sqlite_path')}
                    value={db.sqlitePath}
                    onChange={v => setDb(d => ({ ...d, sqlitePath: v }))}
                    placeholder={t('setup.field_sqlite_path_hint')}
                  />
                  <p className="text-[10px] text-[var(--text-tertiary)] mt-1">{t('setup.field_sqlite_path_help')}</p>
                </div>
              )}

              <div className="flex items-center gap-2 pt-2">
                <Button variant="outline" onClick={handleTestDb} disabled={testing}>
                  {testing ? <Loader2 className="h-4 w-4 animate-spin" /> : <Database className="h-4 w-4" />}
                  {t('setup.test_connection')}
                </Button>
                {testResult && (
                  <span className={`text-xs flex items-center gap-1.5 ${testResult.ok ? 'text-emerald-700' : 'text-red-600'}`}>
                    {testResult.ok ? <CheckCircle2 className="h-3.5 w-3.5" /> : <AlertCircle className="h-3.5 w-3.5" />}
                    {testResult.message}
                  </span>
                )}
              </div>

              <div className="flex justify-end pt-2">
                <Button onClick={() => setStep(2)} disabled={!canAdvanceStep1}>
                  {t('setup.next')}
                  <ArrowRight className="h-4 w-4" />
                </Button>
              </div>
            </CardContent>
          </Card>
        )}

        {/* Step 2: Admin */}
        {step === 2 && (
          <Card>
            <CardContent className="pt-6 space-y-4">
              <div>
                <h2 className="text-lg font-semibold flex items-center gap-2">
                  <ShieldCheck className="h-5 w-5 text-[var(--accent-primary)]" />
                  {t('setup.step2_title')}
                </h2>
                <p className="text-xs text-[var(--text-secondary)] mt-1">{t('setup.step2_subtitle')}</p>
              </div>

              <div className="space-y-3">
                <Field
                  label={t('setup.field_admin_email')}
                  value={admin.email}
                  onChange={v => setAdmin(a => ({ ...a, email: v }))}
                  type="email"
                  placeholder="admin@dominio.com"
                  icon={<Mail className="h-3.5 w-3.5" />}
                />
                <Field
                  label={t('setup.field_admin_name')}
                  value={admin.name}
                  onChange={v => setAdmin(a => ({ ...a, name: v }))}
                  placeholder={t('setup.field_admin_name_placeholder')}
                />
                <Field
                  label={t('setup.field_admin_password')}
                  value={admin.password}
                  onChange={v => setAdmin(a => ({ ...a, password: v }))}
                  type="password"
                  icon={<KeyRound className="h-3.5 w-3.5" />}
                />
                <Field
                  label={t('setup.field_admin_password_confirm')}
                  value={admin.confirm}
                  onChange={v => setAdmin(a => ({ ...a, confirm: v }))}
                  type="password"
                />
                <p className="text-[11px] text-[var(--text-tertiary)]">{t('setup.password_hint')}</p>
              </div>

              <div className="flex justify-between pt-2">
                <Button variant="ghost" onClick={() => setStep(1)}>
                  <ArrowLeft className="h-4 w-4" />
                  {t('setup.back')}
                </Button>
                <Button onClick={() => setStep(3)} disabled={!canAdvanceStep2}>
                  {t('setup.next')}
                  <ArrowRight className="h-4 w-4" />
                </Button>
              </div>
            </CardContent>
          </Card>
        )}

        {/* Step 3: Review + install */}
        {step === 3 && (
          <Card>
            <CardContent className="pt-6 space-y-4">
              <div>
                <h2 className="text-lg font-semibold flex items-center gap-2">
                  <Sparkles className="h-5 w-5 text-[var(--accent-primary)]" />
                  {t('setup.step3_title')}
                </h2>
                <p className="text-xs text-[var(--text-secondary)] mt-1">{t('setup.step3_subtitle')}</p>
              </div>

              <div className="rounded-lg border border-[var(--border-default)] divide-y divide-[var(--border-default)] text-sm">
                <ReviewRow label={t('setup.review_driver')} value={db.driver === 'mysql' ? 'MySQL / MariaDB' : 'SQLite'} />
                {db.driver === 'mysql' && (
                  <>
                    <ReviewRow label={t('setup.review_host')} value={`${db.host}:${db.port}`} />
                    <ReviewRow label={t('setup.review_database')} value={db.name} />
                    <ReviewRow label={t('setup.review_user')} value={db.user} />
                  </>
                )}
                <ReviewRow label={t('setup.review_admin_email')} value={admin.email} />
                {admin.name && <ReviewRow label={t('setup.review_admin_name')} value={admin.name} />}
              </div>

              {installError && (
                <div className="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700 flex items-start gap-2">
                  <AlertCircle className="h-3.5 w-3.5 mt-0.5 shrink-0" />
                  <span>{installError}</span>
                </div>
              )}

              <div className="flex justify-between pt-2">
                <Button variant="ghost" onClick={() => setStep(2)} disabled={installing}>
                  <ArrowLeft className="h-4 w-4" />
                  {t('setup.back')}
                </Button>
                <Button onClick={handleInstall} disabled={installing}>
                  {installing ? <Loader2 className="h-4 w-4 animate-spin" /> : <Sparkles className="h-4 w-4" />}
                  {installing ? t('setup.installing') : t('setup.install')}
                </Button>
              </div>
            </CardContent>
          </Card>
        )}
      </div>
    </div>
  )
}

function DriverCard({ active, onClick, icon, title, body, recommended }: {
  active: boolean
  onClick: () => void
  icon: React.ReactNode
  title: string
  body: string
  recommended?: boolean
}) {
  return (
    <button
      type="button"
      onClick={onClick}
      className={`text-left rounded-lg border px-4 py-3 transition-colors ${
        active
          ? 'border-[var(--accent-primary)] bg-[var(--accent-primary)]/5'
          : 'border-[var(--border-default)] hover:bg-[var(--bg-secondary)]'
      }`}
    >
      <div className="flex items-center gap-2 mb-1">
        <span className={active ? 'text-[var(--accent-primary)]' : 'text-[var(--text-tertiary)]'}>{icon}</span>
        <span className="font-semibold text-sm">{title}</span>
        {recommended && (
          <span className="text-[9px] uppercase font-bold tracking-wider text-emerald-700 bg-emerald-100 px-1.5 py-0.5 rounded">
            ★
          </span>
        )}
      </div>
      <p className="text-xs text-[var(--text-secondary)]">{body}</p>
    </button>
  )
}

function Field({
  label, value, onChange, type = 'text', placeholder, icon, className,
}: {
  label: string
  value: string
  onChange: (v: string) => void
  type?: string
  placeholder?: string
  icon?: React.ReactNode
  className?: string
}) {
  return (
    <div className={className}>
      <Label className="text-xs flex items-center gap-1.5">{icon}{label}</Label>
      <Input
        type={type}
        value={value}
        onChange={e => onChange(e.target.value)}
        placeholder={placeholder}
        className="mt-1"
      />
    </div>
  )
}

function ReviewRow({ label, value }: { label: string; value: string }) {
  return (
    <div className="flex items-center justify-between px-3 py-2">
      <span className="text-xs text-[var(--text-tertiary)]">{label}</span>
      <span className="font-mono text-xs">{value}</span>
    </div>
  )
}
