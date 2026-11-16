import EntityCombobox from './ui/EntityCombobox'

interface Props {
  value: number | null
  onChange: (id: number | null) => void
  departments: { id: number; code?: string | null; name: string; is_active?: boolean }[]
  error?: string
  placeholder?: string
}

/** Nama lengkap; kode ikut tampil bila berbeda, mis. "Sarana Umum (SGA)". */
const departmentLabel = (d: { code?: string | null; name: string }) => (d.code && d.code !== d.name ? `${d.name} (${d.code})` : d.name)

/**
 * Pilih departemen dengan mengetik (daftar bisa puluhan): saran menyaring sambil mengetik, dan hanya departemen
 * yang ada yang bisa dipilih (tanpa tambah baru; daftar berasal dari API pegawai). Departemen yang tidak ada lagi
 * di API tidak muncul sebagai pilihan baru, kecuali yang sedang terpilih di data lama.
 */
export default function DepartmentCombobox({ value, onChange, departments, error, placeholder = 'Ketik nama departemen…' }: Props) {
  return (
    <EntityCombobox
      value={value}
      options={departments.filter((d) => d.is_active !== false || d.id === value).map((d) => ({ id: d.id, label: departmentLabel(d) }))}
      onChange={onChange}
      placeholder={placeholder}
      error={error}
    />
  )
}
