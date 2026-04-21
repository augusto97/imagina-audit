import { useEffect, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { QRCodeSVG } from 'qrcode.react'
import { Loader2, ShieldCheck, ShieldOff, Copy, Check, AlertTriangle, KeyRound, RefreshCw } from 'lucide-react'
import { toast } from 'sonner'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Badge } from '@/components/ui/badge'
import { Skeleton } from '@/components/ui/skeleton'
import { useAdmin } from '@/hooks/useAdmin'

/**
 * /admin/security — gestión del 2FA TOTP del admin.
 *
 * Estados:
 *   - desactivado: botón "Activar 2FA" arranca flujo (QR + verificación)
 *   - activo: botones para regenerar recovery codes o desactivar
 */
export default function Settings2FA() {
  const { t } = useTranslation()
  const { fetch2faStatus, setup2fa, enable2fa, disable2fa, regenerateRecoveryCodes } = useAdmin()
  const [loading, setLoading] = useState(true)
  const [enabled, setEnabled] = useState(false)
  const [codesLeft, setCodesLeft] = useState(0)

  // Setup flow state
  const [setupData, setSetupData] = useState<{ secret: string; otpauthUri: string } | null>(null)
  const [setupCode, setSetupCode] = useState('')
  const [setupLoading, setSetupLoading] = useState(false)
  const [newRecoveryCodes, setNewRecoveryCodes] = useState<string[] | null>(null)
  const [recoveryAck, setRecoveryAck] = useState(false)

  // Disable flow state
  const [disableOpen, setDisableOpen] = useState(false)
  const [disablePassword, setDisablePassword] = useState('')
  const [disableCode, setDisableCode] = useState('')
  const [disableLoading, setDisableLoading] = useState(false)

  // Regenerate recovery
  const [regenOpen, setRegenOpen] = useState(false)
  const [regenCode, setRegenCode] = useState('')
  const [regenLoading, setRegenLoading] = useState(false)

  const [copiedSecret, setCopiedSecret] = useState(false)

  const loadStatus = async () => {
    setLoading(true)
    const s = await fetch2faStatus()
    if (s) { setEnabled(s.enabled); setCodesLeft(s.recoveryCodesLeft) }
    setLoading(false)
  }

  useEffect(() => { loadStatus() /* eslint-disable-next-line react-hooks/exhaustive-deps */ }, [])

  const startSetup = async () => {
    setSetupLoading(true)
    try {
      const data = await setup2fa()
      if (data) setSetupData({ secret: data.secret, otpauthUri: data.otpauthUri })
    } catch { toast.error(t('settings.twofa_setup_start_error')) }
    setSetupLoading(false)
  }

  const finishEnable = async () => {
    if (!setupData) return
    setSetupLoading(true)
    try {
      const res = await enable2fa(setupData.secret, setupCode)
      setNewRecoveryCodes(res.recoveryCodes)
      toast.success(t('settings.twofa_enabled_toast'))
    } catch (err: unknown) {
      const msg = (err as { response?: { data?: { error?: string } } })?.response?.data?.error || t('settings.twofa_setup_wrong_code')
      toast.error(msg)
    }
    setSetupLoading(false)
  }

  const finishSetup = async () => {
    setSetupData(null); setSetupCode(''); setNewRecoveryCodes(null); setRecoveryAck(false)
    await loadStatus()
  }

  const doDisable = async () => {
    setDisableLoading(true)
    try {
      await disable2fa(disablePassword, disableCode)
      toast.success(t('settings.twofa_disabled_toast'))
      setDisableOpen(false); setDisablePassword(''); setDisableCode('')
      await loadStatus()
    } catch (err: unknown) {
      const msg = (err as { response?: { data?: { error?: string } } })?.response?.data?.error || t('settings.save_error')
      toast.error(msg)
    }
    setDisableLoading(false)
  }

  const doRegen = async () => {
    setRegenLoading(true)
    try {
      const res = await regenerateRecoveryCodes(regenCode)
      setNewRecoveryCodes(res.recoveryCodes)
      setRegenOpen(false); setRegenCode('')
    } catch (err: unknown) {
      const msg = (err as { response?: { data?: { error?: string } } })?.response?.data?.error || t('settings.save_error')
      toast.error(msg)
    }
    setRegenLoading(false)
  }

  const copySecret = async () => {
    if (!setupData) return
    await navigator.clipboard.writeText(setupData.secret)
    setCopiedSecret(true)
    setTimeout(() => setCopiedSecret(false), 2000)
  }

  if (loading) return <Skeleton className="h-96 rounded-2xl" />

  // Vista de recovery codes (aparece justo después de activar o regenerar)
  if (newRecoveryCodes !== null) {
    return <RecoveryCodesView codes={newRecoveryCodes} ack={recoveryAck} setAck={setRecoveryAck} onDone={finishSetup} />
  }

  // Flujo de setup (QR + verificación)
  if (setupData) {
    return (
      <SetupView
        data={setupData}
        code={setupCode}
        setCode={setSetupCode}
        loading={setupLoading}
        copiedSecret={copiedSecret}
        onCopy={copySecret}
        onCancel={() => { setSetupData(null); setSetupCode('') }}
        onConfirm={finishEnable}
      />
    )
  }

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-[var(--text-primary)]">{t('settings.twofa_title')}</h1>
        <p className="mt-1 text-sm text-[var(--text-secondary)]">
          {t('settings.twofa_subtitle')}
        </p>
      </div>

      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2 text-base">
            {enabled
              ? <><ShieldCheck className="h-5 w-5 text-emerald-600" strokeWidth={1.5} /> {t('settings.twofa_on')} <Badge variant="success" className="ml-1 text-[10px]">ON</Badge></>
              : <><ShieldOff className="h-5 w-5 text-[var(--text-tertiary)]" strokeWidth={1.5} /> {t('settings.twofa_off')} <Badge variant="secondary" className="ml-1 text-[10px]">OFF</Badge></>}
          </CardTitle>
        </CardHeader>
        <CardContent className="space-y-4">
          {enabled ? (
            <>
              <div className="rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm">
                <p className="font-medium text-emerald-900">{t('settings.twofa_on_ok_title')}</p>
                <p className="mt-0.5 text-xs text-emerald-800" dangerouslySetInnerHTML={{ __html: t('settings.twofa_on_ok_body', { count: codesLeft }) }} />
              </div>
              <div className="flex flex-wrap gap-2">
                <Button variant="outline" onClick={() => setRegenOpen(!regenOpen)}>
                  <RefreshCw className="h-4 w-4" strokeWidth={1.5} /> {t('settings.twofa_regenerate')}
                </Button>
                <Button variant="destructive" onClick={() => setDisableOpen(!disableOpen)}>
                  <ShieldOff className="h-4 w-4" strokeWidth={1.5} /> {t('settings.twofa_disable')}
                </Button>
              </div>

              {regenOpen && (
                <div className="rounded-lg border border-[var(--border-default)] p-3 space-y-2">
                  <Label className="text-xs">{t('settings.twofa_regen_label')}</Label>
                  <Input
                    value={regenCode}
                    onChange={(e) => setRegenCode(e.target.value)}
                    placeholder="000000"
                    maxLength={6}
                    inputMode="numeric"
                    className="font-mono tracking-widest max-w-[120px]"
                  />
                  <Button size="sm" onClick={doRegen} disabled={regenLoading || regenCode.length < 6}>
                    {regenLoading ? <Loader2 className="h-4 w-4 animate-spin" /> : t('settings.twofa_regenerate_btn')}
                  </Button>
                </div>
              )}

              {disableOpen && (
                <div className="rounded-lg border border-red-200 bg-red-50 p-3 space-y-2">
                  <Label className="text-xs text-red-900">{t('settings.twofa_disable_label')}</Label>
                  <div className="flex flex-wrap gap-2">
                    <Input type="password" value={disablePassword} onChange={(e) => setDisablePassword(e.target.value)} placeholder={t('settings.twofa_password_placeholder')} className="max-w-[240px]" />
                    <Input value={disableCode} onChange={(e) => setDisableCode(e.target.value)} placeholder="000000" className="font-mono tracking-widest max-w-[120px]" />
                  </div>
                  <Button size="sm" variant="destructive" onClick={doDisable} disabled={disableLoading || !disablePassword || !disableCode}>
                    {disableLoading ? <Loader2 className="h-4 w-4 animate-spin" /> : t('settings.twofa_confirm_disable')}
                  </Button>
                </div>
              )}
            </>
          ) : (
            <div className="space-y-3">
              <div className="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm">
                <div className="flex items-start gap-2">
                  <AlertTriangle className="h-4 w-4 text-amber-600 mt-0.5" />
                  <div>
                    <p className="font-medium text-amber-900">{t('settings.twofa_recommended')}</p>
                    <p className="mt-0.5 text-xs text-amber-800">
                      {t('settings.twofa_recommended_body')}
                    </p>
                  </div>
                </div>
              </div>
              <Button onClick={startSetup} disabled={setupLoading}>
                {setupLoading ? <Loader2 className="h-4 w-4 animate-spin" /> : <><KeyRound className="h-4 w-4" strokeWidth={1.5} /> {t('settings.twofa_activate')}</>}
              </Button>
            </div>
          )}
        </CardContent>
      </Card>
    </div>
  )
}

