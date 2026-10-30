import { useEffect, useRef, useState } from 'react'
import { controlClass } from './control-class'

interface Props {
  value: string
  onChange: (value: string) => void
  /** Dipanggil saat saran dipilih; `id` kosong untuk entri baru yang diketik. */
  onSelect?: (option: { id: string | number | null; label: string }) => void
  options: { id: string | number; label: string }[]
  placeholder?: string
  /** Izinkan menambah nama baru yang tidak ada di saran (tampil "Tambah '…'"). */
  allowCreate?: boolean
  error?: string
  disabled?: boolean
}

export default function Combobox({
  value, onChange, onSelect, options, placeholder, allowCreate, error, disabled,
}: Props) {
  const [open, setOpen] = useState(false)
  const box = useRef<HTMLDivElement>(null)

  useEffect(() => {
    const close = (e: MouseEvent) => {
      if (!box.current?.contains(e.target as Node)) setOpen(false)
    }
    document.addEventListener('mousedown', close)
    return () => document.removeEventListener('mousedown', close)
  }, [])

  const query = value.trim().toLowerCase()
  const filtered = options.filter((o) => o.label.toLowerCase().includes(query))
  const exact = options.some((o) => o.label.toLowerCase() === query)
  const canCreate = allowCreate && query !== '' && !exact

  const pick = (id: string | number | null, label: string) => {
    onChange(label)
    onSelect?.({ id, label })
    setOpen(false)
  }

  return (
    <div ref={box} className="relative">
      <input
        className={controlClass(error)}
        value={value}
        placeholder={placeholder}
        disabled={disabled}
        autoComplete="off"
        onFocus={() => setOpen(true)}
        onChange={(e) => {
          onChange(e.target.value)
          setOpen(true)
        }}
        onKeyDown={(e) => e.key === 'Escape' && setOpen(false)}
      />
      {open && (filtered.length > 0 || canCreate) && (
        <ul className="absolute z-10 mt-1 max-h-60 w-full overflow-auto rounded-lg border border-slate-200 bg-white p-1 text-sm shadow-[0_8px_24px_rgb(20_33_46/0.12)]">
          {filtered.map((o) => (
            <li key={o.id}>
              <button type="button" className="w-full rounded-md px-3 py-2 text-left hover:bg-brand-50" onClick={() => pick(o.id, o.label)}>
                {o.label}
              </button>
            </li>
          ))}
          {canCreate && (
            <li>
              <button type="button" className="w-full rounded-md px-3 py-2 text-left font-medium text-brand-700 hover:bg-brand-50" onClick={() => pick(null, value.trim())}>
                Tambah “{value.trim()}”
              </button>
            </li>
          )}
        </ul>
      )}
    </div>
  )
}
