import { Camera } from 'lucide-react'
import { useCallback, useEffect, useLayoutEffect, useRef, useState, type KeyboardEvent } from 'react'
import { createPortal } from 'react-dom'
import { AssetCode, StatusBadge } from '../../components/ui/Badge'
import Button from '../../components/ui/Button'
import { dropdownOptionClass, dropdownPanelClass } from '../../components/ui/dropdown-class'
import { FormField, Input } from '../../components/ui/FormField'
import Modal from '../../components/ui/Modal'
import { useToast } from '../../components/ui/toast-context'
import { useAnchoredPanel } from '../../components/ui/useAnchoredPanel'
import { api, errorMessage } from '../../lib/api'
import { Scanner } from '../../lib/qr-scanner'
import type { Paginated } from '../../types/api'
import type { Asset, AssetListItem } from '../../types/asset'
import { useScanLookup } from '../scan/useScanLookup'
import { BOOKABLE, unavailableReason } from './asset-availability'

/** Saran memakai baris daftar, Enter/Scan memakai data lengkap; form hanya butuh field bersama ini. */
export type PickedAsset = Pick<Asset, 'id' | 'code' | 'name' | 'condition' | 'status'>

interface Props {
  asset: PickedAsset | null
  onPick: (asset: PickedAsset | null) => void
  error?: string
  planned?: boolean
}

const MIN_CHARS = 2
const SEARCH_DELAY_MS = 100
const PAGE_SIZE = 100
/** Tinggi satu saran (kode + nama + lokasi), untuk menilai apakah daftar muat di bawah kolom. */
const OPTION_HEIGHT = 60

/**
 * "Aset dipinjam": ketik kode (saran muncul di bawahnya) atau scan QR; hanya aset yang boleh dipinjam yang diterima.
 * Aset yang tidak bisa dipilih tetap tampil, tidak bisa diklik, dan diberi alasan (T45, temuan UAT A5).
 * `planned` (tanggal pinjam di masa depan): aset Dipinjam/Perbaikan juga boleh dipilih untuk pemesanan.
 */
