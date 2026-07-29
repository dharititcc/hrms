"use client"

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query"
import { taskService } from "@/services/task-service"
import type { ProjectTaskInput } from "@/types/project"
import type { ManualTimeInput, TaskChecklistItem, TaskFilters } from "@/types/task"

export function useTaskMutations() {
  const queryClient = useQueryClient()
  // A standalone task shows up in the list and in the dashboard counts.
  const refresh = () => {
    queryClient.invalidateQueries({ queryKey: ["tasks"] })
    queryClient.invalidateQueries({ queryKey: ["dashboard-stats"] })
  }

  const create = useMutation({ mutationFn: (input: ProjectTaskInput) => taskService.create(input), onSuccess: refresh })

  return { create }
}

export function useTasks(filters: TaskFilters) {
  return useQuery({ queryKey: ["tasks", filters], queryFn: () => taskService.list(filters), placeholderData: (previous) => previous })
}

export function useTask(taskId: number) {
  return useQuery({ queryKey: ["task", taskId], queryFn: () => taskService.get(taskId), enabled: Number.isFinite(taskId) })
}

export function useTaskComments(taskId: number) {
  return useQuery({ queryKey: ["task-comments", taskId], queryFn: () => taskService.comments(taskId), enabled: Number.isFinite(taskId) })
}

export function useTaskCommentMutations(taskId: number) {
  const queryClient = useQueryClient()
  const refresh = () => queryClient.invalidateQueries({ queryKey: ["task-comments", taskId] })

  const add = useMutation({
    mutationFn: ({ body, parentId }: { body: string; parentId?: number | null }) => taskService.addComment(taskId, body, parentId),
    onSuccess: refresh,
  })
  const edit = useMutation({ mutationFn: ({ id, body }: { id: number; body: string }) => taskService.editComment(id, body), onSuccess: refresh })
  const remove = useMutation({ mutationFn: (id: number) => taskService.removeComment(id), onSuccess: refresh })

  return { add, edit, remove }
}

export function useTaskChecklist(taskId: number) {
  return useQuery({ queryKey: ["task-checklist", taskId], queryFn: () => taskService.checklist(taskId), enabled: Number.isFinite(taskId) })
}

export function useTaskChecklistMutations(taskId: number) {
  const queryClient = useQueryClient()
  const refresh = () => queryClient.invalidateQueries({ queryKey: ["task-checklist", taskId] })

  const add = useMutation({ mutationFn: (title: string) => taskService.addChecklistItem(taskId, title), onSuccess: refresh })
  const remove = useMutation({ mutationFn: (id: number) => taskService.removeChecklistItem(id), onSuccess: refresh })

  // Optimistic so ticking a box feels instant, rolled back if the request fails.
  const toggle = useMutation({
    mutationFn: ({ id, isCompleted }: { id: number; isCompleted: boolean }) =>
      taskService.updateChecklistItem(id, { is_completed: isCompleted }),
    onMutate: async ({ id, isCompleted }) => {
      const key = ["task-checklist", taskId]
      await queryClient.cancelQueries({ queryKey: key })
      const previous = queryClient.getQueryData<TaskChecklistItem[]>(key)
      queryClient.setQueryData<TaskChecklistItem[]>(key, (items) =>
        items?.map((item) => (item.id === id ? { ...item, is_completed: isCompleted } : item)),
      )
      return { previous }
    },
    onError: (_error, _variables, context) => {
      if (context?.previous) queryClient.setQueryData(["task-checklist", taskId], context.previous)
    },
    onSettled: refresh,
  })

  return { add, toggle, remove }
}

export function useTaskTimeEntries(taskId: number) {
  return useQuery({ queryKey: ["task-time", taskId], queryFn: () => taskService.timeEntries(taskId), enabled: Number.isFinite(taskId) })
}

/** The caller's running timer across all tasks, polled so it stays live. */
export function useRunningTimer() {
  return useQuery({ queryKey: ["running-timer"], queryFn: () => taskService.runningTimer(), refetchInterval: 30_000 })
}

export function useTaskTimerMutations(taskId: number) {
  const queryClient = useQueryClient()
  const refresh = () => {
    queryClient.invalidateQueries({ queryKey: ["task-time", taskId] })
    // Starting a timer stops any other running one, so the global widget and
    // the other task's entries can both be stale.
    queryClient.invalidateQueries({ queryKey: ["running-timer"] })
    queryClient.invalidateQueries({ queryKey: ["task-time"] })
  }

  const start = useMutation({ mutationFn: () => taskService.startTimer(taskId), onSuccess: refresh })
  const stop = useMutation({ mutationFn: () => taskService.stopTimer(taskId), onSuccess: refresh })
  const log = useMutation({ mutationFn: (input: ManualTimeInput) => taskService.logTime(taskId, input), onSuccess: refresh })
  const remove = useMutation({ mutationFn: (id: number) => taskService.removeTimeEntry(id), onSuccess: refresh })

  return { start, stop, log, remove }
}
