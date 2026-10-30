import { Camera } from 'lucide-react'
import { Scanner } from '@yudiel/react-qr-scanner'
import { useEffect, useRef, useState, type KeyboardEvent } from 'react'
import { AssetCode, StatusBadge } from '../../components/ui/Badge'
import Button from '../../components/ui/Button'
import { FormField, Input } from '../../components/ui/FormField'
import Modal from '../../components/ui/Modal'
import { useToast } from '../../components/ui/toast-context'
import { api, errorMessage } from '../../lib/api'
import type { Paginated } from '../../types/api'
import type { Asset } from '../../types/asset'
import { useScanLookup } from '../scan/useScanLookup'

interface Props {
  asset: Asset | null
  onPick: (asset: Asset | null) => void
  error?: string
}

const MIN_CHARS = 2
const MAX_SUGGESTIONS = 8

/** "Aset dipinjam": ketik kode (saran aset Tersedia muncul di bawahnya) atau scan QR; hanya aset yang boleh dipinjam yang diterima. */
export default function AssetPicker({ asset, onPick, error }: Props) {
  const toast = useToast()
  const { lookup, loading } = useScanLookup()
  const [text, setText] = useState('')
  const [scanning, setScanning] = useState(false)
  const [suggestions, setSuggestions] = useState<{ query: string; items: Asset[] } | null>(null)
  const [open, setOpen] = useState(false)
  const [active, setActive] = useState(-1)
  const box = useRef<HTMLDivElement>(null)

  const query = text.trim()

  // Saran: aset Tersedia yang cocok (nama/kode/S-N/kode lama), dengan jeda agar tidak memanggil API tiap ketikan.
  useEffect(() => {
    if (query.length < MIN_CHARS) return
    let cancelled = false
    const t = setTimeout(() => {
      api
        .get<Paginated<Asset>>('/assets', { params: { search: query, status: 'Tersedia', per_page: MAX_SUGGESTIONS } })
        .then((res) => {
          if (cancelled) return
          setSuggestions({ query, items: res.data.data })
          setActive(-1)
        })
        .catch(() => undefined) // saran opsional; Cari/Scan tetap menampilkan error
    }, 300)
    return () => {
      cancelled = true
      clearTimeout(t)
    }
  }, [query])

  useEffect(() => {
    const close = (e: MouseEvent) => {
      if (!box.current?.contains(e.target as Node)) setOpen(false)
    }
    document.addEventListener('mousedown', close)
    return () => document.removeEventListener('mousedown', close)
  }, [])

  const items = query.length >= MIN_CHARS && suggestions?.query === query ? suggestions.items : []
  const showList = open && query.length >= MIN_CHARS && suggestions?.query === query

  const pick = (a: Asset) => {
    setText('')
    setOpen(false)
    setSuggestions(null)
    onPick(a)
  }

  const find = async (raw: string) => {
    if (!raw.trim() || loading) return
    try {
      const result = await lookup(raw)
      if (!result.actions.includes('pinjam')) {
        toast.error(`${result.asset.code} berstatus ${result.asset.status}, tidak dapat dipinjam.`)
        return
      }
      pick(result.asset)
    } catch (e) {
      toast.error(errorMessage(e))
    }
  }

  const onKey = (e: KeyboardEvent) => {
    if (e.key === 'ArrowDown' && items.length) {
      e.preventDefault()
      setOpen(true)
      setActive((i) => (i + 1) % items.length)
    } else if (e.key === 'ArrowUp' && items.length) {
      e.preventDefault()
      setActive((i) => (i <= 0 ? items.length - 1 : i - 1))
    } else if (e.key === 'Escape') {
      setOpen(false)
    } else if (e.key === 'Enter') {
      e.preventDefault()
      if (showList && active >= 0 && items[active]) pick(items[active])
      else find(text)
    }
  }

  const roomLabel = (a: Asset) => (a.room ? `${a.room.name}${a.room.building ? `, ${a.room.building.name}` : ''}` : null)

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
            {showList && (
              <ul
                id="asset-suggestions"
                role="listbox"
                className="absolute z-10 mt-1 max-h-72 w-full overflow-auto rounded-lg border border-slate-200 bg-white p-1 text-sm shadow-[0_8px_24px_rgb(20_33_46/0.12)]"
              >
                {items.length === 0 ? (
                  <li className="px-3 py-2.5 text-slate-500">Tidak ada aset tersedia yang cocok.</li>
                ) : (
                  items.map((a, i) => (
                    <li key={a.id} id={`asset-suggestion-${i}`} role="option" aria-selected={i === active}>
                      <button
                        type="button"
                        onMouseDown={(e) => e.preventDefault()}
                        onMouseEnter={() => setActive(i)}
                        onClick={() => pick(a)}
                        className={`flex w-full flex-col items-start gap-0.5 rounded-md px-3 py-2 text-left ${i === active ? 'bg-brand-50' : ''}`}
                      >
                        <span className="flex min-w-0 max-w-full items-center gap-2">
                          <AssetCode code={a.code} />
                          <span className="truncate font-medium">{a.name}</span>
                        </span>
                        {roomLabel(a) && <span className="text-xs text-slate-500">{roomLabel(a)}</span>}
                      </button>
                    </li>
                  ))
                )}
              </ul>
            )}
          </div>
          <Button variant="secondary" loading={loading} onClick={() => find(text)}>Cari</Button>
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