export default function AssetPicker({ asset, onPick, error, planned = false }: Props) {
  const toast = useToast()
  const { lookup, loading } = useScanLookup()
  const [text, setText] = useState('')
  const [scanning, setScanning] = useState(false)
  const [suggestions, setSuggestions] = useState<{ query: string; items: AssetListItem[] } | null>(null)
  const [open, setOpen] = useState(false)
  const [active, setActive] = useState(-1)
  const box = useRef<HTMLDivElement>(null)
  const list = useRef<HTMLUListElement>(null)

  const query = text.trim()

  // Saran: semua aset yang cocok (nama/kode/S-N/kode lama), yang bisa dipilih lebih dulu. Ketikan baru membatalkan request sebelumnya.
  useEffect(() => {
    if (query.length < MIN_CHARS) return
    const controller = new AbortController()
    const t = setTimeout(async () => {
      try {
        const items: AssetListItem[] = []
        for (let page = 1, lastPage = 1; page <= lastPage; page++) {
          const res = await api.get<Paginated<AssetListItem>>('/assets', {
            params: { search: query, per_page: PAGE_SIZE, page },
            signal: controller.signal,
          })
          items.push(...res.data.data)
          lastPage = res.data.meta.last_page
        }
        const available = (a: AssetListItem) => (unavailableReason(a, planned) === null ? 0 : 1)
        setSuggestions({ query, items: [...items].sort((a, b) => available(a) - available(b)) })
        setActive(-1)
      } catch {
        // saran opsional; Enter/Scan tetap menampilkan error
      }
    }, SEARCH_DELAY_MS)
    return () => {
      controller.abort()
      clearTimeout(t)
    }
  }, [query, planned])

  const items = query.length >= MIN_CHARS && suggestions?.query === query ? suggestions.items : []
  const showList = open && query.length >= MIN_CHARS && suggestions?.query === query
  // Panah atas/bawah hanya berhenti di aset yang bisa dipilih.
  const selectable = items.flatMap((a, i) => (unavailableReason(a, planned) === null ? [i] : []))

  // Daftar dirender ke body (fixed) agar tidak terpotong area scroll popup; posisinya mengikuti kolom.
  const close = useCallback(() => setOpen(false), [])
  const { style, place } = useAnchoredPanel(box, list, showList, Math.max(items.length, 1), close, { optionHeight: OPTION_HEIGHT })
  useLayoutEffect(() => {
    if (showList) place()
  }, [showList, items.length, place])

  const pick = (a: PickedAsset) => {
    setText('')
    setOpen(false)
    setSuggestions(null)
    onPick(a)
  }

  const find = async (raw: string) => {
    if (!raw.trim() || loading) return
    try {
      const result = await lookup(raw)
      if (planned ? !BOOKABLE.includes(result.asset.status) : !result.actions.includes('pinjam')) {
        toast.error(`${result.asset.code} berstatus ${result.asset.status}, tidak dapat ${planned ? 'dipesan' : 'dipinjam'}.`)
        return
      }
      pick(result.asset)
    } catch (e) {
      toast.error(errorMessage(e))
    }
  }

  const onKey = (e: KeyboardEvent) => {
    if (e.key === 'ArrowDown' && selectable.length) {
      e.preventDefault()
      setOpen(true)
      setActive((i) => selectable.find((s) => s > i) ?? selectable[0])
    } else if (e.key === 'ArrowUp' && selectable.length) {
      e.preventDefault()
      setActive((i) => [...selectable].reverse().find((s) => s < i) ?? selectable[selectable.length - 1])
    } else if (e.key === 'Escape') {
      if (showList) {
        // Hanya menutup daftar, bukan popup di belakangnya.
        e.stopPropagation()
        setOpen(false)
      }
    } else if (e.key === 'Enter') {
      e.preventDefault()
      if (showList && active >= 0 && items[active]) pick(items[active])
      else find(text)
    }
  }

  const roomLabel = (a: AssetListItem) => (a.room_name ? `${a.room_name}${a.building_name ? `, ${a.building_name}` : ''}` : null)

  return (
    <FormField label="Aset dipinjam" required error={error}>
      {asset ? (
        <div className="flex items-center justify-between gap-3 rounded-lg border border-brand-200 bg-brand-50 px-3 py-2.5">
          <div className="min-w-0 text-sm">
            <AssetCode code={asset.code} className="bg-white!" />
            <p className="mt-1 truncate font-medium">{asset.name}</p>
          </div>
          <div className="flex shrink-0 items-center gap-2">
            <StatusBadge status={asset.status} />
            <Button variant="secondary" className="px-3!" onClick={() => onPick(null)}>Ganti</Button>
          </div>
        </div>
      ) : (
        <div className="flex gap-2">
          <div ref={box} className="relative min-w-0 flex-1">
            <Input
              value={text}
              onChange={(e) => {
                setText(e.target.value)
                setOpen(true)
              }}
              onFocus={() => setOpen(true)}
              onKeyDown={onKey}
              placeholder="Scan QR atau ketik kode / nama aset"
              autoComplete="off"
              role="combobox"
              aria-expanded={showList}
              aria-controls="asset-suggestions"
              aria-activedescendant={active >= 0 ? `asset-suggestion-${active}` : undefined}
              error={error}
            />
            {showList && createPortal(
              <ul
                ref={list}
                id="asset-suggestions"
                role="listbox"
                style={style}
                onMouseDown={(e) => e.preventDefault()}
                className={`fixed z-50 ${dropdownPanelClass}`}
              >
                {items.length === 0 ? (
                  <li className="px-3 py-2.5 text-slate-500">Tidak ada aset yang cocok.</li>
                ) : (
                  items.map((a, i) => {
                    const reason = unavailableReason(a, planned)
                    return (
                      <li key={a.id} id={`asset-suggestion-${i}`} role="option" aria-selected={i === active} aria-disabled={reason !== null || undefined}>
                        {reason ? (
                          // Tidak bisa dipilih: tetap tampil dengan alasannya supaya staf tahu kenapa.
                          <div className="flex w-full cursor-not-allowed flex-col items-start gap-0.5 rounded-lg px-3 py-2 text-left">
                            <span className="flex min-w-0 max-w-full items-center gap-2 opacity-70">
                              <AssetCode code={a.code} />
                              <span className="truncate font-medium text-slate-600">{a.name}</span>
                            </span>
                            <span className="text-xs font-medium text-bad-700">{reason}</span>
                          </div>
                        ) : (
                          <button
                            type="button"
                            onMouseDown={(e) => e.preventDefault()}
                            onMouseEnter={() => setActive(i)}
                            onClick={() => pick(a)}
                            className={`flex flex-col items-start gap-0.5 ${dropdownOptionClass(i === active)}`}
                          >
                            <span className="flex min-w-0 max-w-full items-center gap-2">
                              <AssetCode code={a.code} />
                              <span className="truncate font-medium">{a.name}</span>
                            </span>
                            {roomLabel(a) && <span className="text-xs text-slate-500">{roomLabel(a)}</span>}
                          </button>
                        )}
                      </li>
                    )
                  })
                )}
              </ul>,
              document.body,
            )}
          </div>
          <Button variant="secondary" aria-label="Scan QR" onClick={() => setScanning(true)}>
            <Camera size={16} />
          </Button>
        </div>
      )}

      <Modal open={scanning} title="Scan QR aset" onClose={() => setScanning(false)}>
        <Scanner
          paused={loading}
          onScan={async (codes) => {
            if (!codes[0]) return
            setScanning(false)
            await find(codes[0].rawValue)
          }}
          onError={() => {
            setScanning(false)
            toast.error('Kamera tidak dapat dibuka. Ketik kode aset saja.')
          }}
          formats={['qr_code']}
          constraints={{ facingMode: 'environment' }}
        />
      </Modal>
    </FormField>
  )
}
