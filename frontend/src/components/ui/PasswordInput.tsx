import { Eye, EyeOff } from 'lucide-react'
import { useState, type InputHTMLAttributes } from 'react'
import { Input } from './FormField'

/** Kolom password dengan tombol tampilkan/sembunyikan. */
export default function PasswordInput({ error, ...rest }: Omit<InputHTMLAttributes<HTMLInputElement>, 'type'> & { error?: string }) {
  const [visible, setVisible] = useState(false)

  return (
    <div className="relative">
      <Input {...rest} type={visible ? 'text' : 'password'} error={error} className="pr-10 [&::-ms-reveal]:hidden" />
      <button
        type="button"
        onClick={() => setVisible((v) => !v)}
        aria-label={visible ? 'Sembunyikan password' : 'Tampilkan password'}
        aria-pressed={visible}
        className="absolute inset-y-0 right-0 flex w-10 items-center justify-center text-slate-500 hover:text-slate-700"
      >
        {visible ? <EyeOff size={18} /> : <Eye size={18} />}
      </button>
    </div>
  )
}
