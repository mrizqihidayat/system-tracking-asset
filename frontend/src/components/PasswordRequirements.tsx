import { Check } from 'lucide-react'
import type { PasswordCheck } from '../lib/password'

/** Daftar syarat password baru; tiap syarat berubah hijau dengan centang saat terpenuhi. */
export default function PasswordRequirements({ checks }: { checks: PasswordCheck[] }) {
  return (
    <ul className="mt-2 space-y-1" aria-label="Syarat password baru">
      {checks.map((check) => (
        <li key={check.key} className={`flex items-center gap-2 text-xs ${check.met ? 'font-medium text-ok-700' : 'text-slate-500'}`}>
          {check.met ? (
            <Check size={14} strokeWidth={3} aria-hidden="true" />
          ) : (
            <span className="flex size-3.5 items-center justify-center" aria-hidden="true">
              <span className="size-1.5 rounded-full bg-slate-400" />
            </span>
          )}
          {check.label}
          <span className="sr-only">{check.met ? ' (terpenuhi)' : ' (belum terpenuhi)'}</span>
        </li>
      ))}
    </ul>
  )
}
