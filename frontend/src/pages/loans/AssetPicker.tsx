import { Camera } from 'lucide-react'
import { Scanner } from '@yudiel/react-qr-scanner'
import { useState, type KeyboardEvent } from 'react'
import { StatusBadge } from '../../components/ui/Badge'
import Button from '../../components/ui/Button'
import { FormField, Input } from '../../components/ui/FormField'
import Modal from '../../components/ui/Modal'
import { useToast } from '../../components/ui/toast-context'
import { errorMessage } from '../../lib/api'
import type { Asset } from '../../types/asset'
import { useScanLookup } from '../scan/useScanLookup'

interface Props {
  asset: Asset | null
  onPick: (asset: Asset | null) => void
  error?: string
}

/** "Aset dipinjam": ketik kode (Enter) atau scan QR; hanya aset yang boleh dipinjam yang diterima. */
export default function AssetPicker({ asset, onPick, error }: Props) {
  const toast = useToast()
  const { lookup, loading } = useScanLookup()
  const [text, setText] = useState('')
  const [scanning, setScanning] = useState(false)

  const find = async (raw: string) => {
    if (!raw.trim() || loading) return
    try {
      const result = await lookup(raw)
      if (!result.actions.includes('pinjam')) {
        toast.error(`${result.asset.code} berstatus ${result.asset.status}, tidak dapat dipinjam.`)
        return
      }
      setText('')
      onPick(result.asset)
    } catch (e) {
      toast.error(errorMessage(e))
    }
  }

  const onKey = (e: KeyboardEvent) => {
    if (e.key === 'Enter') {
      e.preventDefault()
      find(text)
    }
  }

  return (
    <FormField label="Aset dipinjam" required error={error}>
      {asset ? (
        <div className="flex items-center justify-between gap-3 rounded-md border border-slate-300 bg-slate-50 px-3 py-2">
          <div className="min-w-0 text-sm">
            <p className="truncate font-medium">{asset.name}</p>
            <p className="font-mono text-xs text-slate-500">{asset.code}</p>
          </div>
          <div className="flex shrink-0 items-center gap-2">
            <StatusBadge status={asset.status} />
            <Button variant="secondary" className="px-2! py-1!" onClick={() => onPick(null)}>Ganti</Button>
          </div>
        </div>
      ) : (
        <div className="flex gap-2">
          <Input
            value={text}
            onChange={(e) => setText(e.target.value)}
            onKeyDown={onKey}
            placeholder="Scan QR atau ketik kode aset"
            error={error}
          />
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
