"use client"
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query"
import { phaseTwoService } from "@/services/phase-two-service"
export function useAttendance() { return useQuery({ queryKey: ["attendance"], queryFn: phaseTwoService.attendance }) }
export function useAttendanceMutations() { const qc=useQueryClient(); const refresh=()=>qc.invalidateQueries({queryKey:["attendance"]}); return { clockIn: useMutation({mutationFn:phaseTwoService.clockIn,onSuccess:refresh}), clockOut: useMutation({mutationFn:phaseTwoService.clockOut,onSuccess:refresh}) } }
export function useLeave() { return { types: useQuery({queryKey:["leave-types"],queryFn:phaseTwoService.leaveTypes}), requests: useQuery({queryKey:["leave-requests"],queryFn:phaseTwoService.leaveRequests}) } }
export function useLeaveMutations() { const qc=useQueryClient(); const refresh=()=>qc.invalidateQueries({queryKey:["leave-requests"]}); return { create:useMutation({mutationFn:phaseTwoService.createLeave,onSuccess:refresh}), updateStatus:useMutation({mutationFn:({id,status}:{id:number;status:"approved"|"rejected"|"cancelled"})=>phaseTwoService.updateLeaveStatus(id,status),onSuccess:refresh}) } }
