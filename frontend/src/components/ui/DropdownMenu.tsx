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
        className={`inline-flex min-h-10 items-center justify-center gap-2 whitespace-nowrap rounded-lg border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-800 transition-colors hover:border-slate-300 hover:bg-slate-50 ${triggerClassName}`}
      >
        {trigger}
      </button>
      {open && (
        <ul
          role="menu"
          className={`absolute z-20 mt-1 min-w-52 rounded-lg border border-slate-200 bg-white p-1 text-sm shadow-[0_8px_24px_rgb(20_33_46/0.12)] ${align === 'right' ? 'right-0' : 'left-0'}`}
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
        </ul>
      )}
    </div>
  )
}
