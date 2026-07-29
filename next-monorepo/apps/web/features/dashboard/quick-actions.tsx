"use client"

import { CalendarPlus, CheckSquare, ClipboardList, LogIn, LogOut } from "lucide-react"
import Link from "next/link"
import { Button, buttonVariants } from "@workspace/ui/components/button"
import { useAttendanceMutations } from "@/hooks/use-attendance"
import { usePermissions } from "@/hooks/use-permissions"
import { capturePosition, formatRecordedTime } from "@/services/attendance-service"
import { getApiErrorMessage } from "@/lib/api-error"
import { useToast } from "@/providers/toast-provider"
import type { DashboardStats } from "@/types/dashboard"

/**
 * The two or three things somebody actually opens the dashboard to do.
 *
 * Check-in is here rather than only on the attendance page because it is the
 * first action of the day and making people navigate for it is the difference
 * between it being recorded and not.
 */
export function QuickActions({ attendance, canCheckIn }: {
  attendance: DashboardStats["attendance"]
  canCheckIn: boolean
}) {
  const { can, employeeId } = usePermissions()
  const { checkIn, checkOut } = useAttendanceMutations()
  const { toast } = useToast()

  // The workspace owner has no employee record, so there is nothing to check in.
  const showAttendance = canCheckIn && employeeId !== null && attendance !== undefined
  const done = attendance?.checked_out === true
  // Either direction leaves the button busy, so they share one label.
  const pending = checkIn.isPending || checkOut.isPending

  const punch = async () => {
    const position = await capturePosition()

    try {
      if (attendance?.checked_in && attendance.my_attendance_id) {
        await checkOut.mutateAsync({ id: attendance.my_attendance_id, position: position ?? undefined })
        toast({ tone: "success", title: "Checked out" })
      } else {
        await checkIn.mutateAsync({
          employee_id: employeeId as number,
          ...(position ?? {}),
        })
        toast({ tone: "success", title: "Checked in" })
      }
    } catch (error) {
      toast({ tone: "error", title: "Unable to record attendance", description: getApiErrorMessage(error) })
    }
  }

  const actions = [
    can("tasks.create") && { href: "/tasks", label: "New task", icon: CheckSquare },
    can("meetings.create") && { href: "/meetings", label: "Schedule meeting", icon: CalendarPlus },
    can("leave.create") && { href: "/leave", label: "Request leave", icon: ClipboardList },
  ].filter(Boolean) as { href: string; label: string; icon: typeof CheckSquare }[]

  if (!showAttendance && actions.length === 0) return null

  return (
    <section className="flex flex-wrap items-center gap-2 rounded-2xl border bg-background p-3">
      {showAttendance && (
        done ? (
          <span className="rounded-lg bg-muted px-3 py-2 text-sm text-muted-foreground">
            Checked out for today
          </span>
        ) : (
          <Button isDisabled={pending} onPress={() => void punch()}>
            {attendance?.checked_in ? <LogOut /> : <LogIn />}
            {pending ? "Recording…" : attendance?.checked_in ? "Check out" : "Check in"}
          </Button>
        )
      )}

      {showAttendance && attendance?.checked_in && !done && attendance.my_check_in && (
        <span className="text-xs text-muted-foreground">Since {formatRecordedTime(attendance.my_check_in_at, attendance.my_check_in, attendance.my_timezone)}</span>
      )}

      <div className="ml-auto flex flex-wrap gap-2">
        {actions.map(({ href, label, icon: Icon }) => (
          // next/link rather than the Button's own link variant, so navigation
          // stays client-side.
          <Link key={href} href={href} className={buttonVariants({ variant: "outline", size: "sm" })}>
            <Icon />{label}
          </Link>
        ))}
      </div>
    </section>
  )
}
