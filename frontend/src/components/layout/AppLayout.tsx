import {
  Boxes, ClipboardCheck, FileKey, HandHelping, LayoutDashboard, LogOut, Menu, PackageOpen, Send, Settings, X,
} from 'lucide-react'
import { useState } from 'react'
import { NavLink, Outlet, useNavigate } from 'react-router-dom'
import icon from '../../assets/icon.png'
import { api } from '../../lib/api'
import { clearSession, getUser } from '../../lib/auth'

// Isi modul mengikuti SPEC §3. Scan QR dibuka dari tombol di Dashboard (keputusan pengguna), tidak di menu.
const menu = [
  { to: '/', label: 'Dashboard', icon: LayoutDashboard },
  { to: '/daftar-aset', label: 'Daftar Aset', icon: Boxes },
  { to: '/peminjaman', label: 'Peminjaman', icon: HandHelping },
  { to: '/kelengkapan-ruangan', label: 'Kelengkapan Ruangan', icon: ClipboardCheck },
  { to: '/permintaan', label: 'Permintaan ke Unit Lain', icon: Send },
  { to: '/stok', label: 'Stok Habis Pakai', icon: PackageOpen },
]

const itemClass = ({ isActive }: { isActive: boolean }) =>
  `flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm transition-colors ${
    isActive ? 'bg-white font-semibold text-brand-800' : 'font-medium text-[#d3e2ee] hover:bg-white/10 hover:text-white'
  }`

export function Brand({ compact = false }: { compact?: boolean }) {
  return (
    <div className="flex items-center gap-3">
      <span className={`flex shrink-0 items-center justify-center rounded-lg bg-white ${compact ? 'h-8 w-8' : 'h-10 w-10'}`}>
        <img src={icon} alt="INALUM" className={compact ? 'w-5' : 'w-7'} />
      </span>
      <span className="min-w-0 leading-tight">
        <span className={`block font-bold ${compact ? 'text-base' : 'text-[17px]'}`}>Aset SHD</span>
        {!compact && <span className="block text-xs text-[#b5cadb]">Sistem Informasi Aset</span>}
      </span>
    </div>
  )
}

export default function AppLayout() {
  const [open, setOpen] = useState(false)
  const navigate = useNavigate()

  const user = getUser()

  const logout = async () => {
    try {
      await api.post('/logout')
    } catch {
      // token tetap dihapus lokal walau server tidak terjangkau
    }
    clearSession()
    navigate('/login', { replace: true })
  }

  return (
    <div className="min-h-screen lg:pl-60">
      {open && <div className="fixed inset-0 z-20 bg-slate-900/40 lg:hidden" onClick={() => setOpen(false)} />}
      <aside
        className={`fixed inset-y-0 left-0 z-30 flex w-64 flex-col bg-rail px-3 py-5 text-white transition-transform duration-200 lg:w-60 lg:translate-x-0 ${
          open ? 'translate-x-0' : '-translate-x-full'
        }`}
      >
        <div className="flex items-center justify-between pb-6">
          <Brand />
          <button
            className="flex h-9 w-9 items-center justify-center rounded-lg text-[#d3e2ee] hover:bg-white/10 lg:hidden"
            onClick={() => setOpen(false)}
            aria-label="Tutup menu"
          >
            <X size={20} />
          </button>
        </div>

        <nav className="flex-1 space-y-1 overflow-y-auto">
          {menu.map(({ to, label, icon: Icon }) => (
            <NavLink key={to} to={to} end={to === '/'} onClick={() => setOpen(false)} className={itemClass}>
              <Icon size={18} className="shrink-0" />
              <span>{label}</span>
            </NavLink>
          ))}
          <div className="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-[#9fb8cc]" aria-disabled="true">
            <FileKey size={18} className="shrink-0" />
            <span className="flex flex-1 items-center justify-between gap-2">
              Lisensi Digital
              <span className="rounded border border-white/25 px-1.5 py-0.5 text-[11px] text-[#c4d6e4]">Fase 2</span>
            </span>
          </div>

          <p className="px-3 pb-1 pt-5 text-xs font-semibold text-[#b5cadb]">Pengaturan</p>
          <NavLink to="/pengaturan/data-master" onClick={() => setOpen(false)} className={itemClass}>
            <Settings size={18} className="shrink-0" />
            <span>Data Master</span>
          </NavLink>
        </nav>

        <div className="mt-4 border-t border-white/15 pt-4">
          <div className="flex items-center gap-3 px-1">
            <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-white/15 text-sm font-semibold">
              {(user?.name ?? '?').charAt(0).toUpperCase()}
            </span>
            <div className="min-w-0 leading-tight">
              <p className="truncate text-sm font-semibold">{user?.name ?? '-'}</p>
              <p className="text-xs text-[#b5cadb]">Admin Aset SHD</p>
            </div>
          </div>
          <button
            onClick={logout}
            className="mt-3 flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-[#d3e2ee] hover:bg-white/10 hover:text-white"
          >
            <LogOut size={18} className="shrink-0" />
            Keluar
          </button>
        </div>
      </aside>

      <div className="flex min-w-0 flex-col">
        <header className="sticky top-0 z-10 flex h-14 items-center gap-3 bg-rail px-4 text-white lg:hidden">
          <button
            onClick={() => setOpen(true)}
            aria-label="Buka menu"
            className="-ml-1 flex h-10 w-10 items-center justify-center rounded-lg hover:bg-white/10"
          >
            <Menu size={22} />
          </button>
          <Brand compact />
        </header>
        <main className="mx-auto w-full max-w-[1440px] flex-1 px-4 py-5 sm:px-6 lg:px-8 lg:py-7">
          <Outlet />
        </main>
      </div>
    </div>
  )
}
