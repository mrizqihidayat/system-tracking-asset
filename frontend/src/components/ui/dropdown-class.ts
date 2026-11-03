/** Tampilan bersama daftar pilihan (Select, Combobox, saran aset). */
export const dropdownPanelClass =
  'overflow-auto overscroll-contain rounded-xl border border-slate-200 bg-white p-1.5 text-sm shadow-[0_8px_24px_rgb(20_33_46/0.12)]'

/** Disorot (hover/keyboard) abu-abu; terpilih biru sangat muda + teks biru. */
export const dropdownOptionClass = (highlighted: boolean, selected = false) =>
  `min-h-11 w-full cursor-pointer rounded-lg px-3 py-2 text-left transition-colors sm:min-h-9 ${
    selected ? `font-medium text-brand-700 ${highlighted ? 'bg-brand-100' : 'bg-brand-50'}` : highlighted ? 'bg-slate-100' : ''
  }`

/** Kolom saat daftarnya terbuka. */
export const dropdownOpenClass = 'border-brand-600 ring-2 ring-brand-100'
