import { AuthLayout } from "@/features/auth/auth-layout"
import { PublicRoute } from "@/features/auth/route-guards"

export default function Layout({ children }: { children: React.ReactNode }) { return <PublicRoute><AuthLayout>{children}</AuthLayout></PublicRoute> }
