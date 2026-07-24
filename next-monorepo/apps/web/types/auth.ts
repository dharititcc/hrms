export type User = {
  id: number
  name: string
  email: string
  email_verified_at: string | null
  created_at?: string
  updated_at?: string
}

export type AuthResponse = {
  success: boolean
  token: string
  user: User
}

export type ApiErrorPayload = {
  message?: string
  errors?: Record<string, string[]>
}
