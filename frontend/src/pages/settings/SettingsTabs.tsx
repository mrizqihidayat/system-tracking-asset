import { TriangleAlert } from 'lucide-react'
import { useRef, useState, type CSSProperties, type FormEvent, type ReactNode } from 'react'
import defaultLogo from '../../assets/logo.png'
import { ErrorBox } from '../../components/AuthShell'
import Button from '../../components/ui/Button'
import { FormField, Input, Textarea } from '../../components/ui/FormField'
import { LoadingLabel, Skeleton } from '../../components/ui/Skeleton'
import { useToast } from '../../components/ui/toast-context'
import { api, errorMessage, fieldErrors } from '../../lib/api'
import { formatDateTime } from '../../lib/format'
import { useSettings, useSettingsSaved, type Settings, type SettingsResponse } from '../../lib/settings'
import { contrastWarnings, isHexColor, THEME_PRESETS, themeVars, type Theme } from '../../lib/theme'
import { useErrorToast } from '../../lib/useErrorToast'

/** Memuat pengaturan lalu merender form; form dibuat ulang setiap data server berubah. */
function Loaded({ children }: { children: (response: SettingsResponse) => ReactNode }) {
  const settings = useSettings()
  useErrorToast(settings.error)

  if (!settings.data) {
    return (
      <section className="space-y-4 rounded-xl border border-slate-200 bg-white p-5" aria-busy="true">
        <LoadingLabel />
        {Array.from({ length: 4 }, (_, i) => <Skeleton key={i} className="h-10 w-full max-w-md" />)}
      </section>
    )
  }
  return <>{children(settings.data)}</>
}

function Card({ title, help, children }: { title: string; help?: string; children: ReactNode }) {
  return (
    <section className="rounded-xl border border-slate-200 bg-white p-4 sm:p-5">
      <h2 className="font-semibold">{title}</h2>
      {help && <p className="mt-1 text-sm text-slate-500">{help}</p>}
      <div className="mt-4 space-y-4">{children}</div>
    </section>
  )
}

const Help = ({ children }: { children: ReactNode }) => <span className="block text-xs text-slate-500">{children}</span>

function LastChange({ change }: { change: SettingsResponse['last_change'] }) {
  if (!change) return null
  return <p className="text-xs text-slate-500">Terakhir diubah {change.by ? `oleh ${change.by}, ` : ''}{formatDateTime(change.at)}.</p>
}

/** Simpan sebagian pengaturan lewat `PUT /settings`. */
function useSave(success: string) {
  const toast = useToast()
  const saved = useSettingsSaved()
  const [errors, setErrors] = useState<Record<string, string>>({})
  const [message, setMessage] = useState('')
  const [saving, setSaving] = useState(false)

  const save = async (values: Partial<Settings>) => {
    setErrors({})
    setMessage('')
    setSaving(true)
    try {
      saved((await api.put<SettingsResponse>('/settings', values)).data)
      toast.success(success)
    } catch (error) {
      const fields = fieldErrors(error)
      setErrors(fields)
      if (Object.keys(fields).length === 0) setMessage(errorMessage(error))
    } finally {
      setSaving(false)
    }
  }

  return { errors, message, saving, save }
}

function SaveBar({ saving, dirty, change, children }: { saving: boolean; dirty: boolean; change: SettingsResponse['last_change']; children?: ReactNode }) {
  return (
    <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <LastChange change={change} />
      <div className="flex flex-col gap-2 sm:ml-auto sm:flex-row">
        {children}
        <Button type="submit" loading={saving} disabled={!dirty} className="w-full sm:w-auto">Simpan</Button>
      </div>
    </div>
  )
}

/* ---------- Tampilan ---------- */

