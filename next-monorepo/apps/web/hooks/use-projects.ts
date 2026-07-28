"use client"

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query"
import { projectService, type ProjectFilters } from "@/services/project-service"
import type { ProjectInput, ProjectTask, ProjectTaskInput, TaskStatus } from "@/types/project"

export function useProjects(filters: ProjectFilters) {
  return useQuery({ queryKey: ["projects", filters], queryFn: () => projectService.list(filters) })
}

export function useProject(id: number) {
  return useQuery({ queryKey: ["project", id], queryFn: () => projectService.get(id), enabled: Number.isFinite(id) })
}

export function useProjectMutations() {
  const queryClient = useQueryClient()
  const refresh = () => queryClient.invalidateQueries({ queryKey: ["projects"] })
  const create = useMutation({ mutationFn: (input: ProjectInput) => projectService.create(input), onSuccess: refresh })
  const update = useMutation({
    mutationFn: ({ id, input }: { id: number; input: ProjectInput }) => projectService.update(id, input),
    onSuccess: (project) => {
      queryClient.invalidateQueries({ queryKey: ["project", project.id] })
      refresh()
    },
  })
  const remove = useMutation({ mutationFn: (id: number) => projectService.remove(id), onSuccess: refresh })
  return { create, update, remove }
}

export function useProjectTasks(projectId: number) {
  return useQuery({
    queryKey: ["project-tasks", projectId],
    queryFn: () => projectService.tasks(projectId),
    enabled: Number.isFinite(projectId),
  })
}

export function useProjectTaskMutations(projectId: number) {
  const queryClient = useQueryClient()
  const tasksKey = ["project-tasks", projectId]

  // Task edits change the project's rolled-up progress counts, so refresh those too.
  const refresh = () => {
    queryClient.invalidateQueries({ queryKey: tasksKey })
    queryClient.invalidateQueries({ queryKey: ["project", projectId] })
    queryClient.invalidateQueries({ queryKey: ["projects"] })
  }

  const create = useMutation({ mutationFn: (input: ProjectTaskInput) => projectService.createTask(projectId, input), onSuccess: refresh })
  const update = useMutation({ mutationFn: ({ id, input }: { id: number; input: ProjectTaskInput }) => projectService.updateTask(id, input), onSuccess: refresh })
  const remove = useMutation({ mutationFn: (id: number) => projectService.removeTask(id), onSuccess: refresh })

  // Optimistic so a drag lands in the new column immediately, rolled back if the request fails.
  const move = useMutation({
    mutationFn: ({ id, status }: { id: number; status: TaskStatus }) => projectService.moveTask(id, status),
    onMutate: async ({ id, status }) => {
      await queryClient.cancelQueries({ queryKey: tasksKey })
      const previous = queryClient.getQueryData<ProjectTask[]>(tasksKey)
      queryClient.setQueryData<ProjectTask[]>(tasksKey, (tasks) => tasks?.map((task) => (task.id === id ? { ...task, status } : task)))
      return { previous }
    },
    onError: (_error, _variables, context) => {
      if (context?.previous) queryClient.setQueryData(tasksKey, context.previous)
    },
    onSettled: refresh,
  })

  return { create, update, remove, move }
}
