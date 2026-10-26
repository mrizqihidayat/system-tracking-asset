import { Download } from 'lucide-react'
import { useState } from 'react'
import { errorMessage } from '../lib/api'
import { downloadReport, type ReportType } from '../lib/download'
import Button from './ui/Button'
import { useToast } from './ui/toast-context'

interface Props {
  type: ReportType
  /** Filter yang sedang aktif di layar (dibaca saat tombol diklik). */
  filters?: () => Record<string, unknown> | URLSearchParams
  className?: string
}

/** Tombol "Ekspor Excel": unduh laporan dengan filter yang sedang tampil. */
export default function ExportButton({ type, filters, className = '' }: Props) {
  const toast = useToast()
  const [busy, setBusy] = useState(false)

  const run = async () => {
    setBusy(true)
    try {
      await downloadReport(type, filters?.())
    } catch (e) {
      toast.error(errorMessage(e))
    } finally {
      setBusy(false)
    }
  }

  return (
    <Button variant="secondary" loading={busy} onClick={run} className={className}>
      <Download size={16} /> Ekspor Excel
    </Button>
  )
}
