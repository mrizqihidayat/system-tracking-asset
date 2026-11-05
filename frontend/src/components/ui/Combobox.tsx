import { Check, ChevronDown } from 'lucide-react'
import { useCallback, useEffect, useId, useRef, useState, type KeyboardEvent } from 'react'
import { createPortal } from 'react-dom'
import { controlClass } from './control-class'
import { dropdownOpenClass, dropdownOptionClass, dropdownPanelClass } from './dropdown-class'
import { useAnchoredPanel } from './useAnchoredPanel'

interface Props {
  value: string
  onChange: (value: string) => void
  /** Dipanggil saat saran dipilih; `id` kosong untuk entri baru yang diketik. */
  onSelect?: (option: { id: string | number | null; label: string }) => void
  options: { id: string | number; label: string }[]
  placeholder?: string
  /** Izinkan menambah nama baru yang tidak ada di saran (tampil "Tambah '…'"). */
  allowCreate?: boolean
  /** Teks opsi tambah; bawaan: Tambah “…”. */
  createLabel?: (text: string) => string
  /** Untuk label pilihan yang memuat info lain: true = nama ketikan sudah ada, opsi tambah disembunyikan. */
  isExisting?: (text: string) => boolean
  error?: string
  disabled?: boolean
}

/** Kolom ketik-untuk-mencari; daftar pilihannya sama dengan Select. */
export default function Combobox({
  value, onChange, onSelect, options, placeholder, allowCreate, createLabel, isExisting, error, disabled,
}: Props) {
  const [open, setOpen] = useState(false)
  const [active, setActive] = useState(-1)
  const input = useRef<HTMLInputElement>(null)
  const list = useRef<HTMLUListElement>(null)
  const listId = useId()

  const query = value.trim().toLowerCase()
  const exact = options.some((o) => o.label.toLowerCase() === query)
  // Kolom berisi pilihan yang sudah tepat: tampilkan semua pilihan, bukan hanya yang cocok.
  const filtered = exact ? options : options.filter((o) => o.label.toLowerCase().includes(query))
  const canCreate = !!allowCreate && query !== '' && !exact && !isExisting?.(value.trim())
  const count = filtered.length + (canCreate ? 1 : 0)

  const close = useCallback(() => setOpen(false), [])
  const { style, place } = useAnchoredPanel(input, list, open, count, close)

  const show = () => {
    place()
    setOpen(true)
  }

  const pick = (id: string | number | null, label: string) => {
    onChange(label)
    onSelect?.({ id, label })
    setOpen(false)
    setActive(-1)
  }

  useEffect(() => {
    if (open && active >= 0) list.current?.children[active]?.scrollIntoView({ block: 'nearest' })
  }, [open, active])

  const onKeyDown = (e: KeyboardEvent) => {
    if (e.key === 'Escape') {
      if (open) {
        // Hanya menutup daftar, bukan popup di belakangnya.
        e.stopPropagation()
        setOpen(false)
      }
    } else if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
      e.preventDefault()
      if (!open) show()
      else if (count) setActive((i) => (i < 0 ? 0 : (i + (e.key === 'ArrowDown' ? 1 : -1) + count) % count))
    } else if (e.key === 'Enter' && open && active >= 0) {
      e.preventDefault()
      const option = filtered[active]
      if (option) pick(option.id, option.label)
      else pick(null, value.trim())
    } else if (e.key === 'Tab') {
      setOpen(false)
    }
  }

  return (
    <div className="relative">
      <input
        ref={input}
        role="combobox"
        aria-expanded={open && count > 0}
        aria-controls={open && count > 0 ? listId : undefined}
        aria-autocomplete="list"
        aria-activedescendant={open && active >= 0 ? `${listId}-${active}` : undefined}
        className={`${controlClass(error)} pr-9 ${open && count > 0 ? dropdownOpenClass : ''}`}
        value={value}
        placeholder={placeholder}
        disabled={disabled}
        autoComplete="off"
        onFocus={show}
        onClick={show}
        onChange={(e) => {
          onChange(e.target.value)
          setActive(-1)
          show()
        }}
        onKeyDown={onKeyDown}
      />
      <ChevronDown
        size={16}
        className={`pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-slate-500 transition-transform ${open && count > 0 ? 'rotate-180' : ''}`}
        aria-hidden
      />
      {open && count > 0 && createPortal(
        <ul
          ref={list}
          id={listId}
          role="listbox"
          style={style}
          // Fokus tetap di kolom agar bisa terus mengetik.
          onMouseDown={(e) => e.preventDefault()}
          onMouseLeave={() => setActive(-1)}
          className={`fixed z-50 ${dropdownPanelClass}`}
        >
          {filtered.map((o, i) => {
            const selected = o.label.toLowerCase() === query
            return (
              <li
                key={o.id}
                id={`${listId}-${i}`}
                role="option"
                aria-selected={selected}
                onMouseEnter={() => setActive(i)}
                onClick={() => pick(o.id, o.label)}
                className={`flex items-center justify-between gap-2 ${dropdownOptionClass(i === active, selected)}`}
              >
                <span className="min-w-0">{o.label}</span>
                {selected && <Check size={16} className="shrink-0" aria-hidden />}
              </li>
            )
          })}
          {canCreate && (
            <li
              id={`${listId}-${filtered.length}`}
              role="option"
              aria-selected={false}
              onMouseEnter={() => setActive(filtered.length)}
              onClick={() => pick(null, value.trim())}
              className={`flex items-center font-medium text-brand-700 ${dropdownOptionClass(active === filtered.length)}`}
            >
              {createLabel ? createLabel(value.trim()) : `Tambah “${value.trim()}”`}
            </li>
          )}
        </ul>,
        document.body,
      )}
    </div>
  )
}
