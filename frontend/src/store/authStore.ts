import { create } from 'zustand'

interface AuthState {
  isAuthenticated: boolean
  isLoading: boolean
  csrfToken: string | null
  setAuthenticated: (value: boolean) => void
  setLoading: (value: boolean) => void
  setCsrfToken: (token: string | null) => void
}

export const useAuthStore = create<AuthState>((set) => ({
  isAuthenticated: false,
  isLoading: true,
  csrfToken: null,
  setAuthenticated: (value) => set({ isAuthenticated: value }),
  setLoading: (value) => set({ isLoading: value }),
  setCsrfToken: (token) => set({ csrfToken: token }),
}))
