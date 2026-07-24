import axios from "axios"
import type { ApiErrorPayload } from "@/types/auth"

export function getApiErrorMessage(error: unknown, fallback = "Something went wrong. Please try again.") {
  if (!axios.isAxiosError<ApiErrorPayload>(error)) return fallback

  const payload = error.response?.data
  const firstFieldError = payload?.errors && Object.values(payload.errors)[0]?.[0]

  return firstFieldError || payload?.message || (error.response?.status === 422 ? "Please check the highlighted fields." : fallback)
}
