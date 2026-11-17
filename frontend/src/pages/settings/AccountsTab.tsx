import { EllipsisVertical, Plus } from 'lucide-react'
import { useQueryClient } from '@tanstack/react-query'
import { useState, type FormEvent } from 'react'
import { Badge } from '../../components/ui/Badge'
import Button from '../../components/ui/Button'
import ConfirmDialog from '../../components/ui/ConfirmDialog'
import DataTable, { type Column } from '../../components/ui/DataTable'
import DropdownMenu, { type MenuItem } from '../../components/ui/DropdownMenu'
import Combobox from '../../components/ui/Combobox'
import { FormField, Input, Select } from '../../components/ui/FormField'
import Modal from '../../components/ui/Modal'
import PasswordInput from '../../components/ui/PasswordInput'
import { Skeleton } from '../../components/ui/Skeleton'
import { useToast } from '../../components/ui/toast-context'
import { api, errorMessage, fieldErrors } from '../../lib/api'
import { getUser, loginId } from '../../lib/auth'
import { formatDateTime } from '../../lib/format'
import { passwordErrors } from '../../lib/password'
import { useAccountEvents, useAccounts, type Account, type AccountStatus } from '../../lib/queries/accounts'
import { queryKeys } from '../../lib/queries/keys'
import { listConfigs } from '../../lib/queries/list-configs'
import { useDepartments } from '../../lib/queries/master'
import { usePasswordMin } from '../../lib/settings'
import { useErrorToast } from '../../lib/useErrorToast'
import { useListParams } from '../../lib/useListParams'

type ActionKind = 'reset' | 'deactivate' | 'restore' | 'password' | 'email' | 'history'
type Action = { kind: ActionKind; account: Account } | { kind: 'create' }

const STATUS_LABEL: Record<AccountStatus, string> = { aktif: 'Aktif', belum: 'Belum pernah masuk', nonaktif: 'Nonaktif' }
const STATUS_COLOR = { aktif: 'green', belum: 'slate', nonaktif: 'red' } as const

/** Teks bermakna, atau null bila kosong atau hanya "-" (nilai kosong dari API pegawai). */
const meaningful = (value: string | null) => {
  const text = value?.trim()
  return text && text !== '-' ? text : null
}

const EVENT_LABEL: Record<string, string> = {
  created: 'Akun dibuat',
  email_updated: 'Email diubah',
  password_updated: 'Password diubah',
  reset: 'Password direset ke bawaan',
  deactivated: 'Akun dinonaktifkan',
  restored: 'Akun diaktifkan kembali',
}

/**
 * Pengaturan > Akun (T33, T41, SPEC §15): siapa saja yang punya akses ke aplikasi. Akun pegawai SHD terbentuk
 * otomatis dari data pegawai (login pertama dengan NIK); di sini staf menambah akun eksternal, mengubah email dan
 * password akun eksternal, reset password pegawai, menonaktifkan/mengaktifkan kembali, dan melihat riwayat aksi.
 */