function ColorField({ label, help, value, error, onChange }: { label: string; help: string; value: string; error?: string; onChange: (value: string) => void }) {
  return (
    <FormField label={label} error={error ?? (isHexColor(value) ? undefined : 'Tulis kode warna seperti #017cc2.')}>
      <span className="flex items-center gap-2">
        <input
          type="color"
          value={isHexColor(value) ? value : '#000000'}
          onChange={(e) => onChange(e.target.value)}
          aria-label={`Pilih ${label.toLowerCase()}`}
          className="h-10 w-12 shrink-0 cursor-pointer rounded-lg border border-slate-300 bg-white p-1"
        />
        <Input value={value} onChange={(e) => onChange(e.target.value.trim().toLowerCase())} maxLength={7} className="font-mono" spellCheck={false} />
      </span>
      <Help>{help}</Help>
    </FormField>
  )
}

/** Contoh tampilan dengan warna yang sedang dipilih; belum mengubah aplikasi sampai disimpan. */
function ThemePreview({ theme }: { theme: Theme }) {
  return (
    <div style={themeVars(theme) as CSSProperties} className="overflow-hidden rounded-xl border border-slate-200" aria-label="Contoh tampilan">
      <div className="flex min-h-44">
        <div className="flex w-28 shrink-0 flex-col gap-1.5 bg-rail p-3 text-white sm:w-36">
          <span className="mb-1 text-xs font-bold">Aset SHD</span>
          <span className="rounded-md bg-white px-2 py-1.5 text-xs font-semibold text-brand-800">Dashboard</span>
          <span className="px-2 py-1.5 text-xs text-white/80">Daftar Aset</span>
          <span className="px-2 py-1.5 text-xs text-white/80">Peminjaman</span>
        </div>
        <div className="min-w-0 flex-1 space-y-3 bg-canvas p-3 sm:p-4">
          <p className="text-sm font-bold">Contoh halaman</p>
          <div className="flex flex-wrap items-center gap-2">
            <span className="inline-flex min-h-9 items-center rounded-lg bg-brand-600 px-3 text-xs font-semibold text-white">Tombol utama</span>
            <span className="text-xs font-semibold text-brand-700 underline">Tautan</span>
          </div>
          <span className="inline-block rounded-full bg-brand-50 px-2.5 py-1 text-xs font-medium text-brand-800">Label info</span>
          <div className="h-9 rounded-lg border border-brand-600 bg-white px-3 py-2 text-xs text-slate-500 ring-2 ring-brand-100">Kolom isian yang sedang dipilih</div>
        </div>
      </div>
    </div>
  )
}

