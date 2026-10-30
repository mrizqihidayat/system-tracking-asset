import type { ButtonHTMLAttributes } from 'react'
import { buttonClass, type ButtonVariant } from './button-class'

interface Props extends ButtonHTMLAttributes<HTMLButtonElement> {
  variant?: ButtonVariant
  loading?: boolean
}

export default function Button({ variant = 'primary', loading, disabled, className = '', children, ...rest }: Props) {
  return (
    <button type="button" disabled={disabled || loading} className={buttonClass(variant, className)} {...rest}>
      {loading ? 'Memproses…' : children}
    </button>
  )
}
