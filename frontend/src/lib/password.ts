/** Panjang minimal password baru (sama dengan backend, SPEC §15). */
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
): { password?: string; password_confirmation?: string } {
  if (password.length < PASSWORD_MIN) return { password: `Password minimal ${PASSWORD_MIN} karakter.` }
  if (loginId.trim() !== '' && password.toLowerCase() === loginId.trim().toLowerCase()) {
    return { password: `Password tidak boleh sama dengan ${idLabel}.` }
  }
  if (password !== confirmation) return { password_confirmation: 'Konfirmasi password tidak sama.' }
  return {}
}