export default function AccountsTab() {
  const queryClient = useQueryClient()
  const list = useListParams(listConfigs.accounts)
  const accounts = useAccounts(list.params)
  useErrorToast(accounts.error)
  const me = getUser()
  const [action, setAction] = useState<Action | null>(null)

  const done = () => {
    setAction(null)
    queryClient.invalidateQueries({ queryKey: queryKeys.accounts.all })
  }

  /** Baris "Belum pernah masuk" belum punya akun, jadi tidak ada aksi. Menu bergantung jenis dan status akun. */
  const menuItems = (a: Account): MenuItem[] => {
    if (a.id === null) return []
    const isMe = a.id === me?.id
    const open = (kind: ActionKind) => () => setAction({ kind, account: a })
    const items: MenuItem[] = []
    if (!a.is_deactivated) {
      if (a.source === 'manual') items.push({ label: 'Ubah email', onClick: open('email') }, { label: 'Ubah password', onClick: open('password') })
      else if (!isMe) items.push({ label: 'Reset password', onClick: open('reset') })
      // Pegawai nonaktif di data pegawai tetap tidak bisa masuk walau akunnya aktif: hanya Reset password.
      if (!isMe && !a.employee_inactive) items.push({ label: 'Nonaktifkan', danger: true, onClick: open('deactivate') })
    } else if (!a.employee_inactive) {
      items.push({ label: 'Aktifkan kembali', onClick: open('restore') })
    }
    items.push({ label: 'Lihat riwayat', onClick: open('history') })
    return items
  }

  const columns: Column<Account>[] = [
    {
      header: 'Nama',
      mobile: 'title',
      cell: (a) => (
        <>
          <span className="flex flex-wrap items-center gap-2">
            <span className={`font-semibold ${a.status === 'nonaktif' ? 'text-slate-500' : ''}`}>{a.name}</span>
            {a.id !== null && a.id === me?.id && <Badge color="blue">Anda</Badge>}
          </span>
          <span className="block font-mono text-xs text-slate-500">{loginId(a)}</span>
        </>
      ),
    },
    {
      header: 'Jenis',
      cell: (a) => (a.source === 'api' ? <Badge color="blue">Pegawai SHD</Badge> : <Badge>Akun eksternal</Badge>),
    },
    {
      header: 'Jabatan',
      cell: (a) => {
        // Pegawai SHD: jabatan + departemen dari SIMHC. Akun eksternal: keterangan + unit asal. "-" dan kosong dilewati.
        const main = meaningful(a.source === 'api' ? a.position : a.note)
        const second = meaningful(a.department)
        if (!main && !second) return '-'
        return (
          <>
            {main ?? '-'}
            {second && <span className="block text-xs text-slate-500">{second}</span>}
          </>
        )
      },
    },
    {
      header: 'Status',
      cell: (a) => (
        <>
          <Badge color={STATUS_COLOR[a.status]}>{STATUS_LABEL[a.status]}</Badge>
          {a.employee_inactive && <span className="mt-1 block text-xs text-slate-500">Nonaktif di data pegawai</span>}
        </>
      ),
    },
    {
      header: 'Password',
      cell: (a) => (a.password_default === null ? '-' : a.password_default ? <Badge color="amber">Masih bawaan</Badge> : <span className="text-slate-600">Sudah diganti</span>),
    },
    { header: 'Masuk terakhir', cell: (a) => <span className="whitespace-nowrap">{formatDateTime(a.last_login_at)}</span> },
    {
      header: '',
      className: 'w-10',
      mobile: 'badge',
      cell: (a) => {
        const items = menuItems(a)
        return items.length === 0 ? null : <DropdownMenu label={`Aksi untuk ${a.name}`} trigger={<EllipsisVertical size={16} />} triggerClassName="px-2.5!" items={items} />
      },
    },
  ]

  const result = accounts.data

  return (
    <>
      <section className="space-y-3 rounded-xl border border-slate-200 bg-white p-4 sm:p-5">
        <p className="text-sm text-slate-500">Pegawai SHD otomatis mendapat akses dari data SIMHC yang disinkronkan setiap hari. Akun eksternal dibuat untuk pengguna yang tidak terdaftar di SIMHC.</p>
        <div className="grid grid-cols-2 gap-2 sm:flex sm:items-center">
          <Input
            className="col-span-2 sm:w-60"
            value={list.searchText}
            onChange={(e) => list.setSearchText(e.target.value)}
            placeholder="Cari nama atau NIK"
            aria-label="Cari akun"
          />
          <Select clearable className="min-w-0 sm:w-44" value={list.get('type')} onChange={(e) => list.setFilter('type', e.target.value)} aria-label="Jenis">
            <option value="">Semua jenis</option>
            <option value="pegawai">Pegawai SHD</option>
            <option value="bersama">Akun eksternal</option>
          </Select>
          <Select clearable className="min-w-0 sm:w-52" value={list.get('status')} onChange={(e) => list.setFilter('status', e.target.value)} aria-label="Status">
            <option value="">Semua status</option>
            <option value="aktif">Aktif</option>
            <option value="belum">Belum pernah masuk</option>
            <option value="nonaktif">Nonaktif</option>
          </Select>
          <Button variant="secondary" className="col-span-2 sm:col-span-1 sm:ml-auto" onClick={() => setAction({ kind: 'create' })}>
            <Plus size={16} /> Tambah akun eksternal
          </Button>
        </div>
        <DataTable
          columns={columns}
          rows={result?.data ?? []}
          rowKey={(a) => a.id ?? `nik-${a.nik}`}
          loading={accounts.isPending}
          fetching={accounts.isPlaceholderData}
          emptyText={list.hasFilter ? 'Tidak ada akun yang cocok.' : 'Belum ada akun.'}
          meta={result?.meta}
          unit="akun"
          onPageChange={list.setPage}
        />
      </section>

      {action?.kind === 'create' && <SharedAccountModal onClose={() => setAction(null)} onDone={done} />}
      {action?.kind === 'email' && <EmailModal account={action.account} onClose={() => setAction(null)} onDone={done} />}
      {action?.kind === 'password' && <SharedPasswordModal account={action.account} onClose={() => setAction(null)} onDone={done} />}
      {action?.kind === 'history' && <HistoryModal account={action.account} onClose={() => setAction(null)} />}
      {action && (action.kind === 'reset' || action.kind === 'deactivate' || action.kind === 'restore') && (
        <AccountConfirm kind={action.kind} account={action.account} onClose={() => setAction(null)} onDone={done} />
      )}
    </>
  )
}

