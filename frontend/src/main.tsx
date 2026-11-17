import { QueryClientProvider } from '@tanstack/react-query'
import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import { RouterProvider } from 'react-router-dom'
import { ToastProvider } from './components/ui/Toast'
import './index.css'
import { queryClient } from './lib/query-client'
import { applyStoredSettings, SettingsBoot } from './lib/settings'
import { router } from './router'

// Warna, judul, dan ikon dari Pengaturan (T40) diterapkan sebelum halaman tampil.
applyStoredSettings()

createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <QueryClientProvider client={queryClient}>
      <SettingsBoot />
      <ToastProvider>
        <RouterProvider router={router} />
      </ToastProvider>
    </QueryClientProvider>
  </StrictMode>,
)
