import { StaffModule } from "@/features/staff/staff-module"
import type { Metadata } from "next"

export const metadata: Metadata = { title: "Staff management" }

export default function StaffPage() { return <StaffModule /> }
