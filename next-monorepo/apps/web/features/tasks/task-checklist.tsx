"use client"

import { ListChecks, Plus, Trash2 } from "lucide-react"
import { useState } from "react"
import { Button } from "@workspace/ui/components/button"
import { usePermissions } from "@/hooks/use-permissions"
import { useTaskChecklist, useTaskChecklistMutations } from "@/hooks/use-task-detail"
import { getApiErrorMessage } from "@/lib/api-error"
import { useToast } from "@/providers/toast-provider"
import type { TaskChecklistItem } from "@/types/task"

export function TaskChecklist({ taskId }: { taskId: number }) {
  const { data: items, isLoading } = useTaskChecklist(taskId)
  const { add, toggle, remove } = useTaskChecklistMutations(taskId)
  const { toast } = useToast()
  const { can } = usePermissions()
  const [title, setTitle] = useState("")
  // Checklist changes are edits to the task.
  const editable = can("tasks.edit")

  const list = items ?? []
  const done = list.filter((item) => item.is_completed).length
  const percent = list.length === 0 ? 0 : Math.round((done / list.length) * 100)

  const addItem = async (event: React.FormEvent) => {
    event.preventDefault()
    if (!title.trim()) return
    try {
      await add.mutateAsync(title.trim())
      setTitle("")
    } catch (error) {
      toast({ tone: "error", title: "Unable to add item", description: getApiErrorMessage(error) })
    }
  }

  const toggleItem = async (item: TaskChecklistItem) => {
    try {
      await toggle.mutateAsync({ id: item.id, isCompleted: !item.is_completed })
    } catch (error) {
      toast({ tone: "error", title: "Unable to update item", description: getApiErrorMessage(error) })
    }
  }

  const removeItem = async (item: TaskChecklistItem) => {
    try {
      await remove.mutateAsync(item.id)
    } catch (error) {
      toast({ tone: "error", title: "Unable to delete item", description: getApiErrorMessage(error) })
    }
  }

  return (
    <section className="rounded-2xl border bg-background p-5">
      <div className="flex items-center justify-between">
        <h2 className="flex items-center gap-2 text-sm font-semibold"><ListChecks className="size-4" />Checklist</h2>
        <span className="text-xs text-muted-foreground">{done}/{list.length}</span>
      </div>

      {list.length > 0 && (
        <div className="mt-3 h-1.5 overflow-hidden rounded-full bg-muted">
          <div className="h-full rounded-full bg-primary transition-all" style={{ width: `${percent}%` }} />
        </div>
      )}

      {isLoading ? (
        <div className="mt-4 grid gap-2">{[1, 2].map((row) => <div key={row} className="h-8 animate-pulse rounded-lg bg-muted" />)}</div>
      ) : (
        <ul className="mt-4 grid gap-1">
          {list.map((item) => (
            <li key={item.id} className="group flex items-center gap-2 rounded-lg px-2 py-1.5 hover:bg-muted/50">
              <input
                type="checkbox"
                checked={item.is_completed}
                disabled={!editable}
                onChange={() => void toggleItem(item)}
                aria-label={item.title}
                className="size-4 rounded border disabled:opacity-50"
              />
              <span className={`flex-1 text-sm ${item.is_completed ? "text-muted-foreground line-through" : ""}`}>{item.title}</span>
              {item.completed_by_name && <span className="text-xs text-muted-foreground">{item.completed_by_name}</span>}
              {editable && (
                <Button
                  variant="ghost"
                  size="icon-xs"
                  aria-label={`Delete ${item.title}`}
                  className="opacity-0 transition-opacity group-hover:opacity-100 focus-visible:opacity-100"
                  onPress={() => void removeItem(item)}
                >
                  <Trash2 />
                </Button>
              )}
            </li>
          ))}
        </ul>
      )}

      {editable && <form onSubmit={addItem} className="mt-3 flex gap-2">
        <input
          value={title}
          onChange={(event) => setTitle(event.target.value)}
          placeholder="Add an item"
          aria-label="Add a checklist item"
          className="h-9 flex-1 rounded-lg border bg-background px-3 text-sm outline-none focus:border-ring focus:ring-3 focus:ring-ring/20"
        />
        <Button type="submit" variant="outline" size="sm" isDisabled={!title.trim() || add.isPending}><Plus />Add</Button>
      </form>}
    </section>
  )
}
