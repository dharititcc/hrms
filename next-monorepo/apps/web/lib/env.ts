const apiUrl = process.env.NEXT_PUBLIC_API_URL

export const env = {
  apiUrl: apiUrl?.replace(/\/$/, "") || "http://localhost:8000/api",
} as const
