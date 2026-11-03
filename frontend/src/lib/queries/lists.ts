import { infiniteQueryOptions, keepPreviousData, queryOptions, useInfiniteQuery, useQuery } from '@tanstack/react-query'
import type { Paginated } from '../../types/api'
import type { Asset, AssetEvent, AssetFile, AssetListItem } from '../../types/asset'
import type { Loan } from '../../types/loan'
import type { Checklist, ReadinessBuilding } from '../../types/room'
import type { ConsumableItem, SuggestionGroup, SupplyRequest } from '../../types/supply'
import { api } from '../api'
import { queryKeys, type ListParams } from './keys'

const paginated = <T>(queryKey: readonly unknown[], url: string, params: ListParams) =>
  queryOptions({
    queryKey,
    queryFn: async () => (await api.get<Paginated<T>>(url, { params })).data,
    // Data halaman/filter sebelumnya tetap tampil sampai yang baru tiba (tabel tidak kosong/berkedip).
    placeholderData: keepPreviousData,
  })

const data = <T>(queryKey: readonly unknown[], url: string) =>
  queryOptions({ queryKey, queryFn: async () => (await api.get<{ data: T }>(url)).data.data })

export const listQueries = {
  assets: (params: ListParams) => paginated<AssetListItem>(queryKeys.assets.list(params), '/assets', params),
  loans: (params: ListParams) => paginated<Loan>(queryKeys.loans.list(params), '/loans', params),
  consumables: (params: ListParams) => paginated<ConsumableItem>(queryKeys.consumables.list(params), '/consumables', params),
  supplyRequests: (params: ListParams) => paginated<SupplyRequest>(queryKeys.supplyRequests.list(params), '/supply-requests', params),
  suggestions: () => data<SuggestionGroup[]>(queryKeys.supplyRequests.suggestions(), '/supply-requests/suggestions'),
  readiness: () => data<ReadinessBuilding[]>(queryKeys.rooms.readiness(), '/room-readiness'),
  checklist: (roomId: number) =>
    queryOptions({
      queryKey: queryKeys.rooms.checklist(roomId),
      queryFn: async () => (await api.get<Checklist>(`/rooms/${roomId}/checklist`)).data,
    }),
}

export interface AssetDetail {
  data: Asset
  meta: { actions: string[] }
}

export const assetQueries = {
  detail: (id: number | string) =>
    queryOptions({
      queryKey: queryKeys.assets.detail(id),
      queryFn: async () => (await api.get<AssetDetail>(`/assets/${id}`)).data,
    }),
  /** Riwayat dipaginasi server (20 terbaru per halaman), dimuat bertahap dengan "Tampilkan lebih banyak". */
  events: (id: number | string) =>
    infiniteQueryOptions({
      queryKey: queryKeys.assets.events(id),
      queryFn: async ({ pageParam }) =>
        (await api.get<Paginated<AssetEvent>>(`/assets/${id}/events`, { params: { page: pageParam } })).data,
      initialPageParam: 1,
      getNextPageParam: (last) => (last.meta.current_page < last.meta.last_page ? last.meta.current_page + 1 : undefined),
    }),
  files: (id: number | string) => data<AssetFile[]>(queryKeys.assets.files(id), `/assets/${id}/files`),
}

export const useAssets = (params: ListParams) => useQuery(listQueries.assets(params))
export const useLoans = (params: ListParams) => useQuery(listQueries.loans(params))
export const useConsumables = (params: ListParams) => useQuery(listQueries.consumables(params))
export const useSupplyRequests = (params: ListParams) => useQuery(listQueries.supplyRequests(params))
export const useSuggestions = () => useQuery(listQueries.suggestions())
export const useReadiness = () => useQuery(listQueries.readiness())
export const useChecklist = (roomId: number | undefined) =>
  useQuery({ ...listQueries.checklist(roomId ?? 0), enabled: !!roomId })
export const useAssetDetail = (id: number | string | undefined) =>
  useQuery({ ...assetQueries.detail(id ?? ''), enabled: !!id })
export const useAssetFiles = (id: number | string) => useQuery(assetQueries.files(id))
export const useAssetEvents = (id: number | string) => useInfiniteQuery(assetQueries.events(id))
