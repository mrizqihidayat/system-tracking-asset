import { CheckCircle2, Info, XCircle } from 'lucide-react'
import { useCallback, useMemo, useRef, useState, type ReactNode } from 'react'
import { ToastContext, type ToastApi, type ToastKind } from './toast-context'

interface Item {
  id: number
  message: string
  kind: ToastKind
}

const styles: Record<ToastKind, string> = {
  success: 'border-green-300 bg-green-50 text-green-800',
  error: 'border-red-300 bg-red-50 text-red-800',
  info: 'border-slate-300 bg-white text-slate-800',
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
      <div className="fixed right-4 top-4 z-50 flex w-80 max-w-[calc(100vw-2rem)] flex-col gap-2" role="status">
        {items.map(({ id, message, kind }) => {
          const Icon = icons[kind]
          return (
            <div key={id} className={`flex items-start gap-2 rounded-md border px-3 py-2 text-sm shadow ${styles[kind]}`}>
              <Icon size={18} className="mt-0.5 shrink-0" />
              <span>{message}</span>
            </div>
          )
        })}
      </div>
    </ToastContext.Provider>
  )
}
