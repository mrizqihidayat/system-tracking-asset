import { useEffect, useRef, useState, type ReactNode } from 'react'

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
}

export default function DropdownMenu({ trigger, items, label, align = 'right', triggerClassName = '' }: Props) {
  const [open, setOpen] = useState(false)
  const box = useRef<HTMLDivElement>(null)

  useEffect(() => {
    if (!open) return
    const close = (e: MouseEvent) => {
      if (!box.current?.contains(e.target as Node)) setOpen(false)
    }
    const esc = (e: KeyboardEvent) => e.key === 'Escape' && setOpen(false)
    document.addEventListener('mousedown', close)
    document.addEventListener('keydown', esc)
    return () => {
      document.removeEventListener('mousedown', close)
      document.removeEventListener('keydown', esc)
    }
  }, [open])

  return (
    <div ref={box} className="relative">
      <button
        type="button"
        aria-label={label}
        aria-haspopup="menu"
        aria-expanded={open}
        onClick={() => setOpen((v) => !v)}
        className={`inline-flex items-center justify-center gap-2 rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 ${triggerClassName}`}
      >
        {trigger}
      </button>
      {open && (
        <ul
          role="menu"
          className={`absolute z-20 mt-1 min-w-48 rounded-md border border-slate-200 bg-white py-1 text-sm shadow-lg ${align === 'right' ? 'right-0' : 'left-0'}`}
        >
          {items.map((item) => (
            <li key={item.label} role="none">
              <button
                role="menuitem"
                type="button"
                className={`w-full px-3 py-2 text-left hover:bg-slate-50 ${item.danger ? 'text-red-700' : ''}`}
                onClick={() => {
                  setOpen(false)
                  item.onClick()
                }}
              >
                {item.label}
              </button>
            </li>
          ))}
        </ul>
      )}
    </div>
  )
}
