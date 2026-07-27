"use client"
import {useMutation,useQuery,useQueryClient} from "@tanstack/react-query";import {phaseFourFiveService} from "@/services/phase-four-five-service"
export function usePhaseFourFive(path:string){return useQuery({queryKey:[path],queryFn:()=>phaseFourFiveService.get(path)})}
export function usePhaseFourFiveCreate(path:string){const q=useQueryClient();return useMutation({mutationFn:(input:Record<string,unknown>)=>phaseFourFiveService.create(path,input),onSuccess:()=>q.invalidateQueries({queryKey:[path]})})}
export function useSummary(){return useQuery({queryKey:['reports-summary'],queryFn:phaseFourFiveService.report})}
