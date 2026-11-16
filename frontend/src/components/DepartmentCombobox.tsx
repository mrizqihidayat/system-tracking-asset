import EntityCombobox from './ui/EntityCombobox'

interface Props {
  value: number | null
  onChange: (id: number | null) => void
  departments: { id: number; name: string }[]
  error?: string
}

/**
 * Pilih departemen dengan mengetik (daftar bisa puluhan): saran menyaring sambil mengetik,
 * dan hanya departemen yang ada yang bisa dipilih (tanpa tambah baru).
 */
export default function DepartmentCombobox({ value, onChange, departments, error }: Props) {
  return (
    <EntityCombobox
      value={value}
      options={departments.map((d) => ({ id: d.id, label: d.name }))}
      onChange={onChange}
      placeholder="Ketik nama departemen…"
      error={error}
    />
  )
}
