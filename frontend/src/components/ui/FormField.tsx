import type { InputHTMLAttributes, ReactNode, SelectHTMLAttributes, TextareaHTMLAttributes } from 'react'

import { controlClass } from './control-class'

interface FieldProps {
  label: string
  error?: string
  required?: boolean
  children: ReactNode
}

export function FormField({ label, error, required, children }: FieldProps) {
  return (
    <label className="block space-y-1.5">
      <span className="block text-sm font-medium text-slate-700">
        {label}
        {required && <span className="text-bad-700"> *</span>}
      </span>
      {children}
      {error && <span className="block text-xs font-medium text-bad-700">{error}</span>}
    </label>
  )
}

export function Input({ error, className = '', ...rest }: InputHTMLAttributes<HTMLInputElement> & { error?: string }) {
  return <input className={`${controlClass(error)} ${className}`} {...rest} />
}

export function Select({ error, className = '', children, ...rest }: SelectHTMLAttributes<HTMLSelectElement> & { error?: string }) {
  return (
    <select className={`${controlClass(error)} ${className}`} {...rest}>
      {children}
    </select>
  )
}

export function Textarea({ error, className = '', ...rest }: TextareaHTMLAttributes<HTMLTextAreaElement> & { error?: string }) {
  return <textarea rows={3} className={`${controlClass(error)} ${className}`} {...rest} />
}
