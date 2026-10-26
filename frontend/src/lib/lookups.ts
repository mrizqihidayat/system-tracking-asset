import type { AssetType, Building, Category, Department, Room } from '../types/asset'
import { api } from './api'

const list = async <T>(url: string, params?: Record<string, unknown>) =>
  (await api.get<{ data: T[] }>(url, { params })).data.data

export const fetchCategories = () => list<Category>('/categories')
export const fetchAssetTypes = () => list<AssetType>('/asset-types')
export const fetchBuildings = () => list<Building>('/buildings')
export const fetchDepartments = () => list<Department>('/departments')
export const fetchRooms = (buildingId?: number | null) =>
  list<Room>('/rooms', buildingId ? { building_id: buildingId } : undefined)

export const createDepartment = async (name: string) =>
  (await api.post<{ data: Department }>('/departments', { name })).data.data
export const createBuilding = async (name: string) =>
  (await api.post<{ data: Building }>('/buildings', { name })).data.data
export const createRoom = async (buildingId: number, name: string) =>
  (await api.post<{ data: Room }>(`/buildings/${buildingId}/rooms`, { name })).data.data
