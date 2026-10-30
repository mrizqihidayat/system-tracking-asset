export type ButtonVariant = 'primary' | 'secondary' | 'danger' | 'ghost'

const variants: Record<ButtonVariant, string> = {
  primary: 'bg-brand-600 text-white hover:bg-brand-700',
  secondary: 'border border-slate-200 bg-white text-slate-800 hover:border-slate-300 hover:bg-slate-50',
  danger: 'bg-bad-700 text-white hover:bg-[#912018]',
  ghost: 'text-brand-700 hover:bg-brand-50',
}

/** Kelas tombol; dipakai `<Button>` dan elemen lain (mis. `<Link>`) agar tampil sama. */
export const buttonClass = (variant: ButtonVariant = 'primary', className = '') =>
  `inline-flex min-h-10 items-center justify-center gap-2 whitespace-nowrap rounded-lg px-4 text-sm font-semibold transition-colors disabled:cursor-not-allowed disabled:opacity-50 ${variants[variant]} ${className}`