function AppearanceForm({ response }: { response: SettingsResponse }) {
  const initial: Theme = { primary: response.data.theme_primary, sidebar: response.data.theme_sidebar }
  const defaults: Theme = { primary: response.defaults.theme_primary, sidebar: response.defaults.theme_sidebar }
  const [theme, setTheme] = useState(initial)
  const { errors, message, saving, save } = useSave('Tampilan disimpan.')

  const same = (a: Theme, b: Theme) => a.primary === b.primary && a.sidebar === b.sidebar
  const valid = isHexColor(theme.primary) && isHexColor(theme.sidebar)
  const warnings = contrastWarnings(theme)

  const submit = (e: FormEvent) => {
    e.preventDefault()
    if (valid) save({ theme_primary: theme.primary, theme_sidebar: theme.sidebar })
  }

  return (
    <form noValidate onSubmit={submit} className="space-y-5">
      <Card title="Warna web" help="Pilih salah satu paket warna, atau tentukan sendiri. Lihat contohnya dulu; aplikasi baru berubah setelah Anda menekan Simpan.">
        <ErrorBox message={message} />
        <div className="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-5" role="group" aria-label="Paket warna">
          {THEME_PRESETS.map((preset) => (
            <button
              key={preset.name}
              type="button"
              aria-pressed={same(theme, preset.theme)}
              onClick={() => setTheme(preset.theme)}
              className={`flex min-h-10 items-center gap-2 rounded-lg border px-3 py-2 text-left text-sm font-medium transition-colors ${
                same(theme, preset.theme) ? 'border-brand-600 bg-brand-50 text-brand-800' : 'border-slate-200 bg-white hover:border-slate-300'
              }`}
            >
              <span className="flex shrink-0 overflow-hidden rounded-full border border-white shadow-sm">
                <span className="h-5 w-2.5" style={{ background: preset.theme.sidebar }} />
                <span className="h-5 w-2.5" style={{ background: preset.theme.primary }} />
              </span>
              <span className="min-w-0 truncate">{preset.name}</span>
            </button>
          ))}
        </div>

        <div className="grid gap-4 sm:grid-cols-2">
          <ColorField
            label="Warna utama"
            help="Dipakai untuk tombol, tautan, dan tanda pilihan."
            value={theme.primary}
            error={errors.theme_primary}
            onChange={(primary) => setTheme((t) => ({ ...t, primary }))}
          />
          <ColorField
            label="Warna sidebar"
            help="Latar menu di sisi kiri dan kepala halaman di HP."
            value={theme.sidebar}
            error={errors.theme_sidebar}
            onChange={(sidebar) => setTheme((t) => ({ ...t, sidebar }))}
          />
        </div>

        {warnings.length > 0 && (
          <div role="alert" className="flex gap-2 rounded-lg bg-warn-50 px-3 py-2.5 text-sm text-warn-700">
            <TriangleAlert size={18} className="mt-0.5 shrink-0" />
            <ul className="space-y-1">
              {warnings.map((warning) => <li key={warning}>{warning}</li>)}
            </ul>
          </div>
        )}

        <div>
          <p className="mb-1.5 text-sm font-medium text-slate-700">Contoh tampilan</p>
          {valid ? <ThemePreview theme={theme} /> : <p className="text-sm text-slate-500">Perbaiki kode warna untuk melihat contoh.</p>}
        </div>
      </Card>

      <SaveBar saving={saving} dirty={valid && !same(theme, initial)} change={response.last_change}>
        <Button variant="secondary" className="w-full sm:w-auto" disabled={same(theme, defaults)} onClick={() => setTheme(defaults)}>
          Kembalikan ke default
        </Button>
      </SaveBar>
    </form>
  )
}

export const AppearanceTab = () => (
  <Loaded>{(response) => <AppearanceForm key={`${response.data.theme_primary}${response.data.theme_sidebar}`} response={response} />}</Loaded>
)

/* ---------- Konten ---------- */

const IMAGE_MAX_BYTES = 1024 * 1024

function ImageField({ type, label, help, url, fallback }: { type: 'logo' | 'favicon'; label: string; help: string; url: string | null; fallback: string }) {
  const toast = useToast()
  const saved = useSettingsSaved()
  const input = useRef<HTMLInputElement>(null)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState('')

  const request = async (send: () => Promise<{ data: SettingsResponse }>, success: string) => {
    setError('')
    setBusy(true)
    try {
      saved((await send()).data)
      toast.success(success)
    } catch (err) {
      setError(fieldErrors(err).file ?? errorMessage(err))
    } finally {
      setBusy(false)
    }
  }

  const upload = (file: File | undefined) => {
    if (input.current) input.current.value = ''
    if (!file) return
    if (file.size > IMAGE_MAX_BYTES) {
      setError('Ukuran gambar maksimal 1 MB.')
      return
    }
    const body = new FormData()
    body.append('file', file)
    request(() => api.post<SettingsResponse>(`/settings/files/${type}`, body), `${label} diganti.`)
  }

  return (
    <div className="space-y-1.5">
      <p className="text-sm font-medium text-slate-700">{label}</p>
      <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
        <span className="flex h-16 w-full shrink-0 items-center justify-center rounded-lg border border-slate-200 bg-slate-50 p-2 sm:w-40">
          <img src={url ?? fallback} alt={`${label} saat ini`} className="max-h-full max-w-full object-contain" />
        </span>
        <div className="flex flex-col gap-2 sm:flex-row">
          <input ref={input} type="file" accept="image/png,image/jpeg,image/webp" className="sr-only" tabIndex={-1} aria-hidden="true" onChange={(e) => upload(e.target.files?.[0])} />
          <Button variant="secondary" loading={busy} className="w-full sm:w-auto" onClick={() => input.current?.click()}>Ganti gambar</Button>
          {url && (
            <Button variant="ghost" disabled={busy} className="w-full sm:w-auto" onClick={() => request(() => api.delete<SettingsResponse>(`/settings/files/${type}`), `${label} kembali ke bawaan.`)}>
              Pakai bawaan
            </Button>
          )}
        </div>
      </div>
      {error ? <span role="alert" className="block text-xs font-medium text-bad-700">{error}</span> : <Help>{help}</Help>}
    </div>
  )
}

