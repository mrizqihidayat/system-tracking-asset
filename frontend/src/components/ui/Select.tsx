import { Check, ChevronDown, X } from 'lucide-react'
import {
  Children, Fragment, isValidElement, useCallback, useEffect, useId, useMemo, useRef, useState,
  type ChangeEvent, type KeyboardEvent, type ReactElement, type ReactNode,
} from 'react'
import { createPortal } from 'react-dom'
import { controlClass } from './control-class'
import { dropdownOpenClass, dropdownOptionClass, dropdownPanelClass } from './dropdown-class'
import { useAnchoredPanel } from './useAnchoredPanel'

interface Props {
  value?: string | number
  onChange?: (e: ChangeEvent<HTMLSelectElement>) => void
  /** Berisi `<option>`; `<option value="">` menjadi placeholder di kolom, bukan pilihan di daftar. */
  children?: ReactNode
  className?: string
  disabled?: boolean
  error?: string
  /** Kolom opsional: tampilkan tombol × untuk kembali ke placeholder. */
  clearable?: boolean
  'aria-label'?: string
}

interface Option {
  value: string
  label: ReactNode
  text: string
}

const TYPEAHEAD_RESET_MS = 500

const textOf = (node: ReactNode): string =>
  Children.toArray(node)
    .map((child) => (typeof child === 'string' || typeof child === 'number' ? String(child) : ''))
    .join('')

function readOptions(children: ReactNode): Option[] {
  return Children.toArray(children).flatMap((child): Option[] => {
    if (!isValidElement(child)) return []
    const element = child as ReactElement<{ value?: string | number; children?: ReactNode }>
    if (element.type === Fragment) return readOptions(element.props.children)
    const text = textOf(element.props.children)
    return [{ value: String(element.props.value ?? text), label: element.props.children, text }]
  })
}

