import { useState } from 'react'
import Combobox from './Combobox'

interface Option {
  id: number
  label: string
}

interface Props {
  /** id terpilih; null jika belum memilih. */
  value: number | null
  options: Option[]
  onChange: (id: number | null) => void
  /** Jika ada, mengetik nama baru menampilkan "Tambah …"; harus mengembalikan opsi yang dibuat. */
  onCreate?: (name: string) => Promise<Option>
  /** Untuk kolom opsional: true selama ada teks ketikan yang belum menjadi pilihan. */
  onPendingChange?: (pending: boolean) => void
  placeholder?: string
  error?: string
  disabled?: boolean
}

/** Combobox yang mengikat id (bukan teks): pilih dari saran atau tambah nama baru. */
export default function EntityCombobox({ value, options, onChange, onCreate, onPendingChange, placeholder, error, disabled }: Props) {
  const [typed, setTyped] = useState<string | null>(null)
  const selectedLabel = options.find((o) => o.id === value)?.label
  const text = typed ?? selectedLabel ?? ''

  return (
    <Combobox
      value={text}
      options={options}
      placeholder={placeholder}
      allowCreate={!!onCreate}
      error={error}
      disabled={disabled}
      onChange={(t) => {
        setTyped(t)
        onPendingChange?.(t.trim() !== '')
        if (value !== null) onChange(null)
      }}
      onSelect={async ({ id, label }) => {
        if (id !== null) {
          setTyped(null)
          onPendingChange?.(false)
          onChange(Number(id))
          return
        }
        try {
          const created = await onCreate?.(label)
          if (created) onChange(created.id)
          setTyped(null)
          onPendingChange?.(false)
        } catch {
          // onCreate menampilkan pesan error sendiri
        }
      }}
    />
  )
}
