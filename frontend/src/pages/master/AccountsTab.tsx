import { Plus } from 'lucide-react'
import DepartmentCombobox from '../../components/DepartmentCombobox'
import { useQueryClient } from '@tanstack/react-query'
import { useState, type FormEvent } from 'react'
import { Badge } from '../../components/ui/Badge'
import Button from '../../components/ui/Button'
import ConfirmDialog from '../../components/ui/ConfirmDialog'
import { FormField, Input } from '../../components/ui/FormField'
import Modal from '../../components/ui/Modal'
import PasswordInput from '../../components/ui/PasswordInput'
import { LoadingLabel, Skeleton } from '../../components/ui/Skeleton'
import { useToast } from '../../components/ui/toast-context'
import { api, errorMessage, fieldErrors } from '../../lib/api'
import { getUser, loginId } from '../../lib/auth'
import { useAccounts, type Account } from '../../lib/queries/accounts'
import { queryKeys } from '../../lib/queries/keys'
import { useDepartments } from '../../lib/queries/master'
import { PASSWORD_MIN, passwordErrors } from '../../lib/password'
import { useErrorToast } from '../../lib/useErrorToast'

type Action = { kind: 'reset' | 'deactivate' | 'restore'; account: Account } | { kind: 'password'; account: Account } | { kind: 'create' }

/**
 * Data Master > Akun (T33, SPEC §15). Akun pegawai SHD dibuat otomatis saat login pertama dengan NIK;
 * di sini staf menambah akun bersama, reset/ubah password, serta menonaktifkan/mengaktifkan kembali.
 */
export default function AccountsTab() {
  const queryClient = useQueryClient()
  const accounts = useAccounts()
  useErrorToast(accounts.error)
  const me = getUser()
  const [action, setAction] = useState<Action | null>(null)
  const [showInactive, setShowInactive] = useState(false)

  const done = () => {
    setAction(null)
    queryClient.invalidateQueries({ queryKey: queryKeys.accounts.all })
  }

  const rows = (accounts.data ?? []).filter((a) => showInactive || a.is_active)
  const inactiveCount = (accounts.data ?? []).filter((a) => !a.is_active).length

  return (
    <>
      <div className="mb-3 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <p className="text-sm text-slate-500">
          Akun pegawai SHD terbentuk otomatis saat pegawai pertama kali masuk menggunakan NIK.
        </p>
        <Button variant="secondary" onClick={() => setAction({ kind: 'create' })}>
          <Plus size={16} /> Tambah akun
        </Button>
      </div>

      {accounts.isPending ? (
        <ul className="divide-y divide-slate-100" aria-busy="true">
          <LoadingLabel />
          {Array.from({ length: 4 }, (_, i) => (
            <li key={i} className="flex items-center justify-between gap-3 py-3.5">
              <Skeleton className="h-4 w-48" />
              <Skeleton className="h-4 w-24" />
            </li>
          ))}
        </ul>
      ) : (
        <ul className="divide-y divide-slate-100">
          {rows.map((a) => {
            const isMe = a.id === me?.id
            return (
              <li key={a.id} className="flex flex-col gap-2 py-3 text-sm sm:flex-row sm:items-center sm:justify-between">
                <div className="min-w-0">
                  <p className="flex flex-wrap items-center gap-2">
                    <span className={`font-medium ${a.is_active ? '' : 'text-slate-500'}`}>{a.name}</span>
                    {isMe && <Badge color="blue">Anda</Badge>}
                    {!a.is_active && <Badge color="red">Nonaktif</Badge>}
                  </p>
                  <p className="mt-0.5 text-xs text-slate-500">
                    <span className="font-mono">{loginId(a)}</span>
                    {a.department ? ` · ${a.department}` : ''}
                  </p>
                </div>
                <div className="flex shrink-0 flex-wrap gap-3">
                  {a.is_active ? (
                    <>
                      {a.source === 'api' && !isMe && (
                        <button className="tap font-semibold text-brand-700 hover:underline" onClick={() => setAction({ kind: 'reset', account: a })}>
                          Reset password
                        </button>
                      )}
                      {a.source === 'manual' && (
                        <button className="tap font-semibold text-brand-700 hover:underline" onClick={() => setAction({ kind: 'password', account: a })}>
                          Ubah password
                        </button>
                      )}
                      {!isMe && (
                        <button className="tap font-semibold text-bad-700 hover:underline" onClick={() => setAction({ kind: 'deactivate', account: a })}>
                          Nonaktifkan
                        </button>
                      )}
                    </>
                  ) : (
                    <button className="tap font-semibold text-brand-700 hover:underline" onClick={() => setAction({ kind: 'restore', account: a })}>
                      Aktifkan kembali
                    </button>
                  )}
                </div>
              </li>
            )
          })}
        </ul>
      )}

      {inactiveCount > 0 && (
        <label className="mt-3 flex items-center gap-2 text-sm text-slate-600">
          <input type="checkbox" checked={showInactive} onChange={(e) => setShowInactive(e.target.checked)} />
          Tampilkan akun nonaktif ({inactiveCount})
        </label>
      )}

      {action?.kind === 'create' && <SharedAccountModal onClose={() => setAction(null)} onDone={done} />}
      {action?.kind === 'password' && <SharedPasswordModal account={action.account} onClose={() => setAction(null)} onDone={done} />}
      {action && (action.kind === 'reset' || action.kind === 'deactivate' || action.kind === 'restore') && (
        <AccountConfirm kind={action.kind} account={action.account} onClose={() => setAction(null)} onDone={done} />
      )}
    </>
  )
}

