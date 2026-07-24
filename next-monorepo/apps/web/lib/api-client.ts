import axios from "axios"
import { env } from "@/lib/env"

export const apiClient = axios.create({
  baseURL: env.apiUrl,
  headers: { Accept: "application/json", "Content-Type": "application/json" },
})

apiClient.interceptors.request.use((config) => {
  if (typeof window !== "undefined") {
    const token = window.localStorage.getItem("auth-token") || window.sessionStorage.getItem("auth-token")
    if (token) config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

apiClient.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401 && typeof window !== "undefined") {
      window.localStorage.removeItem("auth-token")
      window.sessionStorage.removeItem("auth-token")
      if (!window.location.pathname.startsWith("/login")) window.location.assign("/login")
    }
    return Promise.reject(error)
  },
)
