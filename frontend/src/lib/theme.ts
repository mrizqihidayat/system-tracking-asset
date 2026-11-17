/**
 * Tema warna web (T40): warna utama dan warna sidebar dari Pengaturan diterapkan lewat CSS variables
 * (`--color-brand-*`, `--color-rail`) tanpa rebuild. File ini murni (tanpa DOM); penerapannya di lib/settings.ts.
 */

export interface Theme {
  primary: string
  sidebar: string
}

/** Sama dengan token bawaan di index.css dan `SettingService::DEFAULTS`. */
export const DEFAULT_THEME: Theme = { primary: '#017cc2', sidebar: '#0e4f7e' }

export const THEME_PRESETS: { name: string; theme: Theme }[] = [
  { name: 'Biru INALUM', theme: DEFAULT_THEME },
  { name: 'Hijau', theme: { primary: '#0b7a53', sidebar: '#0d4a36' } },
  { name: 'Merah marun', theme: { primary: '#a3263a', sidebar: '#5f1522' } },
  { name: 'Ungu', theme: { primary: '#6b3fb5', sidebar: '#3c2470' } },
  { name: 'Abu gelap', theme: { primary: '#3d5166', sidebar: '#1f2b37' } },
]

export const isHexColor = (value: string) => /^#[0-9a-f]{6}$/i.test(value)

type Rgb = [number, number, number]

const toRgb = (hex: string): Rgb => [1, 3, 5].map((i) => parseInt(hex.slice(i, i + 2), 16)) as Rgb

const toHex = (rgb: Rgb) => '#' + rgb.map((c) => Math.round(c).toString(16).padStart(2, '0')).join('')

/** Campur `hex` dengan `target` sebanyak `amount` (0–1). */
export function mix(hex: string, target: string, amount: number): string {
  const a = toRgb(hex)
  const b = toRgb(target)
  return toHex(a.map((c, i) => c + (b[i] - c) * amount) as Rgb)
}

function luminance(hex: string): number {
  const [r, g, b] = toRgb(hex).map((c) => {
    const s = c / 255
    return s <= 0.03928 ? s / 12.92 : ((s + 0.055) / 1.055) ** 2.4
  })
  return 0.2126 * r + 0.7152 * g + 0.0722 * b
}

/** Rasio kontras WCAG antara dua warna (1–21). */
export function contrastRatio(a: string, b: string): number {
  const [hi, lo] = [luminance(a), luminance(b)].sort((x, y) => y - x)
  return (hi + 0.05) / (lo + 0.05)
}

/** Skala warna aksen yang diturunkan dari satu warna utama (600 = warna utama). */
export function brandScale(primary: string): Record<string, string> {
  return {
    '--color-brand-50': mix(primary, '#ffffff', 0.92),
    '--color-brand-100': mix(primary, '#ffffff', 0.83),
    '--color-brand-200': mix(primary, '#ffffff', 0.66),
    '--color-brand-500': mix(primary, '#ffffff', 0.18),
    '--color-brand-600': primary,
    '--color-brand-700': mix(primary, '#000000', 0.15),
    '--color-brand-800': mix(primary, '#000000', 0.35),
    '--color-brand-900': mix(primary, '#000000', 0.48),
  }
}

/**
 * CSS variables untuk sebuah tema. Nilai bawaan tidak menimpa apa pun (kosong), sehingga tampilan bawaan
 * persis sama dengan token di index.css.
 */
export function themeVars(theme: Theme): Record<string, string> {
  const primary = theme.primary.toLowerCase()
  const sidebar = theme.sidebar.toLowerCase()
  return {
    ...(isHexColor(primary) && primary !== DEFAULT_THEME.primary ? brandScale(primary) : {}),
    ...(isHexColor(sidebar) && sidebar !== DEFAULT_THEME.sidebar ? { '--color-rail': sidebar } : {}),
  }
}

/** Batas kontras teks normal WCAG AA. */
const MIN_CONTRAST = 4.5

/** Peringatan bahasa awam bila teks akan sulit dibaca dengan tema ini; kosong = aman. */
export function contrastWarnings(theme: Theme): string[] {
  if (!isHexColor(theme.primary) || !isHexColor(theme.sidebar)) return []
  const warnings: string[] = []
  if (contrastRatio('#ffffff', theme.primary) < MIN_CONTRAST) {
    warnings.push('Warna utama terlalu terang: tulisan putih di tombol akan sulit dibaca. Pilih warna yang lebih gelap.')
  }
  if (contrastRatio(brandScale(theme.primary)['--color-brand-700'], '#ffffff') < MIN_CONTRAST) {
    warnings.push('Warna utama terlalu terang: tulisan tautan di latar putih akan sulit dibaca.')
  }
  if (contrastRatio('#ffffff', theme.sidebar) < MIN_CONTRAST) {
    warnings.push('Warna sidebar terlalu terang: tulisan menu berwarna putih akan sulit dibaca. Pilih warna yang lebih gelap.')
  }
  return warnings
}

/** Semua CSS variable yang bisa ditimpa tema. */
export const THEME_VAR_NAMES = [...Object.keys(brandScale('#000000')), '--color-rail']