const CONFIRM = {
  reset: {
    title: 'Reset password',
    label: 'Reset password',
    text: (a: Account) => `${a.name} (NIK ${a.nik}) akan keluar dari semua perangkat. Password kembali ke NIK.`,
    request: (a: Account) => api.post(`/accounts/${a.id}/reset`),
    done: 'Password direset.',
  },
  deactivate: {
    title: 'Nonaktifkan akun',
    label: 'Nonaktifkan',
    text: (a: Account) => `${a.name} tidak bisa login dan keluar dari semua perangkat. Riwayat yang dicatat akun ini tetap tersimpan. Akun bisa diaktifkan kembali.`,
    request: (a: Account) => api.delete(`/accounts/${a.id}`),
    done: 'Akun dinonaktifkan.',
  },
  restore: {
    title: 'Aktifkan kembali',
    label: 'Aktifkan',
    text: (a: Account) => `${a.name} bisa login lagi dengan password terakhirnya.`,
    request: (a: Account) => api.post(`/accounts/${a.id}/restore`),
    done: 'Akun diaktifkan kembali.',
  },
}

function AccountConfirm({ kind, account, onClose, onDone }: { kind: keyof typeof CONFIRM; account: Account; onClose: () => void; onDone: () => void }) {
  const toast = useToast()
  const [loading, setLoading] = useState(false)
  const config = CONFIRM[kind]

  const confirm = async () => {
    setLoading(true)
    try {
      await config.request(account)
      toast.success(config.done)
      onDone()
    } catch (error) {
      toast.error(fieldErrors(error).account ?? errorMessage(error))
      setLoading(false)
    }
  }

  return (
    <ConfirmDialog open title={config.title} confirmLabel={config.label} danger={kind === 'deactivate'} loading={loading} onConfirm={confirm} onClose={onClose}>
      {config.text(account)}
    </ConfirmDialog>
  )
}

function PasswordFields({ form, errors, set }: {
  form: { password: string; password_confirmation: string }
  errors: Record<string, string>
  set: (key: 'password' | 'password_confirmation', value: string) => void
}) {
  return (
    <>
      <FormField label="Password" required error={errors.password}>
        <PasswordInput value={form.password} onChange={(e) => set('password', e.target.value)} autoComplete="new-password" error={errors.password} />
        {!errors.password && <span className="block text-xs text-slate-500">Minimal {PASSWORD_MIN} karakter, tidak sama dengan username.</span>}
      </FormField>
      <FormField label="Konfirmasi password" required error={errors.password_confirmation}>
        <PasswordInput
          value={form.password_confirmation}
          onChange={(e) => set('password_confirmation', e.target.value)}
          autoComplete="new-password"
          error={errors.password_confirmation}
        />
      </FormField>
    </>
  )
}

function useFormSubmit(onDone: () => void, success: string) {
  const toast = useToast()
  const [errors, setErrors] = useState<Record<string, string>>({})
  const [message, setMessage] = useState('')
  const [saving, setSaving] = useState(false)

  const run = async (local: Record<string, string>, request: () => Promise<unknown>) => {
    setMessage('')
    setErrors(local)
    if (Object.keys(local).length > 0) return
    setSaving(true)
    try {
      await request()
      toast.success(success)
      onDone()
    } catch (error) {
      const fields = fieldErrors(error)
      setErrors(fields)
      if (Object.keys(fields).length === 0 || fields.account) setMessage(fields.account ?? errorMessage(error))
      setSaving(false)
    }
  }

  return { errors, message, saving, run }
}

