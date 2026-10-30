import { QueryClient } from '@tanstack/react-query'
import axios from 'axios'

/** Data dianggap segar 60 detik (master: 10 menit, lihat queries/master.ts). */
export const DEFAULT_STALE_TIME = 60_000
export const MASTER_STALE_TIME = 10 * 60_000

export const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      staleTime: DEFAULT_STALE_TIME,
      refetchOnWindowFocus: false,
      // Error 4xx (validasi, 404, 401) tidak diulang; gangguan jaringan/5xx diulang sekali.
      retry: (count, error) => {
        const status = axios.isAxiosError(error) ? error.response?.status : undefined
        return count < 1 && (status === undefined || status >= 500)
      },
    },
  },
})
