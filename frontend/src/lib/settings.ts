import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useEffect } from 'react'
import { api } from './api'
import { PASSWORD_MIN } from './password'
import { DEFAULT_THEME, THEME_VAR_NAMES, themeVars, type Theme } from './theme'

/** Pengaturan yang boleh dibaca tanpa login (`GET /settings/public`, T40). */
export interface PublicSettings {
  theme_primary: string
  theme_sidebar: string
  site_title: string
  site_description: string
  footer_text: string
  password_min_length: number
  logo_url: string | null
  favicon_url: string | null
}

/** Semua pengaturan (`GET /settings`, khusus akun pegawai SHD). */
export interface Settings extends PublicSettings {
  report_header: string
  label_text: string
  max_login_attempts: number
  lockout_minutes: number
  otp_ttl_minutes: number
}

export interface SettingsResponse {
  data: Settings
  defaults: Omit<Settings, 'logo_url' | 'favicon_url'>
  last_change: { by: string | null; at: string } | null
}

export const DEFAULT_PUBLIC_SETTINGS: PublicSettings = {
  theme_primary: DEFAULT_THEME.primary,
  theme_sidebar: DEFAULT_THEME.sidebar,
  site_title: 'Aset SHD',
  site_description: 'Masuk untuk melanjutkan ke Sistem Informasi Aset',
  footer_text: 'Departemen SHD · PT INALUM',
  password_min_length: PASSWORD_MIN,
  logo_url: null,
  favicon_url: null,
}

export const settingsKeys = {
  public: ['settings', 'public'] as const,
  full: ['settings', 'full'] as const,
}

const STORAGE_KEY = 'asset_settings'

/** Pengaturan terakhir yang diketahui browser ini, supaya warna dan judul langsung benar saat halaman dibuka. */
function storedSettings(): PublicSettings {
  try {
    const stored = JSON.parse(localStorage.getItem(STORAGE_KEY) ?? 'null')
    return stored && typeof stored === 'object' ? { ...DEFAULT_PUBLIC_SETTINGS, ...stored } : DEFAULT_PUBLIC_SETTINGS
  } catch {
    return DEFAULT_PUBLIC_SETTINGS
  }
}

const DEFAULT_FAVICON = '/icon.png'

/** Terapkan tema ke seluruh halaman; warna bawaan menghapus timpaan sehingga token index.css berlaku lagi. */
function applyTheme(theme: Theme) {
  const style = document.documentElement.style
  const vars = themeVars(theme)
  for (const name of THEME_VAR_NAMES) {
    if (vars[name]) style.setProperty(name, vars[name])
    else style.removeProperty(name)
  }
}

/** Terapkan warna, judul tab, dan favicon ke halaman. */
export function applyPublicSettings(settings: PublicSettings) {
  applyTheme({ primary: settings.theme_primary, sidebar: settings.theme_sidebar })
  document.title = settings.site_title || DEFAULT_PUBLIC_SETTINGS.site_title
  document.querySelector<HTMLLinkElement>('link[rel="icon"]')?.setAttribute('href', settings.favicon_url ?? DEFAULT_FAVICON)
}

/** Dipanggil sekali sebelum React dirender. */
export const applyStoredSettings = () => applyPublicSettings(storedSettings())

export function usePublicSettings(): PublicSettings {
  const query = useQuery({
    queryKey: settingsKeys.public,
    queryFn: async () => (await api.get<{ data: PublicSettings }>('/settings/public')).data.data,
    staleTime: 5 * 60_000,
    initialData: storedSettings,
    initialDataUpdatedAt: 0,
    retry: 1,
  })
  return query.data
}

/** Memuat pengaturan publik dan menerapkannya; dipasang sekali di akar aplikasi. */
export function SettingsBoot() {
  const settings = usePublicSettings()

  useEffect(() => {
    applyPublicSettings(settings)
    try {
      localStorage.setItem(STORAGE_KEY, JSON.stringify(settings))
    } catch {
      // penyimpanan penuh/diblokir: pengaturan tetap diterapkan untuk sesi ini
    }
  }, [settings])

  return null
}

export const useSettings = () =>
  useQuery({
    queryKey: settingsKeys.full,
    queryFn: async () => (await api.get<SettingsResponse>('/settings')).data,
  })

/** Setelah simpan: perbarui cache halaman Pengaturan dan muat ulang pengaturan publik (tema, judul, logo). */
export function useSettingsSaved() {
  const queryClient = useQueryClient()
  return (response: SettingsResponse) => {
    queryClient.setQueryData(settingsKeys.full, response)
    queryClient.invalidateQueries({ queryKey: settingsKeys.public })
  }
}

/** Panjang minimal password dari Pengaturan > Keamanan. */
export const usePasswordMin = () => usePublicSettings().password_min_length
