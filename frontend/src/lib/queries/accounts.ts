import { keepPreviousData, useQuery } from '@tanstack/react-query'
import { useEffect, useState } from 'react'
import type { AuthUser } from '../auth'
import { api } from '../api'
import { queryKeys } from './keys'

/** Akun di Data Master > Akun (T33). */
export interface Account extends AuthUser {
  is_active: boolean
  deactivated_at: string | null
  created_at: string | null
}

/** Saran pegawai: hanya nama, departemen, jabatan (SPEC §15); NIK tidak dikirim. */
export interface Employee {
  name: string
  department: string
  position: string
}

export const useAccounts = () =>
  useQuery({
    queryKey: queryKeys.accounts.all,
    queryFn: async () => (await api.get<{ data: Account[] }>('/accounts')).data.data,
  })

/** Teks yang baru dipakai setelah berhenti mengetik `delay` ms. */
function useDebounced(value: string, delay: number) {
  const [debounced, setDebounced] = useState(value)
  useEffect(() => {
    const timer = setTimeout(() => setDebounced(value), delay)
    return () => clearTimeout(timer)
  }, [value, delay])
  return debounced
}

const EMPTY: Employee[] = []

/**
 * Saran pegawai (≥ 2 huruf, jeda 250 ms). Gagal/API pegawai mati → daftar kosong,
 * kolom tetap bisa diketik manual.
 */
export function useEmployeeSearch(search: string, enabled = true) {
  const term = useDebounced(search.trim(), 250)
  const query = useQuery({
    queryKey: queryKeys.employees(term),
    queryFn: async ({ signal }) => (await api.get<{ data: Employee[] }>('/employees', { params: { search: term }, signal })).data.data,
    enabled: enabled && term.length >= 2,
    staleTime: 5 * 60_000,
    retry: false,
    placeholderData: keepPreviousData,
  })
  return term.length >= 2 ? (query.data ?? EMPTY) : EMPTY
}
