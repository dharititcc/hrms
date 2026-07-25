"use client"

import { create } from "zustand"
import { authService, type LoginInput, type RegisterInput } from "@/services/auth-service"
import type { User } from "@/types/auth"

type AuthState = {
  user: User | null
  token: string | null
  isLoading: boolean
  isInitialized: boolean
  isAuthenticated: boolean
  initialize: () => void
  login: (input: LoginInput) => Promise<void>
  register: (input: RegisterInput) => Promise<void>
  logout: () => Promise<void>
  updateUser: (user: User) => void
}

function clearTokens() {
  window.localStorage.removeItem("auth-token")
  window.sessionStorage.removeItem("auth-token")
}

function readUser(value: string | null): User | null {
  if (!value) return null
  try { return JSON.parse(value) as User } catch { return null }
}

export const useAuthStore = create<AuthState>((set) => ({
  user: null,
  token: null,
  isLoading: false,
  isInitialized: false,
  isAuthenticated: false,
  initialize: () => {
    const token = window.localStorage.getItem("auth-token") || window.sessionStorage.getItem("auth-token")
    const user = window.localStorage.getItem("auth-user") || window.sessionStorage.getItem("auth-user")
    set({ token, user: readUser(user), isAuthenticated: Boolean(token), isInitialized: true })
  },
  login: async (input) => {
    set({ isLoading: true })
    try {
      const response = await authService.login(input)
      clearTokens()
      const storage = input.remember ? window.localStorage : window.sessionStorage
      storage.setItem("auth-token", response.token)
      storage.setItem("auth-user", JSON.stringify(response.user))
      set({ token: response.token, user: response.user, isAuthenticated: true, isInitialized: true })
    } finally {
      set({ isLoading: false })
    }
  },
  register: async (input) => {
    set({ isLoading: true })
    try {
      const response = await authService.register(input)
      clearTokens()
      window.localStorage.setItem("auth-token", response.token)
      window.localStorage.setItem("auth-user", JSON.stringify(response.user))
      set({ token: response.token, user: response.user, isAuthenticated: true, isInitialized: true })
    } finally {
      set({ isLoading: false })
    }
  },
  logout: async () => {
    set({ isLoading: true })
    try { await authService.logout() } catch { /* token is cleared locally even if the API is unavailable */ }
    clearTokens()
    set({ user: null, token: null, isAuthenticated: false, isLoading: false })
  },
  updateUser: (user) => {
    const storage = window.localStorage.getItem("auth-token") ? window.localStorage : window.sessionStorage
    storage.setItem("auth-user", JSON.stringify(user))
    set({ user })
  },
}))
