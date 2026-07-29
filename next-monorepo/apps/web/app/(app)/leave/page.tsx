import type { Metadata } from "next"
import { LeaveModule } from "@/features/leave/leave-module"
export const metadata: Metadata = { title: "Leave management" }
export default function LeavePage(){return <LeaveModule/>}
