// Lebar bervariasi agar skeleton terbaca sebagai baris data, bukan blok seragam.
const WIDTHS = ['w-3/4', 'w-1/2', 'w-2/3', 'w-5/12', 'w-7/12']

export const skeletonWidth = (i: number) => WIDTHS[i % WIDTHS.length]
