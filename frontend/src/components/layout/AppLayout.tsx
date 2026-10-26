import {
  Boxes, ClipboardCheck, FileKey, HandHelping, LayoutDashboard, LogOut, Menu, PackageOpen, QrCode, Send, Settings, X,
} from 'lucide-react'
import { useState } from 'react'
import { NavLink, Outlet, useNavigate } from 'react-router-dom'
import { api } from '../../lib/api'
import { clearSession, getUser } from '../../lib/auth'

// Gaya mengikuti docs/mockup-aset-shd.pdf; isi modul mengikuti SPEC §3 (Scan QR & Stok termasuk Fase 1).
const menu = [
  { to: '/', label: 'Dashboard', icon: LayoutDashboard },
  { to: '/daftar-aset', label: 'Daftar Aset', icon: Boxes },
  { to: '/scan', label: 'Scan QR', icon: QrCode },
  { to: '/peminjaman', label: 'Peminjaman', icon: HandHelping },
  { to: '/kelengkapan-ruangan', label: 'Kelengkapan Ruangan', icon: ClipboardCheck },
  { to: '/permintaan', label: 'Permintaan ke Unit Lain', icon: Send },
  { to: '/stok', label: 'Stok Habis Pakai', icon: PackageOpen },
]

// Desktop: sidebar ciut (ikon saja) dan melebar saat kursor masuk; label disembunyikan saat ciut.
const fade = 'whitespace-nowrap transition-opacity duration-150 lg:opacity-0 lg:group-hover:opacity-100'

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
    <div className="min-h-screen lg:pl-16">
      {open && <div className="fixed inset-0 z-20 bg-black/40 lg:hidden" onClick={() => setOpen(false)} />}
      <aside
        className={`group fixed inset-y-0 left-0 z-30 flex w-64 flex-col overflow-hidden bg-[#1a1b1e] px-2 py-5 text-[#e8eae6] transition-[width,transform] duration-200 lg:w-16 lg:translate-x-0 lg:hover:w-64 lg:hover:shadow-2xl ${
          open ? 'translate-x-0' : '-translate-x-full'
        }`}
      >
        <div className="flex items-center gap-3 px-2">
          <span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-brand-600 text-sm font-bold text-white">A</span>
          <div className={`min-w-0 flex-1 ${fade}`}>
            <p className="font-semibold leading-tight text-white">Aset SHD</p>
            <p className="text-xs text-[#9da49d]">Sistem Informasi Aset</p>
          </div>
          <button className="lg:hidden" onClick={() => setOpen(false)} aria-label="Tutup menu">
            <X size={20} />
          </button>
        </div>

        <nav className="mt-6 flex-1 space-y-1 overflow-y-auto overflow-x-hidden">
          {menu.map(({ to, label, icon: Icon }) => (
            <NavLink
              key={to}
              to={to}
              end={to === '/'}
              title={label}
              onClick={() => setOpen(false)}
              className={({ isActive }) =>
                `flex items-center gap-3 rounded-lg px-3 py-3 text-[15px] ${
                  isActive ? 'bg-brand-600 font-semibold text-white' : 'hover:bg-white/5'
                }`
              }
            >
              <Icon size={20} className="shrink-0" />
              <span className={fade}>{label}</span>
            </NavLink>
          ))}
          <div
            className="flex cursor-not-allowed items-center gap-3 rounded-lg px-3 py-3 text-[15px] text-[#80867f]"
            title="Lisensi Digital (Fase 2)"
            aria-disabled="true"
          >
            <FileKey size={20} className="shrink-0" />
            <span className={`flex flex-1 items-center justify-between ${fade}`}>
              Lisensi Digital
              <span className="rounded border border-[#4a4e4a] px-1.5 py-0.5 text-[11px] text-[#9da49d]">Fase 2</span>
            </span>
          </div>

          <div className="mx-3 mt-4 mb-1 border-t border-white/10 pt-3">
            <p className={`text-[11px] uppercase tracking-wide text-[#80867f] ${fade}`}>Pengaturan</p>
          </div>
          <NavLink
            to="/pengaturan/data-master"
            title="Data Master"
            onClick={() => setOpen(false)}
            className={({ isActive }) =>
              `flex items-center gap-3 rounded-lg px-3 py-3 text-[15px] ${
                isActive ? 'bg-brand-600 font-semibold text-white' : 'hover:bg-white/5'
              }`
            }
          >
            <Settings size={20} className="shrink-0" />
            <span className={fade}>Data Master</span>
          </NavLink>
        </nav>

        <div className="border-t border-white/10 pt-4">
          <div className="flex items-center gap-3 px-2 text-sm leading-tight text-[#b9beb8]">
            <span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-white/10 text-xs font-semibold text-white">
              {(user?.name ?? '?').charAt(0).toUpperCase()}
            </span>
            <div className={`min-w-0 ${fade}`}>
              <p>Admin Aset SHD</p>
              <p className="truncate">{user?.name ?? '-'}</p>
            </div>
          </div>
          <button
            onClick={logout}
            title="Keluar"
            className="mt-2 flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-sm text-[#9da49d] hover:bg-white/5 hover:text-white"
          >
            <LogOut size={20} className="shrink-0" />
            <span className={fade}>Keluar</span>
          </button>
        </div>
      </aside>

      <div className="flex min-w-0 flex-1 flex-col">
        <header className="flex h-14 items-center border-b border-slate-200 bg-white px-4 lg:hidden">
          <button onClick={() => setOpen(true)} aria-label="Buka menu">
            <Menu size={22} />
          </button>
          <span className="ml-3 font-semibold">Aset SHD</span>
        </header>
        <main className="flex-1 p-4 lg:p-8">
          <Outlet />
        </main>
      </div>
    </div>
  )
}
