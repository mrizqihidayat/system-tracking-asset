import { queryOptions, useQuery } from '@tanstack/react-query'
import type { Loan } from '../../types/loan'
import type { ConsumableItem, SupplyRequest } from '../../types/supply'
import { api } from '../api'
import { queryKeys } from './keys'

export interface DashboardCards {
  total_assets: { total: number; owned_by_shd: number; from_other_units: number; not_feasible: number }
  active_loans: { total: number; due_today: number; overdue: number }
  needs_repair: { assets_in_repair: number; loans_pending_confirmation: number }
  incomplete_rooms: { incomplete: number; total_rooms: number; total_buildings: number }
  needs_service: { total: number; overdue: number; upcoming: number }
  low_stock: { total: number; menipis: number; habis: number }
}

export interface DashboardReadiness {
  id: number
  name: string
  slug: string
  rooms_count: number
  incomplete_rooms_count: number
  rooms_without_standards: number
  incomplete_rooms: { id: number; name: string; slug: string; shortage_items: number; unchecked_items: number }[]
}

export interface DashboardPanels {
  loans_due: Loan[]
  recent_requests: SupplyRequest[]
  assets_need_service: {
    id: number
    code: string
    name: string
    next_service_date: string
    days_until_service: number
    is_overdue: boolean
    room: { name: string; building: string } | null
  }[]
  low_stock: ConsumableItem[]
}

const get = <T>(queryKey: readonly unknown[], url: string) =>
  queryOptions({ queryKey, queryFn: async () => (await api.get<{ data: T }>(url)).data.data })

/** Tiga kelompok widget dimuat paralel; tiap kelompok tampil begitu datanya tiba. */
export const dashboardQueries = {
  cards: () => get<DashboardCards>(queryKeys.dashboard.cards(), '/dashboard/cards'),
  roomReadiness: () => get<DashboardReadiness[]>(queryKeys.dashboard.roomReadiness(), '/dashboard/room-readiness'),
  panels: () => get<DashboardPanels>(queryKeys.dashboard.panels(), '/dashboard/panels'),
}

export const useDashboardCards = () => useQuery(dashboardQueries.cards())
export const useDashboardReadiness = () => useQuery(dashboardQueries.roomReadiness())
export const useDashboardPanels = () => useQuery(dashboardQueries.panels())
