import { create } from 'zustand'

/**
 * Plan asignado al user logged-in.
 * monthlyLimit === 0 se interpreta como ilimitado (el backend lo valida igual).
 */
export interface UserPlan {
  id: number
  name: string
  monthlyLimit: number
  description: string | null
}

export interface UserQuota {
  used: number
  limit: number
  remaining: number | null
  unlimited: boolean
}

export interface CurrentUser {
  id: number
  email: string
  name: string | null
  plan: UserPlan | null
}

interface UserAuthState {
  isAuthenticated: boolean
  isLoading: boolean
  user: CurrentUser | null
  quota: UserQuota | null
  csrfToken: string | null
  setSession: (payload: { user: CurrentUser | null; quota: UserQuota | null; csrfToken: string | null }) => void
  setLoading: (v: boolean) => void
  clear: () => void
}

export const useUserAuthStore = create<UserAuthState>((set) => ({
  isAuthenticated: false,
  isLoading: true,
  user: null,
  quota: null,
  csrfToken: null,
  setSession: ({ user, quota, csrfToken }) => set({
    isAuthenticated: user !== null,
    user,
    quota,
    csrfToken,
  }),
  setLoading: (v) => set({ isLoading: v }),
  clear: () => set({ isAuthenticated: false, user: null, quota: null, csrfToken: null }),
}))
