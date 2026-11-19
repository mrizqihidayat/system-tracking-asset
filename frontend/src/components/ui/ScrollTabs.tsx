import { useCallback, useEffect, useLayoutEffect, useRef, useState, type ReactNode } from 'react'

interface Props {
  children: ReactNode
  /** Kelas baris tab (flex, gap, garis bawah, dll.). */
  className?: string
  /** Kunci tab aktif: setiap berubah, tab aktif (`aria-selected="true"`) digulir agar terlihat. */
  activeKey?: string
  /** Label daftar tab untuk pembaca layar. */
  label: string
}

/**
 * Baris tab yang bisa digeser ke samping di layar sempit (T45, temuan UAT M2): gradasi di tepi kiri/kanan
 * menandakan masih ada tab tersembunyi, dan tab aktif otomatis digulir agar terlihat.
 */
export default function ScrollTabs({ children, className = '', activeKey, label }: Props) {
  const ref = useRef<HTMLDivElement>(null)
  const [edges, setEdges] = useState({ left: false, right: false })

  const updateEdges = useCallback(() => {
    const el = ref.current
    if (!el) return
    const left = el.scrollLeft > 2
    const right = el.scrollLeft + el.clientWidth < el.scrollWidth - 2
    setEdges((prev) => (prev.left === left && prev.right === right ? prev : { left, right }))
  }, [])

  useEffect(() => {
    const el = ref.current
    if (!el) return
    updateEdges()
    const observer = new ResizeObserver(updateEdges)
    observer.observe(el)
    return () => observer.disconnect()
  }, [updateEdges])

  // Gulir hanya baris tab (bukan halaman) sampai tab aktif terlihat penuh, dengan sedikit jarak dari gradasi.
  useLayoutEffect(() => {
    const el = ref.current
    const active = el?.querySelector<HTMLElement>('[aria-selected="true"]')
    if (!el || !active) return
    const start = active.offsetLeft
    const end = start + active.offsetWidth
    if (start < el.scrollLeft) el.scrollLeft = Math.max(0, start - 24)
    else if (end > el.scrollLeft + el.clientWidth) el.scrollLeft = end - el.clientWidth + 24
    updateEdges()
  }, [activeKey, updateEdges])

  return (
    <div className="relative">
      <div
        ref={ref}
        onScroll={updateEdges}
        role="tablist"
        aria-label={label}
        className={`relative overflow-x-auto [scrollbar-width:none] [&::-webkit-scrollbar]:hidden ${className}`}
      >
        {children}
      </div>
      {edges.left && <span aria-hidden className="pointer-events-none absolute inset-y-0 left-0 w-10 bg-linear-to-r from-canvas to-transparent" />}
      {edges.right && <span aria-hidden className="pointer-events-none absolute inset-y-0 right-0 w-10 bg-linear-to-l from-canvas to-transparent" />}
    </div>
  )
}