/** 10 aktivitas terakhir pada akun: aksi, pelaku, waktu. */
function HistoryModal({ account, onClose }: { account: Account; onClose: () => void }) {
  const events = useAccountEvents(account.id as number)
  useErrorToast(events.error)

  return (
    <Modal open title={`Riwayat ${account.name}`} onClose={onClose} footer={<Button variant="secondary" onClick={onClose}>Tutup</Button>}>
      {events.isPending ? (
        <div className="space-y-3" aria-busy="true">
          {Array.from({ length: 3 }, (_, i) => <Skeleton key={i} className="h-10 w-full" />)}
        </div>
      ) : events.data && events.data.length > 0 ? (
        <ul className="divide-y divide-slate-100 text-sm">
          {events.data.map((e) => (
            <li key={e.id} className="py-2.5">
              <p className="font-medium">{EVENT_LABEL[e.event] ?? e.event}</p>
              <p className="text-xs text-slate-500">oleh {e.actor_name ?? '-'} · {formatDateTime(e.created_at)}</p>
            </li>
          ))}
        </ul>
      ) : (
        <p className="text-sm text-slate-500">Belum ada aktivitas tercatat.</p>
      )}
    </Modal>
  )
}

const CONFIRM = {
  reset: {
    title: 'Reset password',
    label: 'Reset password',
    text: (a: Account) => `${a.name} (NIK ${a.nik}) akan keluar dari semua perangkat. Password kembali ke NIK, dan saat masuk berikutnya ia wajib membuat password baru.`,
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
  const passwordMin = usePasswordMin()

  return (
    <>
      <FormField label="Password" required error={errors.password}>
        <PasswordInput value={form.password} onChange={(e) => set('password', e.target.value)} autoComplete="new-password" error={errors.password} />
        {!errors.password && <span className="block text-xs text-slate-500">Minimal {passwordMin} karakter, tidak sama dengan username.</span>}
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

/** Tambah akun eksternal: email wajib (T40); password ditentukan staf pembuat, tidak wajib ganti saat login pertama. */
function SharedAccountModal({ onClose, onDone }: { onClose: () => void; onDone: () => void }) {
  const departments = useDepartments()
  const passwordMin = usePasswordMin()
  const [form, setForm] = useState({ name: '', department: '', note: '', username: '', email: '', password: '', password_confirmation: '' })
  const { errors, message, saving, run } = useFormSubmit(onDone, 'Akun eksternal ditambahkan.')
  const set = (key: keyof typeof form, value: string) => setForm((f) => ({ ...f, [key]: value }))
  // Saran unit dari master departemen (berasal dari SIMHC); unit lain boleh diketik sendiri.
  const unitOptions = departments.data
    .filter((d) => d.is_active !== false)
    .map((d) => ({ id: d.id, label: d.name, description: d.code && d.code !== d.name ? d.code : undefined }))

  const submit = (e: FormEvent) => {
    e.preventDefault()
    const username = form.username.trim().toLowerCase()
    const local: Record<string, string> = { ...passwordErrors(form.password, form.password_confirmation, username, 'username', passwordMin) }
    if (/^\d+$/.test(username)) local.username = 'Username tidak boleh angka saja (format NIK).'
    if (!form.department.trim()) local.department = 'Unit / departemen asal wajib diisi.'
    run(local, () =>
      api.post('/accounts', {
        ...form,
        username,
        name: form.name.trim(),
        department: form.department.trim(),
        note: form.note.trim() || null,
        email: form.email.trim(),
      }),
    )
  }

  return (
    <Modal
      open
      title="Tambah akun eksternal"
      onClose={onClose}
      footer={
        <>
          <Button variant="secondary" onClick={onClose}>Batal</Button>
          <Button type="submit" form="shared-account-form" loading={saving}>Simpan</Button>
        </>
      }
    >
      <form id="shared-account-form" onSubmit={submit} className="space-y-4">
        <p className="text-sm text-slate-500">Untuk pengguna yang tidak terdaftar di SIMHC, mis. satu akun untuk satu departemen. Riwayat mencatat nama akun ini, bukan orangnya.</p>
        <ErrorBox message={message} />
        <FormField label="Nama" required error={errors.name}>
          <Input value={form.name} onChange={(e) => set('name', e.target.value)} placeholder="mis. Akun SGA" maxLength={100} error={errors.name} />
        </FormField>
        <FormField label="Unit / departemen asal" required error={errors.department}>
          <Combobox
            value={form.department}
            onChange={(value) => set('department', value)}
            options={unitOptions}
            placeholder="Pilih dari saran atau ketik sendiri"
            error={errors.department}
          />
          {!errors.department && <span className="block text-xs text-slate-500">Pilih dari daftar departemen, atau ketik unit lain bila tidak ada.</span>}
        </FormField>
        <FormField label="Keterangan" error={errors.note}>
          <Input value={form.note} onChange={(e) => set('note', e.target.value)} placeholder="mis. Akun untuk Departemen SGA" maxLength={150} error={errors.note} />
        </FormField>
        <FormField label="Username" required error={errors.username}>
          <Input value={form.username} onChange={(e) => set('username', e.target.value)} placeholder="mis. sga" autoComplete="off" maxLength={50} error={errors.username} />
          {!errors.username && <span className="block text-xs text-slate-500">Huruf kecil, angka, titik, garis bawah, atau strip; minimal satu huruf.</span>}
        </FormField>
        <FormField label="Email" required error={errors.email}>
          <Input type="email" inputMode="email" value={form.email} onChange={(e) => set('email', e.target.value)} placeholder="nama@contoh.com" autoComplete="off" maxLength={150} error={errors.email} />
          {!errors.email && <span className="block text-xs text-slate-500">Kode lupa password dikirim ke email ini.</span>}
        </FormField>
        <PasswordFields form={form} errors={errors} set={set} />
      </form>
    </Modal>
  )
}

/** Ubah email akun mana pun: tujuan kode lupa password (T40). */
function EmailModal({ account, onClose, onDone }: { account: Account; onClose: () => void; onDone: () => void }) {
  const [email, setEmail] = useState(account.email ?? '')
  const { errors, message, saving, run } = useFormSubmit(onDone, 'Email akun disimpan.')

  const submit = (e: FormEvent) => {
    e.preventDefault()
    run({}, () => api.put(`/accounts/${account.id}`, { email: email.trim() }))
  }

  return (
    <Modal
      open
      title={`Ubah email ${account.name}`}
      onClose={onClose}
      footer={
        <>
          <Button variant="secondary" onClick={onClose}>Batal</Button>
          <Button type="submit" form="account-email-form" loading={saving}>Simpan</Button>
        </>
      }
    >
      <form id="account-email-form" onSubmit={submit} className="space-y-4">
        <p className="text-sm text-slate-500">Kode lupa password untuk akun ini dikirim ke email berikut. Pastikan email milik pemakai akun.</p>
        <ErrorBox message={message} />
        <FormField label="Email" required error={errors.email}>
          <Input type="email" inputMode="email" value={email} onChange={(e) => setEmail(e.target.value)} placeholder="nama@contoh.com" autoComplete="off" maxLength={150} error={errors.email} autoFocus required />
        </FormField>
      </form>
    </Modal>
  )
}

/** Ubah password akun eksternal oleh staf; pemakai akun wajib membuat password baru saat masuk berikutnya (T40). */
function SharedPasswordModal({ account, onClose, onDone }: { account: Account; onClose: () => void; onDone: () => void }) {
  const passwordMin = usePasswordMin()
  const [form, setForm] = useState({ password: '', password_confirmation: '' })
  const { errors, message, saving, run } = useFormSubmit(onDone, 'Password akun eksternal diubah.')
  const set = (key: keyof typeof form, value: string) => setForm((f) => ({ ...f, [key]: value }))

  const submit = (e: FormEvent) => {
    e.preventDefault()
    run(passwordErrors(form.password, form.password_confirmation, account.username ?? '', 'username', passwordMin), () => api.put(`/accounts/${account.id}/password`, form))
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
          Username <span className="font-mono">{account.username}</span>. Perangkat yang memakai akun ini akan keluar, dan saat masuk berikutnya pemakainya wajib membuat password baru.
        </p>
        <ErrorBox message={message} />
        <PasswordFields form={form} errors={errors} set={set} />
      </form>
    </Modal>
  )
}
