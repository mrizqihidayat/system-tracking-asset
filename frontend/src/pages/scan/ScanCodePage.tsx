import { useCallback, useEffect, useState } from 'react'
import { useNavigate, useParams, useSearchParams } from 'react-router-dom'
import { LoadingLabel, Skeleton } from '../../components/ui/Skeleton'
import { useToast } from '../../components/ui/toast-context'
import { errorMessage } from '../../lib/api'
import AssetActionPanel from './AssetActionPanel'
import { useScanLookup, type ScanResult } from './useScanLookup'

/** /scan/:code — membuka hasil scan langsung; ?aksi=<kunci> membuka form aksi (dari halaman publik). */
export default function ScanCodePage() {
  const { code = '' } = useParams()
  const [params] = useSearchParams()
  const navigate = useNavigate()
  const toast = useToast()
  const { lookup } = useScanLookup()
  const [result, setResult] = useState<ScanResult | null>(null)

  const load = useCallback(
    () =>
      lookup(code)
        .then(setResult)
        .catch((e) => {
          toast.error(errorMessage(e))
          navigate('/scan', { replace: true })
        }),
    [code, lookup, navigate, toast],
  )

  useEffect(() => {
    load()
  }, [load])

  if (!result) {
    return (
      <div className="max-w-lg space-y-3 rounded-xl border border-slate-200 bg-white p-5" aria-busy="true">
        <LoadingLabel />
        <Skeleton className="h-5 w-28" />
        <Skeleton className="h-6 w-3/4" />
        <Skeleton className="h-4 w-1/2" />
      </div>
    )
  }

  return (
    <AssetActionPanel
      asset={result.asset}
      actions={result.actions}
      matchedBy={result.matchedBy}
      initialAction={params.get('aksi')}
      onClose={() => navigate('/scan', { replace: true })}
      onChanged={load}
    />
  )
}