/** Dropdown dengan daftar buatan sendiri; dipakai seperti `<select>` (isi `<option>`, `onChange` baca `e.target.value`). */
export default function Select({ value, onChange, children, className = '', disabled, error, clearable, 'aria-label': ariaLabel }: Props) {
  const all = useMemo(() => readOptions(children), [children])
  const options = useMemo(() => all.filter((o) => o.value !== ''), [all])
  const placeholder = all.find((o) => o.value === '')
  const current = String(value ?? '')
  const selectedIndex = options.findIndex((o) => o.value === current)
  const selected = options[selectedIndex]
  const shown = selected ?? placeholder ?? options[0]
  const canClear = !!clearable && selected !== undefined && !disabled

  const [open, setOpen] = useState(false)
  // -1 = belum ada yang disorot (dibuka dengan mouse): hanya pilihan aktif yang berwarna.
  const [active, setActive] = useState(-1)
  const button = useRef<HTMLButtonElement>(null)
  const list = useRef<HTMLUListElement>(null)
  const typed = useRef({ text: '', timer: undefined as ReturnType<typeof setTimeout> | undefined })
  const listId = useId()

  const close = useCallback(() => setOpen(false), [])
  const { style, place } = useAnchoredPanel(button, list, open, options.length, close)

  const openList = (index = -1) => {
    place()
    setActive(index)
    setOpen(true)
  }

  const emit = (next: string) => {
    if (next !== current) onChange?.({ target: { value: next }, currentTarget: { value: next } } as ChangeEvent<HTMLSelectElement>)
  }

  const pick = (option: Option | undefined) => {
    setOpen(false)
    if (option) emit(option.value)
  }

  const clear = () => {
    setOpen(false)
    emit('')
    button.current?.focus()
  }

  useEffect(() => {
    if (open) list.current?.children[active >= 0 ? active : selectedIndex]?.scrollIntoView({ block: 'nearest' })
  }, [open, active, selectedIndex])

  const typeAhead = (key: string) => {
    clearTimeout(typed.current.timer)
    typed.current.text += key.toLowerCase()
    typed.current.timer = setTimeout(() => (typed.current.text = ''), TYPEAHEAD_RESET_MS)
    const found = options.findIndex((o) => o.text.toLowerCase().startsWith(typed.current.text))
    if (found < 0) return
    if (open) setActive(found)
    else openList(found)
  }

  const onKeyDown = (e: KeyboardEvent) => {
    if (e.key === 'Tab') {
      setOpen(false)
    } else if (e.key === 'Escape') {
      if (open) {
        // Hanya menutup daftar, bukan popup di belakangnya.
        e.stopPropagation()
        setOpen(false)
      }
    } else if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
      e.preventDefault()
      const start = Math.max(selectedIndex, 0)
      if (!open) openList(start)
      else if (options.length) setActive((i) => (i < 0 ? start : (i + (e.key === 'ArrowDown' ? 1 : -1) + options.length) % options.length))
    } else if (e.key === 'Enter' || e.key === ' ') {
      e.preventDefault()
      if (open) pick(options[active])
      else openList(Math.max(selectedIndex, 0))
    } else if ((e.key === 'Delete' || e.key === 'Backspace') && canClear) {
      e.preventDefault()
      clear()
    } else if (e.key.length === 1 && !e.ctrlKey && !e.metaKey && !e.altKey) {
      typeAhead(e.key)
    }
  }

  return (
    <span className={`relative block w-full ${className}`}>
      <button
        ref={button}
        type="button"
        role="combobox"
        aria-haspopup="listbox"
        aria-expanded={open}
        aria-controls={open ? listId : undefined}
        aria-activedescendant={open && active >= 0 ? `${listId}-${active}` : undefined}
        aria-label={ariaLabel}
        aria-invalid={error ? true : undefined}
        disabled={disabled}
        onClick={() => (open ? setOpen(false) : openList())}
        onKeyDown={onKeyDown}
        className={`${controlClass(error)} flex items-center justify-between gap-2 text-left disabled:cursor-not-allowed ${open ? dropdownOpenClass : ''}`}
      >
        <span className={`min-w-0 flex-1 ${canClear ? 'pr-7' : ''}`}>
          <span className={`block truncate ${selected ? '' : 'text-slate-500'}`}>{shown?.label}</span>
          {/* Lebar kolom mengikuti pilihan terpanjang (filter dengan lebar otomatis tidak bergeser). */}
          {all.map((o) => <span key={o.value} className="block h-0 overflow-hidden whitespace-nowrap" aria-hidden>{o.label}</span>)}
        </span>
        <ChevronDown size={16} className={`shrink-0 text-slate-500 transition-transform ${open ? 'rotate-180' : ''}`} aria-hidden />
      </button>
      {canClear && (
        <button
          type="button"
          tabIndex={-1}
          aria-label="Kosongkan pilihan"
          onClick={clear}
          className="absolute right-8 top-1/2 flex h-8 w-8 -translate-y-1/2 items-center justify-center rounded-md text-slate-500 hover:bg-slate-100 hover:text-slate-900"
        >
          <X size={14} aria-hidden />
        </button>
      )}
      {open && createPortal(
        <ul
          ref={list}
          id={listId}
          role="listbox"
          style={style}
          // Fokus tetap di tombol agar keyboard terus jalan.
          onMouseDown={(e) => e.preventDefault()}
          onMouseLeave={() => setActive(-1)}
          className={`fixed z-50 ${dropdownPanelClass}`}
        >
          {options.length === 0 && <li className="px-3 py-2 text-slate-500">Belum ada pilihan.</li>}
          {options.map((o, i) => (
            <li
              key={o.value}
              id={`${listId}-${i}`}
              role="option"
              aria-selected={i === selectedIndex}
              onMouseEnter={() => setActive(i)}
              onClick={() => pick(o)}
              className={`flex items-center justify-between gap-2 ${dropdownOptionClass(i === active, i === selectedIndex)}`}
            >
              <span className="min-w-0">{o.label}</span>
              {i === selectedIndex && <Check size={16} className="shrink-0" aria-hidden />}
            </li>
          ))}
        </ul>,
        document.body,
      )}
    </span>
  )
}
