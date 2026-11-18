import { useRef, type ClipboardEvent, type KeyboardEvent } from 'react'
import { OTP_LENGTH, otpBackspace, otpInsert } from '../../lib/otp'

interface Props {
  value: string
  onChange: (value: string) => void
  invalid?: boolean
  disabled?: boolean
  autoFocus?: boolean
}

const box =
  'h-12 min-w-0 flex-1 rounded-lg border bg-white text-center font-mono text-lg text-slate-900 transition-colors focus:outline-none focus:ring-2 disabled:bg-slate-100 disabled:text-slate-500'

/**
 * Kode OTP dalam 6 kotak angka: kotak berikutnya terisi otomatis saat mengetik, Backspace mundur, dan kode bisa
 * ditempel atau diisi otomatis dari SMS/email sekaligus.
 */
export default function OtpInput({ value, onChange, invalid, disabled, autoFocus }: Props) {
  const refs = useRef<(HTMLInputElement | null)[]>([])

  const apply = (edit: { value: string; focus: number }) => {
    onChange(edit.value)
    refs.current[edit.focus]?.focus()
  }

  const onKeyDown = (index: number, e: KeyboardEvent<HTMLInputElement>) => {
    if (e.key === 'Backspace' || e.key === 'Delete') {
      e.preventDefault()
      apply(otpBackspace(value, index))
    } else if (e.key === 'ArrowLeft') {
      e.preventDefault()
      refs.current[Math.max(index - 1, 0)]?.focus()
    } else if (e.key === 'ArrowRight') {
      e.preventDefault()
      refs.current[Math.min(index + 1, OTP_LENGTH - 1)]?.focus()
    }
  }

  const onPaste = (index: number, e: ClipboardEvent<HTMLInputElement>) => {
    e.preventDefault()
    apply(otpInsert(value, index, e.clipboardData.getData('text')))
  }

  return (
    <div role="group" aria-label="Kode verifikasi 6 angka" className="flex gap-2">
      {Array.from({ length: OTP_LENGTH }, (_, index) => (
        <input
          key={index}
          ref={(el) => {
            refs.current[index] = el
          }}
          value={value[index] ?? ''}
          onChange={(e) => apply(otpInsert(value, index, e.target.value))}
          onKeyDown={(e) => onKeyDown(index, e)}
          onPaste={(e) => onPaste(index, e)}
          onFocus={(e) => e.target.select()}
          inputMode="numeric"
          autoComplete={index === 0 ? 'one-time-code' : 'off'}
          aria-label={`Angka ${index + 1} dari ${OTP_LENGTH}`}
          aria-invalid={invalid || undefined}
          autoFocus={autoFocus && index === 0}
          disabled={disabled}
          className={`${box} ${invalid ? 'border-bad-700 focus:border-bad-700 focus:ring-bad-50' : 'border-slate-300 hover:border-slate-400 focus:border-brand-600 focus:ring-brand-100'}`}
        />
      ))}
    </div>
  )
}
