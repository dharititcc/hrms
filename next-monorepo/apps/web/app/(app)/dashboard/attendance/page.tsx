import type { Metadata } from "next"
import { AttendanceModule } from "@/features/attendance/attendance-module"
export const metadata: Metadata = { title: "Attendance" }
export default function AttendancePage(){return <AttendanceModule/>}
