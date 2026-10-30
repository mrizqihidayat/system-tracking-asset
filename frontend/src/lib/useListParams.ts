import { useCallback, useEffect, useMemo, useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import type { ListParams } from './queries/keys'

/**
 * Konfigurasi satu tabel server-side. Dipakai halaman (lewat useListParams) dan prefetch sidebar
 * (lewat buildListParams) agar keduanya menghasilkan query key yang sama.
 */
export interface ListConfig {
  /** Awalan parameter URL bila satu halaman punya beberapa tabel (mis. `aktif_`). */
  prefix?: string
  /** Nama filter (selain search/sort/page) yang dibaca dari URL dan dikirim ke API. */
  filters?: readonly string[]
  perPage?: number
  /** Parameter tetap yang selalu dikirim (tidak tampil di URL). */
  fixed?: ListParams
  /** Ubah nilai URL menjadi parameter API (mis. status tampilan → flag API). */
  toApi?: (params: ListParams) => ListParams
}

const BASE_KEYS = ['search', 'sort', 'page'] as const

export const SEARCH_DEBOUNCE_MS = 300

/** Parameter API dari URL (fungsi murni). */
export function buildListParams(search: URLSearchParams, config: ListConfig): ListParams {
  const { prefix = '', filters = [], perPage, fixed, toApi } = config
  const params: ListParams = { ...fixed }
  for (const key of [...filters, ...BASE_KEYS]) {
    const value = search.get(prefix + key)
    if (value) params[key] = value
  }
  if (params.page === '1') delete params.page
  if (perPage) params.per_page = perPage
  return toApi ? toApi(params) : params
}

/**
 * State tabel (filter, pencarian, urutan, halaman) disimpan di URL query params sehingga tetap ada saat
 * kembali ke halaman. Pencarian diketik di state lokal lalu ditulis ke URL setelah jeda 300 ms.
 */
export function useListParams(config: ListConfig) {
  const [search, setSearch] = useSearchParams()
  const prefix = config.prefix ?? ''
  const name = useCallback((key: string) => prefix + key, [prefix])

  const urlSearch = search.get(name('search')) ?? ''
  const [searchText, setSearchText] = useState(urlSearch)
  const [syncedSearch, setSyncedSearch] = useState(urlSearch)
  // URL berubah dari luar (mis. tautan dashboard ?search=, tombol kembali) → isi ulang kotak cari.
  // Hasil debounce sendiri tidak menimpa ketikan (spasi di akhir tetap ada).
  if (urlSearch !== syncedSearch) {
    setSyncedSearch(urlSearch)
    if (searchText.trim() !== urlSearch) setSearchText(urlSearch)
  }

  const update = useCallback(
    (changes: Record<string, string | null>, push = false) =>
      setSearch(
        (prev) => {
          const next = new URLSearchParams(prev)
          for (const [key, value] of Object.entries(changes)) {
            if (value) next.set(name(key), value)
            else next.delete(name(key))
          }
          return next
        },
        { replace: !push },
      ),
    [name, setSearch],
  )

  useEffect(() => {
    if (searchText.trim() === urlSearch) return
    const t = setTimeout(() => update({ search: searchText.trim() || null, page: null }), SEARCH_DEBOUNCE_MS)
    return () => clearTimeout(t)
  }, [searchText, urlSearch, update])

  const params = useMemo(() => buildListParams(search, config), [search, config])

  const filterKeys = config.filters ?? []
  const hasFilter = [...filterKeys, 'search'].some((key) => search.get(name(key)))

  return {
    /** Parameter untuk API / query key. */
    params,
    get: (key: string) => search.get(name(key)) ?? '',
    /** Ubah filter (halaman kembali ke 1). `extra` untuk filter turunan, mis. ruangan saat gedung berganti. */
    setFilter: (key: string, value: string, extra: Record<string, string | null> = {}) =>
      update({ [key]: value || null, ...extra, page: null }),
    page: Number(search.get(name('page')) ?? 1),
    setPage: (page: number) => update({ page: page > 1 ? String(page) : null }, true),
    sort: search.get(name('sort')) ?? '',
    setSort: (sort: string) => update({ sort: sort || null, page: null }),
    searchText,
    setSearchText,
    hasFilter,
    reset: () => {
      setSearchText('')
      update(Object.fromEntries([...filterKeys, ...BASE_KEYS].map((key) => [key, null])))
    },
  }
}
