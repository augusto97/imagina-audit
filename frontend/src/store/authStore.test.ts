import { describe, it, expect, beforeEach } from 'vitest'
import { useAuthStore } from './authStore'

describe('authStore', () => {
  beforeEach(() => {
    // Reset al estado inicial entre tests
    useAuthStore.setState({ isAuthenticated: false, isLoading: true, csrfToken: null })
  })

  it('initial state is unauthenticated + loading, no token', () => {
    const s = useAuthStore.getState()
    expect(s.isAuthenticated).toBe(false)
    expect(s.isLoading).toBe(true)
    expect(s.csrfToken).toBeNull()
  })

  it('setAuthenticated toggles the flag', () => {
    useAuthStore.getState().setAuthenticated(true)
    expect(useAuthStore.getState().isAuthenticated).toBe(true)

    useAuthStore.getState().setAuthenticated(false)
    expect(useAuthStore.getState().isAuthenticated).toBe(false)
  })

  it('setLoading toggles the flag', () => {
    useAuthStore.getState().setLoading(false)
    expect(useAuthStore.getState().isLoading).toBe(false)
  })

  it('setCsrfToken stores and clears the token', () => {
    useAuthStore.getState().setCsrfToken('abc123')
    expect(useAuthStore.getState().csrfToken).toBe('abc123')

    useAuthStore.getState().setCsrfToken(null)
    expect(useAuthStore.getState().csrfToken).toBeNull()
  })

  it('setters operate independently', () => {
    // setAuthenticated no debe tocar csrfToken
    useAuthStore.getState().setCsrfToken('token-xyz')
    useAuthStore.getState().setAuthenticated(true)
    expect(useAuthStore.getState().csrfToken).toBe('token-xyz')
  })
})
