import { apiClient } from "@/lib/api-client"
import type { AuthResponse, User } from "@/types/auth"

export type LoginInput = { email: string; password: string; remember: boolean }
export type RegisterInput = { name: string; email: string; password: string; password_confirmation: string }
export type ResetPasswordInput = { token: string; email: string; password: string; password_confirmation: string }

export const authService = {
  async login(input: LoginInput) {
    const { data } = await apiClient.post<AuthResponse>("/auth/login", input)
    return data
  },
  async register(input: RegisterInput) {
    const { data } = await apiClient.post<AuthResponse>("/auth/register", input)
    return data
  },
  async me() {
    const { data } = await apiClient.get<User>("/auth/me")
    return data
  },
  async updateProfile(input: { name: string; email: string }) {
    const { data } = await apiClient.patch<User>("/auth/profile", input)
    return data
  },
  async logout() {
    await apiClient.post("/auth/logout")
  },
  async sendVerificationNotification() {
    await apiClient.post("/auth/email/verification-notification")
  },
  async forgotPassword(email: string) {
    await apiClient.post("/auth/forgot-password", { email })
  },
  async resetPassword(input: ResetPasswordInput) {
    await apiClient.post("/auth/reset-password", input)
  },
}
