"use client"

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query"
import { attendanceService } from "@/services/attendance-service"
import type {
  AttendanceCorrectionInput, AttendanceLocationInput, CapturedPosition, CheckInInput, WorkShiftInput,
} from "@/types/attendance"

export function useAttendance(filters: { month?: string; employee_id?: number }) {
  return useQuery({
    queryKey: ["attendance", filters],
    queryFn: () => attendanceService.list(filters),
    placeholderData: (previous) => previous,
  })
}

export function useTodayAttendance() {
  return useQuery({ queryKey: ["attendance-today"], queryFn: () => attendanceService.today() })
}

export function useAttendanceLocations(enabled = true) {
  return useQuery({
    queryKey: ["attendance-locations"],
    queryFn: () => attendanceService.locations(),
    // Callers without attendance.view would only get a 403.
    enabled,
  })
}

export function useAttendanceLocationMutations() {
  const queryClient = useQueryClient()
  const refresh = () => queryClient.invalidateQueries({ queryKey: ["attendance-locations"] })

  const create = useMutation({ mutationFn: (input: AttendanceLocationInput) => attendanceService.createLocation(input), onSuccess: refresh })
  const update = useMutation({
    mutationFn: ({ id, input }: { id: number; input: AttendanceLocationInput }) => attendanceService.updateLocation(id, input),
    onSuccess: refresh,
  })
  const remove = useMutation({ mutationFn: (id: number) => attendanceService.removeLocation(id), onSuccess: refresh })

  return { create, update, remove }
}

export function useWorkShifts() {
  return useQuery({ queryKey: ["work-shifts"], queryFn: () => attendanceService.shifts() })
}

export function useWorkShiftMutations() {
  const queryClient = useQueryClient()
  // Hours decide what counts as late, so changing them changes how the
  // attendance list reads.
  const refresh = () => {
    queryClient.invalidateQueries({ queryKey: ["work-shifts"] })
    queryClient.invalidateQueries({ queryKey: ["attendance"] })
  }

  const create = useMutation({ mutationFn: (input: WorkShiftInput) => attendanceService.createShift(input), onSuccess: refresh })
  const update = useMutation({
    mutationFn: ({ id, input }: { id: number; input: WorkShiftInput }) => attendanceService.updateShift(id, input),
    onSuccess: refresh,
  })
  const remove = useMutation({ mutationFn: (id: number) => attendanceService.removeShift(id), onSuccess: refresh })

  return { create, update, remove }
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
  const correct = useMutation({
    mutationFn: ({ id, input }: { id: number; input: AttendanceCorrectionInput }) => attendanceService.correct(id, input),
    onSuccess: refresh,
  })

  return { checkIn, checkOut, approve, correct }
}
