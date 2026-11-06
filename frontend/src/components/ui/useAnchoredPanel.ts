import { useCallback, useEffect, useState, type CSSProperties, type RefObject } from 'react'

const MAX_HEIGHT = 288
const OPTION_HEIGHT = 44
const PANEL_PADDING = 14
const VIEWPORT_GAP = 8
const ANCHOR_GAP = 6

/**
 * Posisi daftar pilihan yang dirender ke body (fixed): tidak terpotong area scroll popup, mengikuti kolom saat
 * di-scroll, pindah ke atas jika ruang bawah kurang, dan tertutup saat klik di luar.
 * Panggil `place()` tepat sebelum membuka. `fit`: selebar kolom, atau mengikuti isi dan rata kiri/kanan dengan kolom.
 * `optionHeight` & `panelPadding`: ukuran isi panel, untuk menilai apakah muat di bawah kolom.
 */
export function useAnchoredPanel(
  anchor: RefObject<HTMLElement | null>,
  panel: RefObject<HTMLElement | null>,
  open: boolean,
  optionCount: number,
  onClose: () => void,
  { fit = 'width', optionHeight = OPTION_HEIGHT, panelPadding = PANEL_PADDING }: { fit?: 'width' | 'left' | 'right'; optionHeight?: number; panelPadding?: number } = {},
) {
  const [style, setStyle] = useState<CSSProperties>({})

  const place = useCallback(() => {
    const rect = anchor.current?.getBoundingClientRect()
    if (!rect) return
    const below = window.innerHeight - rect.bottom - ANCHOR_GAP - VIEWPORT_GAP
    const above = rect.top - ANCHOR_GAP - VIEWPORT_GAP
    const wanted = Math.min(MAX_HEIGHT, Math.max(optionCount, 1) * optionHeight + panelPadding)
    const upward = below < wanted && above > below
    setStyle({
      ...(fit === 'right' ? { right: document.documentElement.clientWidth - rect.right } : { left: rect.left }),
      ...(fit === 'width' ? { width: rect.width } : {}),
      maxHeight: Math.min(MAX_HEIGHT, upward ? above : below),
      ...(upward ? { bottom: window.innerHeight - rect.top + ANCHOR_GAP } : { top: rect.bottom + ANCHOR_GAP }),
    })
  }, [anchor, optionCount, fit, optionHeight, panelPadding])

  useEffect(() => {
    if (!open) return
    const close = (e: MouseEvent) => {
      const target = e.target as Node
      // Klik label kolomnya sendiri diteruskan ke kolom, jangan ditutup dulu lalu terbuka lagi.
      const inField = anchor.current?.contains(target) || anchor.current?.closest('label')?.contains(target)
      if (!inField && !panel.current?.contains(target)) onClose()
    }
    const follow = (e: Event) => {
      if (e.target !== panel.current) place()
    }
    document.addEventListener('mousedown', close)
    window.addEventListener('scroll', follow, true)
    window.addEventListener('resize', place)
    return () => {
      document.removeEventListener('mousedown', close)
      window.removeEventListener('scroll', follow, true)
      window.removeEventListener('resize', place)
    }
  }, [open, anchor, panel, place, onClose])

  return { style, place }
}
