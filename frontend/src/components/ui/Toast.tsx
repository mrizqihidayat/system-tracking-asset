import { CheckCircle2, Info, XCircle } from 'lucide-react'
import { useCallback, useMemo, useRef, useState, type ReactNode } from 'react'
import { ToastContext, type ToastApi, type ToastKind } from './toast-context'

interface Item {
  id: number
  message: string
  kind: ToastKind
}

const styles: Record<ToastKind, string> = {
  success: 'text-ok-700',
  error: 'text-bad-700',
  info: 'text-brand-700',
}
const icons = { success: CheckCircle2, error: XCircle, info: Info }

export function ToastProvider({ children }: { children: ReactNode }) {
  const [items, setItems] = useState<Item[]>([])
  const nextId = useRef(1)

  const show = useCallback((message: string, kind: ToastKind = 'info') => {
    const id = nextId.current++
    setItems((prev) => [...prev, { id, message, kind }])
    setTimeout(() => setItems((prev) => prev.filter((i) => i.id !== id)), 4000)
  }, [])

  const api = useMemo<ToastApi>(
    () => ({
      show,
      success: (m) => show(m, 'success'),
      error: (m) => show(m, 'error'),
    }),
    [show],
  )

  return (
    <ToastContext.Provider value={api}>
      {children}
      <div className="fixed inset-x-4 top-4 z-50 flex flex-col gap-2 sm:left-auto sm:w-96" role="status">
        {items.map(({ id, message, kind }) => {
          const Icon = icons[kind]
          return (
            <div
              key={id}
              className="flex items-start gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-800 shadow-[0_8px_24px_rgb(20_33_46/0.14)]"
            >
              <Icon size={18} className={`mt-0.5 shrink-0 ${styles[kind]}`} />
              <span>{message}</span>
            </div>
          )
        })}
      </div>
    </ToastContext.Provider>
  )
}
