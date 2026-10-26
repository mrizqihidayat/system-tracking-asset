const TOKEN_KEY = 'asset_token'
const USER_KEY = 'asset_user'

export interface AuthUser {
  id: number
  name: string
  username: string
}

// Sesi sudah dipastikan valid di server selama halaman ini terbuka (login baru / GET /user berhasil).
let verified = false
export const isVerified = () => verified
export const markVerified = () => {
  verified = true
}

export const getToken = () => localStorage.getItem(TOKEN_KEY)

export function getUser(): AuthUser | null {
  try {
    return JSON.parse(localStorage.getItem(USER_KEY) ?? 'null')
  } catch {
    return null
  }
}

export const setUser = (user: AuthUser) => localStorage.setItem(USER_KEY, JSON.stringify(user))

export function setSession(token: string, user: AuthUser) {
  localStorage.setItem(TOKEN_KEY, token)
  setUser(user)
  verified = true
}

export function clearSession() {
  localStorage.removeItem(TOKEN_KEY)
  localStorage.removeItem(USER_KEY)
  verified = false
}
