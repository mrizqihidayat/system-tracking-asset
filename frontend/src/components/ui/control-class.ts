const control =
  'block min-h-10 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 placeholder:text-slate-500 transition-colors hover:border-slate-400 focus:border-brand-600 focus:outline-none focus:ring-2 focus:ring-brand-100 disabled:bg-slate-100 disabled:text-slate-500'

export const controlClass = (error?: string) => `${control} ${error ? 'border-bad-700 focus:border-bad-700 focus:ring-bad-50' : ''}`
