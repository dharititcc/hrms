import axios from "axios"
import { env } from "@/lib/env"

/**
 * Client for endpoints reached without an account, currently the guest meeting
 * invitation.
 *
 * Deliberately separate from apiClient: that one attaches a bearer token and
 * redirects to /login on 401, which would throw an invited guest into a sign-in
 * screen for an account they do not have.
 */
export const publicApiClient = axios.create({
  baseURL: env.apiUrl,
  headers: { Accept: "application/json", "Content-Type": "application/json" },
})
