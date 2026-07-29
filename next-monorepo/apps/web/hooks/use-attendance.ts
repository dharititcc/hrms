"use client"

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query"
import { attendanceService } from "@/services/attendance-service"
import type { CapturedPosition, CheckInInput } from "@/types/attendance"

export function useAttendance(filters: { month?: string; staff_id?: number }) {
  return useQuery({
    queryKey: ["attendance", filters],
    queryFn: () => attendanceService.list(filters),
    placeholderData: (previous) => previous,
  })
}

export function useTodayAttendance() {
  return useQuery({ queryKey: ["attendance-today"], queryFn: () => attendanceService.today() })
}

export function useAttendanceMutations() {
  const queryClient = useQueryClient()

  const refresh = () => {
    queryClient.invalidateQueries({ queryKey: ["attendance"] })
    queryClient.invalidateQueries({ queryKey: ["attendance-today"] })
    // The dashboard's attendance card reads the same data.
    queryClient.invalidateQueries({ queryKey: ["dashboard-stats"] })
  }

  const checkIn = useMutation({ mutationFn: (input: CheckInInput) => attendanceService.checkIn(input), onSuccess: refresh })
  const checkOut = useMutation({
    mutationFn: ({ id, position }: { id: number; position?: CapturedPosition }) => attendanceService.checkOut(id, position),
    onSuccess: refresh,
  })
  const approve = useMutation({ mutationFn: (id: number) => attendanceService.approve(id), onSuccess: refresh })

  return { checkIn, checkOut, approve }
}
