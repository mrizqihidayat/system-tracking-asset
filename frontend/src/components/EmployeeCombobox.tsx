import { useEmployeeSearch, type Employee } from '../lib/queries/accounts'
import Combobox from './ui/Combobox'

interface Props {
  value: string
  onChange: (name: string) => void
  /** Saran pegawai dipilih (nama sudah diisi lewat onChange). */
  onPick?: (employee: Employee) => void
  error?: string
  placeholder?: string
}

/**
 * Kolom nama dengan saran pegawai dari direktori pegawai (semua departemen, T33).
 * Ketik manual tetap boleh untuk orang yang tidak ada di direktori.
 */
export default function EmployeeCombobox({ value, onChange, onPick, error, placeholder = 'Ketik nama atau NIK' }: Props) {
  const employees = useEmployeeSearch(value)

  return (
    <Combobox
      value={value}
      onChange={onChange}
      filter={false}
      options={employees.map((e) => ({
        id: e.nik,
        label: e.name,
        description: [e.department, e.position].filter((part) => part && part !== '-').join(' · '),
      }))}
      onSelect={({ id }) => {
        const employee = employees.find((e) => e.nik === id)
        if (employee) onPick?.(employee)
      }}
      placeholder={placeholder}
      error={error}
    />
  )
}