type ContentValues = Pick<Settings, 'site_title' | 'site_description' | 'footer_text' | 'report_header' | 'label_text'>

const CONTENT_KEYS: (keyof ContentValues)[] = ['site_title', 'site_description', 'footer_text', 'report_header', 'label_text']

const pickContent = (data: Settings): ContentValues => Object.fromEntries(CONTENT_KEYS.map((key) => [key, data[key]])) as ContentValues

function ContentForm({ response }: { response: SettingsResponse }) {
  const initial = pickContent(response.data)
  const [form, setForm] = useState(initial)
  const { errors, message, saving, save } = useSave('Konten disimpan.')
  const set = (key: keyof ContentValues, value: string) => setForm((f) => ({ ...f, [key]: value }))
  const dirty = CONTENT_KEYS.some((key) => form[key].trim() !== initial[key])

  const submit = (e: FormEvent) => {
    e.preventDefault()
    save(form)
  }

  return (
    <form noValidate onSubmit={submit} className="space-y-5">
      <Card title="Logo dan ikon" help="Gambar langsung tersimpan setelah dipilih. Jenis file: PNG, JPG, atau WebP, paling besar 1 MB.">
        <ImageField type="logo" label="Logo" help="Tampil di halaman masuk dan di email kode lupa password." url={response.data.logo_url} fallback={defaultLogo} />
        <ImageField type="favicon" label="Ikon tab browser" help="Gambar kecil di tab browser. Sebaiknya persegi, mis. 64 × 64." url={response.data.favicon_url} fallback="/icon.png" />
        <Help>Ikon dan nama aplikasi yang sudah dipasang di HP (layar utama) tidak ikut berubah dari sini.</Help>
      </Card>

      <Card title="Tulisan di aplikasi">
        <ErrorBox message={message} />
        <FormField label="Judul web" required error={errors.site_title}>
          <Input value={form.site_title} onChange={(e) => set('site_title', e.target.value)} maxLength={60} error={errors.site_title} required />
          {!errors.site_title && <Help>Nama aplikasi di tab browser, menu, dan email.</Help>}
        </FormField>
        <FormField label="Deskripsi di halaman masuk" error={errors.site_description}>
          <Textarea value={form.site_description} onChange={(e) => set('site_description', e.target.value)} maxLength={200} rows={2} error={errors.site_description} />
          {!errors.site_description && <Help>Kalimat pendek di bawah ucapan selamat datang.</Help>}
        </FormField>
        <FormField label="Teks footer" error={errors.footer_text}>
          <Input value={form.footer_text} onChange={(e) => set('footer_text', e.target.value)} maxLength={150} error={errors.footer_text} />
          {!errors.footer_text && <Help>Tulisan kecil di bagian bawah halaman masuk dan halaman QR. Boleh dikosongkan.</Help>}
        </FormField>
      </Card>

      <Card title="Laporan dan label">
        <FormField label="Kop laporan Excel" error={errors.report_header}>
          <Input value={form.report_header} onChange={(e) => set('report_header', e.target.value)} maxLength={120} placeholder="mis. PT INALUM - Departemen SHD" error={errors.report_header} />
          {!errors.report_header && <Help>Baris paling atas di setiap laporan Excel. Kosong = tanpa kop.</Help>}
        </FormField>
        <FormField label="Teks label QR" error={errors.label_text}>
          <Input value={form.label_text} onChange={(e) => set('label_text', e.target.value)} maxLength={40} placeholder="mis. Milik Departemen SHD" error={errors.label_text} />
          {!errors.label_text && <Help>Dicetak kecil di bawah kode aset pada setiap label. Paling banyak 40 huruf ({form.label_text.length}/40). Kosong = tidak dicetak.</Help>}
        </FormField>
      </Card>

      <SaveBar saving={saving} dirty={dirty} change={response.last_change} />
    </form>
  )
}

