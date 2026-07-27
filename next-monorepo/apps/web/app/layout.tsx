import "@workspace/ui/globals.css"
import type { Metadata } from "next"
import { ThemeProvider } from "@/components/theme-provider"
import { QueryProvider } from "@/providers/query-provider"
import { ToastProvider } from "@/providers/toast-provider"
import { AuthProvider } from "@/providers/auth-provider"

export const metadata: Metadata = {
  title: {
    default: "HRMS",
    template: "%s | HRMS",
  },
  description: "A focused workspace for modern teams.",
}

export default function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode
}>) {
  return (
    <html
      lang="en"
      suppressHydrationWarning
      className="antialiased font-sans"
    >
      <body>
        <ThemeProvider><QueryProvider><ToastProvider><AuthProvider>{children}</AuthProvider></ToastProvider></QueryProvider></ThemeProvider>
      </body>
    </html>
  )
}
