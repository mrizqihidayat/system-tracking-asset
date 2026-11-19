import { keepPreviousData, useQuery } from '@tanstack/react-query'
import { useEffect, useState } from 'react'
import type { Paginated } from '../../types/api'
import { api } from '../api'
import { queryKeys, type ListParams } from './keys'

export type AccountStatus = 'aktif' | 'belum' | 'nonaktif'

/**
 * Satu baris Pengaturan > Akun (T41): akun di database, atau pegawai SHD yang belum pernah masuk (`id` null).
 * Tidak ada password di sini; `password_default` hanya penanda (null untuk akun eksternal).
 */
export interface Account {
  /** Penanda baris stabil: `akun-{id}` atau `pegawai-{id}` (belum punya akun). */
  key: string
  id: number | null
  name: string
  /** Hanya akun eksternal; NIK pegawai tidak dikirim API (T45, temuan UAT K4). */
  username: string | null
  email: string | null
  source: 'api' | 'manual'
  position: string | null
  /** Pegawai SHD: departemen dari data SIMHC. Akun eksternal: unit asal. */
  department: string | null
  /** Keterangan akun eksternal (mis. "Akun untuk Departemen SGA"). */
  note: string | null
  status: AccountStatus
  /** Akun dinonaktifkan staf (soft delete). */
  is_deactivated: boolean
  /** Pegawai sudah tidak ada/aktif/SHD di data pegawai: tetap tidak bisa masuk walau akun diaktifkan. */
  employee_inactive: boolean
  password_default: boolean | null
  last_login_at: string | null
}

/** Aktivitas pada akun: `event` = created, email_updated, password_updated, reset, deactivated, restored. */
export interface AccountEvent {
  id: number
  event: string
  actor_name: string | null
  created_at: string
}

/** Saran pegawai: hanya nama, departemen, jabatan (SPEC §15); NIK tidak dikirim. */
export interface Employee {
  name: string
  department: string
  position: string
}

/** Hasil `employees:sync` terakhir (hanya baca); null = belum pernah sinkron. */
export interface EmployeeSyncStatus {
  status: 'berhasil' | 'gagal' | 'dibatalkan'
  started_at: string
  finished_at: string | null
  total: number
  created: number
  updated: number
  deactivated: number
  skipped: number
  message: string | null
}

export const useEmployeeSyncStatus = () =>
  useQuery({
    queryKey: queryKeys.employeeSync,
    queryFn: async () => (await api.get<{ data: EmployeeSyncStatus | null }>('/employees/sync-status')).data.data,
  })

export const useAccounts = (params: ListParams) =>
  useQuery({
    queryKey: queryKeys.accounts.list(params),
    queryFn: async () => (await api.get<Paginated<Account>>('/accounts', { params })).data,
    placeholderData: keepPreviousData,
  })

/** 10 aktivitas terakhir akun; dimuat saat dialog riwayat dibuka. */
export const useAccountEvents = (id: number) =>
  useQuery({
    queryKey: queryKeys.accounts.events(id),
    queryFn: async () => (await api.get<{ data: AccountEvent[] }>(`/accounts/${id}/events`)).data.data,
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
