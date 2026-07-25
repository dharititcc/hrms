"use client"
import {useMutation,useQuery,useQueryClient} from '@tanstack/react-query';import {phaseThreeService} from '@/services/phase-three-service'
export function usePayroll(){return useQuery({queryKey:['payroll'],queryFn:phaseThreeService.payroll})}
export function usePayrollMutations(){const q=useQueryClient();return{create:useMutation({mutationFn:phaseThreeService.createPayroll,onSuccess:()=>q.invalidateQueries({queryKey:['payroll']})})}}
export function useExpenses(){return useQuery({queryKey:['expenses'],queryFn:phaseThreeService.expenses})}
export function useExpenseMutations(){const q=useQueryClient();const refresh=()=>q.invalidateQueries({queryKey:['expenses']});return{create:useMutation({mutationFn:phaseThreeService.createExpense,onSuccess:refresh}),status:useMutation({mutationFn:({id,status}:{id:number;status:'approved'|'rejected'})=>phaseThreeService.updateExpenseStatus(id,status),onSuccess:refresh})}}
