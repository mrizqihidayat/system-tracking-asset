/** Panjang minimal password bawaan; nilai yang berlaku dibaca dari Pengaturan > Keamanan (`usePasswordMin`, T40). */
export const PASSWORD_MIN = 8

/**
 * Cek awal password baru di form sebelum dikirim. Backend tetap memeriksa ulang,
 * termasuk larangan sama dengan tanggal lahir (data itu tidak pernah dikirim ke frontend).
 * Mengembalikan pesan error per field, kosong jika lolos.
 */
export function passwordErrors(
  password: string,
  confirmation: string,
  loginId: string,
  idLabel: 'NIK' | 'username' = 'username',
  min: number = PASSWORD_MIN,
): { password?: string; password_confirmation?: string } {
  if (password.length < min) return { password: `Password minimal ${min} karakter.` }
  if (loginId.trim() !== '' && password.toLowerCase() === loginId.trim().toLowerCase()) {
    return { password: `Password tidak boleh sama dengan ${idLabel}.` }
  }
  if (password !== confirmation) return { password_confirmation: 'Konfirmasi password tidak sama.' }
  return {}
}

export interface PasswordCheck {
  key: 'min' | 'login' | 'current'
  label: string
  met: boolean
}

/**
 * Daftar syarat password baru untuk ditampilkan (hijau saat terpenuhi). Syarat "bukan NIK/username" dan "beda dari
 * password saat ini" baru dianggap terpenuhi setelah ada isian, supaya daftar tidak hijau di kolom kosong.
 * `current` diisi hanya di form yang punya kolom password saat ini.
 */
export function passwordChecks(
  password: string,
  options: { loginId: string; idLabel: 'NIK' | 'username'; min?: number; current?: string },
): PasswordCheck[] {
  const { loginId, idLabel, min = PASSWORD_MIN, current } = options
  const typed = password.length > 0
  const id = loginId.trim().toLowerCase()

  const checks: PasswordCheck[] = [
    { key: 'min', label: `Minimal ${min} karakter`, met: password.length >= min },
    { key: 'login', label: `Tidak sama dengan ${idLabel}`, met: typed && (id === '' || password.toLowerCase() !== id) },
  ]
  if (current !== undefined) {
    checks.push({ key: 'current', label: 'Tidak sama dengan password saat ini', met: typed && password !== current })
  }
  return checks
}

/** Keadaan kolom konfirmasi: `idle` selama kosong, lalu `match` atau `mismatch`. */
export function confirmationState(password: string, confirmation: string): 'idle' | 'match' | 'mismatch' {
  if (confirmation === '') return 'idle'
  return confirmation === password ? 'match' : 'mismatch'
}
