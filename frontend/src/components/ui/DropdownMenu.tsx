import { useCallback, useEffect, useRef, useState, type ReactNode } from 'react'
import { createPortal } from 'react-dom'
import { useAnchoredPanel } from './useAnchoredPanel'

export interface MenuItem {
  label: string
  onClick: () => void
  danger?: boolean
}

interface Props {
  trigger: ReactNode
  items: MenuItem[]
  label: string
  align?: 'left' | 'right'
  triggerClassName?: string
  /** Dipanggil saat menu dibuka (mis. memuat data lebih awal). */
  onOpen?: () => void
}

export default function DropdownMenu({ trigger, items, label, align = 'right', triggerClassName = '', onOpen }: Props) {
  const [open, setOpen] = useState(false)
  const button = useRef<HTMLButtonElement>(null)
  const panel = useRef<HTMLUListElement>(null)
  const close = useCallback(() => setOpen(false), [])
  // Dirender ke body agar tidak terpotong area scroll tabel. Baris menu 36px, bingkai + padding 10px.
  const { style, place } = useAnchoredPanel(button, panel, open, items.length, close, { fit: align, optionHeight: 36, panelPadding: 10 })

  useEffect(() => {
    if (!open) return
    const esc = (e: KeyboardEvent) => e.key === 'Escape' && setOpen(false)
    document.addEventListener('keydown', esc)
    return () => document.removeEventListener('keydown', esc)
  }, [open])

  return (
    <div className="relative">
      <button
        ref={button}
        type="button"
        aria-label={label}
        aria-haspopup="menu"
        aria-expanded={open}
        onClick={() => {
          if (!open) {
            onOpen?.()
            place()
          }
          setOpen((v) => !v)
        }}
        className={`inline-flex min-h-10 items-center justify-center gap-2 whitespace-nowrap rounded-lg border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-800 transition-colors hover:border-slate-300 hover:bg-slate-50 ${triggerClassName}`}
      >
        {trigger}
      </button>
      {open && createPortal(
        <ul
          ref={panel}
          role="menu"
          style={style}
          className="fixed z-50 min-w-52 overflow-y-auto rounded-lg border border-slate-200 bg-white p-1 text-sm shadow-[0_8px_24px_rgb(20_33_46/0.12)]"
        >
          {items.map((item) => (
            <li key={item.label} role="none">
              <button
                role="menuitem"
                type="button"
                className={`w-full rounded-md px-3 py-2 text-left hover:bg-slate-100 ${item.danger ? 'text-bad-700 hover:bg-bad-50' : ''}`}
                onClick={() => {
                  setOpen(false)
                  item.onClick()
                }}
              >
                {item.label}
              </button>
            </li>
          ))}
        </ul>,
        document.body,
      )}
    </div>
  )
}