// ─── Vista de setup (QR + verificación) ─────────────────────────────

function SetupView({
  data, code, setCode, loading, copiedSecret, onCopy, onCancel, onConfirm,
}: {
  data: { secret: string; otpauthUri: string }
  code: string
  setCode: (s: string) => void
  loading: boolean
  copiedSecret: boolean
  onCopy: () => void
  onCancel: () => void
  onConfirm: () => void
}) {
  const { t } = useTranslation()
  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-[var(--text-primary)]">{t('settings.twofa_setup_title')}</h1>
        <p className="mt-1 text-sm text-[var(--text-secondary)]">{t('settings.twofa_setup_subtitle')}</p>
      </div>

      <Card>
        <CardContent className="space-y-5 py-6">
          <div className="grid gap-6 lg:grid-cols-2 items-start">
            {/* QR */}
            <div className="flex flex-col items-center gap-3 rounded-lg border border-[var(--border-default)] bg-white p-4">
              <QRCodeSVG value={data.otpauthUri} size={200} level="M" />
              <p className="text-[10px] text-[var(--text-tertiary)]">{t('settings.twofa_setup_qr_note')}</p>
            </div>

            {/* Manual entry */}
            <div className="space-y-4">
              <div>
                <Label className="text-xs">{t('settings.twofa_setup_manual')}</Label>
                <div className="mt-1 flex items-center gap-2">
                  <code className="flex-1 rounded border border-[var(--border-default)] bg-[var(--bg-secondary)] px-2 py-1.5 font-mono text-xs break-all">
                    {data.secret}
                  </code>
                  <Button size="sm" variant="outline" onClick={onCopy}>
                    {copiedSecret ? <Check className="h-3.5 w-3.5 text-emerald-600" /> : <Copy className="h-3.5 w-3.5" />}
                  </Button>
                </div>
                <p className="mt-1 text-[10px] text-[var(--text-tertiary)]">{t('settings.twofa_setup_manual_hint')}</p>
              </div>

              <div>
                <Label className="text-xs">{t('settings.twofa_setup_code_label')}</Label>
                <Input
                  value={code}
                  onChange={(e) => setCode(e.target.value.replace(/\D/g, ''))}
                  placeholder="000000"
                  maxLength={6}
                  inputMode="numeric"
                  className="mt-1 font-mono tracking-[0.3em] text-lg h-11 max-w-[200px]"
                  autoFocus
                />
              </div>

              <div className="flex gap-2">
                <Button onClick={onConfirm} disabled={loading || code.length < 6}>
                  {loading ? <Loader2 className="h-4 w-4 animate-spin" /> : t('settings.twofa_setup_confirm')}
                </Button>
                <Button variant="ghost" onClick={onCancel} disabled={loading}>{t('settings.twofa_setup_cancel')}</Button>
              </div>
            </div>
          </div>
        </CardContent>
      </Card>
    </div>
  )
}