function ErrorBox({ message }: { message: string }) {
  if (!message) return null
  return (
    <div role="alert" className="rounded-lg bg-bad-50 px-3 py-2.5 text-sm text-bad-700">
      {message}
    </div>
  )
}

/** Tambah akun bersama: password ditentukan staf pembuat, tidak wajib ganti saat login pertama. */
function SharedAccountModal({ onClose, onDone }: { onClose: () => void; onDone: () => void }) {
  const departments = useDepartments()
  const [form, setForm] = useState({ username: '', name: '', department: '', password: '', password_confirmation: '' })
  const { errors, message, saving, run } = useFormSubmit(onDone, 'Akun bersama ditambahkan.')
  const set = (key: keyof typeof form, value: string) => setForm((f) => ({ ...f, [key]: value }))

  const submit = (e: FormEvent) => {
    e.preventDefault()
    const username = form.username.trim().toLowerCase()
    const local: Record<string, string> = { ...passwordErrors(form.password, form.password_confirmation, username) }
    if (/^\d+$/.test(username)) local.username = 'Username tidak boleh angka saja (format NIK).'
    run(local, () => api.post('/accounts', { ...form, username, name: form.name.trim() }))
  }

  return (
    <Modal
      open
      title="Tambah akun"
      onClose={onClose}
      footer={
        <>
          <Button variant="secondary" onClick={onClose}>Batal</Button>
          <Button type="submit" form="shared-account-form" loading={saving}>Simpan</Button>
        </>
      }
    >
      <form id="shared-account-form" onSubmit={submit} className="space-y-4">
        <p className="text-sm text-slate-500">Untuk akun yang dipakai bersama, mis. satu akun per departemen. Riwayat mencatat nama akun ini, bukan orangnya.</p>
        <ErrorBox message={message} />
        <FormField label="Username" required error={errors.username}>
          <Input value={form.username} onChange={(e) => set('username', e.target.value)} placeholder="mis. bersama.hrm" autoComplete="off" maxLength={50} error={errors.username} />
          {!errors.username && <span className="block text-xs text-slate-500">Huruf kecil, angka, titik, garis bawah, atau strip; minimal satu huruf.</span>}
        </FormField>
        <FormField label="Nama tampilan" required error={errors.name}>
          <Input value={form.name} onChange={(e) => set('name', e.target.value)} placeholder="mis. Akun Bersama HRM" maxLength={100} error={errors.name} />
        </FormField>
        <FormField label="Departemen" required error={errors.department}>
          <DepartmentCombobox
            value={departments.data.find((d) => d.name === form.department)?.id ?? null}
            onChange={(id) => set('department', departments.data.find((d) => d.id === id)?.name ?? '')}
            departments={departments.data}
            error={errors.department}
          />
        </FormField>
        <PasswordFields form={form} errors={errors} set={set} />
      </form>
    </Modal>
  )
}

/** Ubah password akun bersama oleh staf (tanpa wajib ganti). */
function SharedPasswordModal({ account, onClose, onDone }: { account: Account; onClose: () => void; onDone: () => void }) {
  const [form, setForm] = useState({ password: '', password_confirmation: '' })
  const { errors, message, saving, run } = useFormSubmit(onDone, 'Password akun bersama diubah.')
  const set = (key: keyof typeof form, value: string) => setForm((f) => ({ ...f, [key]: value }))

  const submit = (e: FormEvent) => {
    e.preventDefault()
    run(passwordErrors(form.password, form.password_confirmation, account.username ?? ''), () => api.put(`/accounts/${account.id}/password`, form))
  }

  return (
    <Modal
      open
      title={`Ubah password ${account.name}`}
      onClose={onClose}
      footer={
        <>
          <Button variant="secondary" onClick={onClose}>Batal</Button>
          <Button type="submit" form="shared-password-form" loading={saving}>Simpan</Button>
        </>
      }
    >
      <form id="shared-password-form" onSubmit={submit} className="space-y-4">
        <p className="text-sm text-slate-500">
          Username <span className="font-mono">{account.username}</span>. Perangkat lain yang memakai akun ini akan keluar.
        </p>
        <ErrorBox message={message} />
        <PasswordFields form={form} errors={errors} set={set} />
      </form>
    </Modal>
  )
}
