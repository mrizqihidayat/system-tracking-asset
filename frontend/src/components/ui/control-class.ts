const control =
  'w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 disabled:bg-slate-100'

export const controlClass = (error?: string) => `${control} ${error ? 'border-red-500' : ''}`
