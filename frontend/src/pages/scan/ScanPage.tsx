import { CameraOff } from 'lucide-react'
import { useState, type FormEvent } from 'react'
import Button from '../../components/ui/Button'
import { Input } from '../../components/ui/FormField'
import { PageHeader } from '../../components/ui/Page'
import { useToast } from '../../components/ui/toast-context'
import { errorMessage } from '../../lib/api'
import { Scanner } from '../../lib/qr-scanner'
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
    <div className="space-y-5">
      <PageHeader title="Scan QR" description="Arahkan kamera ke label QR aset, atau ketik kodenya." back={{ to: '/', label: 'Dashboard' }} />

      <div className="grid items-start gap-5 lg:grid-cols-[minmax(0,36rem)_minmax(0,22rem)]">
      <div className="mx-auto w-full max-w-xl overflow-hidden rounded-xl bg-slate-900 lg:mx-0">
        {cameraError ? (
          <div className="flex aspect-square flex-col items-center justify-center gap-3 p-6 text-center text-sm text-slate-100">
            <CameraOff size={32} className="text-slate-400" />
            {cameraError}
          </div>
        ) : (
          <Scanner
            paused={result !== null || loading}
            onScan={(codes) => codes[0] && search(codes[0].rawValue)}
            onError={() =>
              setCameraError('Kamera tidak dapat dibuka. Izinkan akses kamera di browser, atau ketik kode asetnya.')
            }
            formats={['qr_code']}
            constraints={{ facingMode: 'environment' }}
            scanDelay={1500}
            allowMultiple
          />
        )}
      </div>

      <form onSubmit={submitManual} className="mx-auto w-full max-w-xl space-y-3 rounded-xl border border-slate-200 bg-white p-5 lg:mx-0">
        <div>
          <h2 className="font-semibold">Ketik kode aset</h2>
          <p className="mt-0.5 text-sm text-slate-500">Kode saat ini atau kode lama, mis. SHD-PRJ-001.</p>
        </div>
        <div className="flex gap-2">
          <Input
            value={manual}
            onChange={(e) => setManual(e.target.value)}
            placeholder="SHD-…"
            aria-label="Kode aset"
            autoCapitalize="characters"
            className="flex-1 font-mono"
          />
          <Button type="submit" loading={loading}>Cari</Button>
        </div>
      </form>
      </div>

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
