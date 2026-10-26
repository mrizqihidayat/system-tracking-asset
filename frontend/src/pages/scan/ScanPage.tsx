import { Scanner } from '@yudiel/react-qr-scanner'
import { useState, type FormEvent } from 'react'
import Button from '../../components/ui/Button'
import { Input } from '../../components/ui/FormField'
import { useToast } from '../../components/ui/toast-context'
import { errorMessage } from '../../lib/api'
import AssetActionPanel from './AssetActionPanel'
import { useScanLookup, type ScanResult } from './useScanLookup'

export default function ScanPage() {
  const toast = useToast()
  const { lookup, loading } = useScanLookup()
  const [result, setResult] = useState<ScanResult | null>(null)
  const [manual, setManual] = useState('')
  const [cameraError, setCameraError] = useState('')

  const search = async (raw: string) => {
    if (!raw.trim() || loading) return
    try {
      setResult(await lookup(raw))
    } catch (e) {
      toast.error(errorMessage(e))
    }
  }

  const submitManual = (e: FormEvent) => {
    e.preventDefault()
    search(manual)
  }

  return (
    <div className="max-w-xl space-y-5">
      <h1 className="text-2xl font-semibold">Scan QR</h1>

      <div className="overflow-hidden rounded-lg border border-slate-200 bg-black">
        {cameraError ? (
          <p className="p-6 text-sm text-white">{cameraError}</p>
        ) : (
          <Scanner
            paused={result !== null || loading}
            onScan={(codes) => codes[0] && search(codes[0].rawValue)}
            onError={() =>
              setCameraError('Kamera tidak dapat dibuka. Izinkan akses kamera di browser, atau ketik kode aset di bawah.')
            }
            formats={['qr_code']}
            constraints={{ facingMode: 'environment' }}
            scanDelay={1500}
            allowMultiple
          />
        )}
      </div>

      <form onSubmit={submitManual} className="flex gap-2">
        <Input
          value={manual}
          onChange={(e) => setManual(e.target.value)}
          placeholder="Atau ketik kode aset"
          aria-label="Kode aset"
          className="flex-1"
        />
        <Button type="submit" loading={loading}>Cari</Button>
      </form>

      {result && (
        <AssetActionPanel
          asset={result.asset}
          actions={result.actions}
          matchedBy={result.matchedBy}
          onClose={() => setResult(null)}
          onChanged={() =>
            lookup(result.asset.code)
              .then(setResult)
              .catch((e) => {
                toast.error(errorMessage(e))
                setResult(null)
              })
          }
        />
      )}
    </div>
  )
}