export const ContentTab = () => (
  <Loaded>{(response) => <ContentForm key={CONTENT_KEYS.map((key) => response.data[key]).join('|')} response={response} />}</Loaded>
)

/* ---------- Keamanan ---------- */

type SecurityKey = 'max_login_attempts' | 'lockout_minutes' | 'password_min_length' | 'otp_ttl_minutes'

const SECURITY_FIELDS: { key: SecurityKey; label: string; unit: string; min: number; max: number; help: string }[] = [
  { key: 'max_login_attempts', label: 'Berapa kali boleh salah password', unit: 'kali', min: 3, max: 10, help: 'Setelah salah sebanyak ini, akun dikunci sementara. Isi 3 sampai 10.' },
  { key: 'lockout_minutes', label: 'Lama akun dikunci', unit: 'menit', min: 1, max: 1440, help: 'Akun terbuka sendiri setelah waktu ini. Staf SHD bisa membukanya lebih cepat lewat Reset password. Isi 1 sampai 1440 (24 jam).' },
  { key: 'password_min_length', label: 'Panjang password paling pendek', unit: 'huruf', min: 8, max: 32, help: 'Berlaku untuk password baru; password lama tidak berubah. Isi 8 sampai 32.' },
  { key: 'otp_ttl_minutes', label: 'Masa berlaku kode lupa password', unit: 'menit', min: 5, max: 60, help: 'Kode 6 angka yang dikirim ke email tidak bisa dipakai setelah waktu ini. Isi 5 sampai 60.' },
]

function SecurityForm({ response }: { response: SettingsResponse }) {
  const initial = Object.fromEntries(SECURITY_FIELDS.map((f) => [f.key, String(response.data[f.key])])) as Record<SecurityKey, string>
  const [form, setForm] = useState(initial)
  const [local, setLocal] = useState<Record<string, string>>({})
  const { errors, message, saving, save } = useSave('Keamanan disimpan.')
  const dirty = SECURITY_FIELDS.some((f) => form[f.key] !== initial[f.key])

  const submit = (e: FormEvent) => {
    e.preventDefault()
    const invalid: Record<string, string> = {}
    for (const field of SECURITY_FIELDS) {
      const value = Number(form[field.key])
      if (!/^\d+$/.test(form[field.key]) || value < field.min || value > field.max) invalid[field.key] = `Isi angka ${field.min} sampai ${field.max}.`
    }
    setLocal(invalid)
    if (Object.keys(invalid).length > 0) return
    save(Object.fromEntries(SECURITY_FIELDS.map((f) => [f.key, Number(form[f.key])])))
  }

  return (
    <form onSubmit={submit} className="space-y-5" noValidate>
      <Card title="Keamanan akun" help="Aturan ini berlaku untuk semua akun.">
        <ErrorBox message={message} />
        <div className="grid gap-4 sm:grid-cols-2">
          {SECURITY_FIELDS.map((field) => {
            const error = local[field.key] ?? errors[field.key]
            return (
              <FormField key={field.key} label={field.label} required error={error}>
                <span className="flex items-center gap-2">
                  <Input
                    type="number"
                    inputMode="numeric"
                    min={field.min}
                    max={field.max}
                    value={form[field.key]}
                    onChange={(e) => setForm((f) => ({ ...f, [field.key]: e.target.value }))}
                    error={error}
                    className="max-w-28"
                  />
                  <span className="text-sm text-slate-500">{field.unit}</span>
                </span>
                {!error && <Help>{field.help}</Help>}
              </FormField>
            )
          })}
        </div>
      </Card>

      <SaveBar saving={saving} dirty={dirty} change={response.last_change} />
    </form>
  )
}

export const SecurityTab = () => (
  <Loaded>{(response) => <SecurityForm key={SECURITY_FIELDS.map((f) => response.data[f.key]).join('|')} response={response} />}</Loaded>
)
