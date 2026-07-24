import { OverviewModule } from "@/features/dashboard/overview-module"
import type { Metadata } from "next"

export const metadata: Metadata = { title: "Dashboard" }

export default function DashboardPage() { return <OverviewModule /> }