// ─── Vista de recovery codes (se muestra una vez) ───────────────────

function RecoveryCodesView({
  codes, ack, setAck, onDone,
}: { codes: string[]; ack: boolean; setAck: (v: boolean) => void; onDone: () => void }) {
  const { t } = useTranslation()
  const copyAll = async () => {
    await navigator.clipboard.writeText(codes.join('\n'))
    toast.success(t('settings.twofa_recovery_copied'))
  }

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-[var(--text-primary)]">{t('settings.twofa_recovery_title')}</h1>
        <p className="mt-1 text-sm text-[var(--text-secondary)]">
          {t('settings.twofa_recovery_body_1')}
          <b className="text-red-600"> {t('settings.twofa_recovery_body_2')}</b> {t('settings.twofa_recovery_body_3')}
        </p>
      </div>

      <Card className="border-amber-200 bg-amber-50/50">
        <CardContent className="py-6 space-y-4">
          <div className="grid grid-cols-2 gap-2 sm:grid-cols-4">
            {codes.map((c) => (
              <code key={c} className="rounded border border-amber-300 bg-white px-2 py-1.5 text-center font-mono text-xs">{c}</code>
            ))}
          </div>
          <div className="flex flex-wrap gap-2">
            <Button variant="outline" onClick={copyAll}>
              <Copy className="h-4 w-4" strokeWidth={1.5} /> {t('settings.twofa_recovery_copy_all')}
            </Button>
            <Button variant="outline" onClick={() => {
              const blob = new Blob([codes.join('\n') + '\n'], { type: 'text/plain' })
              const url = URL.createObjectURL(blob)
              const a = document.createElement('a')
              a.href = url
              a.download = 'imagina-audit-recovery-codes.txt'
              a.click()
              URL.revokeObjectURL(url)
            }}>
              {t('settings.twofa_recovery_download')}
            </Button>
          </div>
        </CardContent>
      </Card>

      <label className="flex items-start gap-2 text-sm">
        <input type="checkbox" checked={ack} onChange={(e) => setAck(e.target.checked)} className="mt-1 h-4 w-4" />
        <span>{t('settings.twofa_recovery_ack')}</span>
      </label>

      <Button disabled={!ack} onClick={onDone}>{t('settings.twofa_recovery_done')}</Button>
    </div>
  )
}
