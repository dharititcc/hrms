"use client"

import { Clock, Pause, Play, Plus, Trash2 } from "lucide-react"
import { useState } from "react"
import { Button } from "@workspace/ui/components/button"
import { useRunningTimer, useTaskTimeEntries, useTaskTimerMutations } from "@/hooks/use-task-detail"
import { getApiErrorMessage } from "@/lib/api-error"
import { useToast } from "@/providers/toast-provider"
import type { TaskTimeEntry } from "@/types/task"

/** 135 -> "2h 15m" */
function formatMinutes(minutes: number) {
  if (minutes < 60) return `${minutes}m`
  const hours = Math.floor(minutes / 60)
  const rest = minutes % 60
  return rest === 0 ? `${hours}h` : `${hours}h ${rest}m`
}

export function TaskTimer({ taskId }: { taskId: number }) {
  const { data: entries, isLoading } = useTaskTimeEntries(taskId)
  const { data: running } = useRunningTimer()
  const { start, stop, log, remove } = useTaskTimerMutations(taskId)
  const { toast } = useToast()
  const [showManual, setShowManual] = useState(false)

  const runningHere = running?.task_id === taskId
  const runningElsewhere = running != null && !runningHere

  const toggleTimer = async () => {
    try {
      if (runningHere) {
        await stop.mutateAsync()
        toast({ tone: "success", title: "Timer stopped" })
      } else {
        await start.mutateAsync()
        toast({
          tone: "success",
          title: "Timer started",
          // Be explicit that starting here stopped the other one.
          description: runningElsewhere ? "Your timer on the other task was stopped." : undefined,
        })
      }
    } catch (error) {
      toast({ tone: "error", title: "Unable to update timer", description: getApiErrorMessage(error) })
    }
  }

  const list = entries?.data ?? []
  const total = entries?.meta.total_minutes ?? 0

  return (
    <section className="rounded-2xl border bg-background p-5">
      <div className="flex items-center justify-between">
        <h2 className="flex items-center gap-2 text-sm font-semibold"><Clock className="size-4" />Time tracking</h2>
        <span className="text-xs text-muted-foreground">{formatMinutes(total)} logged</span>
      </div>

      <div className="mt-4 flex flex-wrap items-center gap-2">
        <Button variant={runningHere ? "destructive" : "default"} size="sm" onPress={() => void toggleTimer()} isDisabled={start.isPending || stop.isPending}>
          {runningHere ? <><Pause />Stop timer</> : <><Play />Start timer</>}
        </Button>
        <Button variant="outline" size="sm" onPress={() => setShowManual((open) => !open)}><Plus />Log time</Button>
        {runningElsewhere && (
          <span className="text-xs text-muted-foreground">A timer is running on another task; starting here will stop it.</span>
        )}
      </div>

      {showManual && <ManualEntryForm onSubmit={async (input) => {
        try {
          await log.mutateAsync(input)
          setShowManual(false)
          toast({ tone: "success", title: "Time logged" })
        } catch (error) {
          toast({ tone: "error", title: "Unable to log time", description: getApiErrorMessage(error) })
        }
      }} onCancel={() => setShowManual(false)} pending={log.isPending} />}

      {isLoading ? (
        <div className="mt-4 grid gap-2">{[1, 2].map((row) => <div key={row} className="h-10 animate-pulse rounded-lg bg-muted" />)}</div>
      ) : list.length === 0 ? (
        <p className="mt-4 text-sm text-muted-foreground">No time logged yet.</p>
      ) : (
        <ul className="mt-4 grid gap-1">
          {list.map((entry) => <TimeRow key={entry.id} entry={entry} onDelete={() => void remove.mutateAsync(entry.id)} />)}
        </ul>
      )}
    </section>
  )
}

function TimeRow({ entry, onDelete }: { entry: TaskTimeEntry; onDelete: () => void }) {
  return (
    <li className="group flex items-center gap-3 rounded-lg px-2 py-1.5 text-sm hover:bg-muted/50">
      <span className="font-medium tabular-nums">{entry.is_running ? "Running…" : formatMinutes(entry.duration_minutes)}</span>
      <span className="text-xs text-muted-foreground">{entry.user_name}</span>
      {entry.description && <span className="truncate text-xs text-muted-foreground">{entry.description}</span>}
      {entry.is_manual && <span className="rounded-full bg-muted px-2 py-0.5 text-[0.7rem] text-muted-foreground">Manual</span>}
      <time className="ml-auto text-xs text-muted-foreground" dateTime={entry.started_at}>
        {new Date(entry.started_at).toLocaleDateString()}
      </time>
      <Button
        variant="ghost"
        size="icon-xs"
        aria-label="Delete time entry"
        className="opacity-0 transition-opacity group-hover:opacity-100 focus-visible:opacity-100"
        onPress={onDelete}
      >
        <Trash2 />
      </Button>
    </li>
  )
}

function ManualEntryForm({ onSubmit, onCancel, pending }: {
  onSubmit: (input: { started_at: string; ended_at: string; description?: string | null }) => Promise<void>
  onCancel: () => void
  pending: boolean
}) {
  const [startedAt, setStartedAt] = useState("")
  const [endedAt, setEndedAt] = useState("")
  const [description, setDescription] = useState("")

  return (
    <form
      className="mt-4 grid gap-3 rounded-xl border bg-muted/20 p-3"
      onSubmit={(event) => {
        event.preventDefault()
        if (!startedAt || !endedAt) return
        void onSubmit({ started_at: startedAt, ended_at: endedAt, description: description || null })
      }}
    >
      <div className="grid gap-3 sm:grid-cols-2">
        <label className="grid gap-1 text-xs font-medium">
          Started
          <input
            type="datetime-local"
            value={startedAt}
            onChange={(event) => setStartedAt(event.target.value)}
            className="h-9 rounded-lg border bg-background px-2 text-sm outline-none focus:border-ring focus:ring-3 focus:ring-ring/20"
          />
        </label>
        <label className="grid gap-1 text-xs font-medium">
          Ended
          <input
            type="datetime-local"
            value={endedAt}
            onChange={(event) => setEndedAt(event.target.value)}
            className="h-9 rounded-lg border bg-background px-2 text-sm outline-none focus:border-ring focus:ring-3 focus:ring-ring/20"
          />
        </label>
      </div>
      <input
        value={description}
        onChange={(event) => setDescription(event.target.value)}
        placeholder="What did you work on? (optional)"
        aria-label="Time entry description"
        className="h-9 rounded-lg border bg-background px-3 text-sm outline-none focus:border-ring focus:ring-3 focus:ring-ring/20"
      />
      <div className="flex justify-end gap-2">
        <Button type="button" variant="outline" size="sm" onPress={onCancel}>Cancel</Button>
        <Button type="submit" size="sm" isDisabled={!startedAt || !endedAt || pending}>{pending ? "Saving…" : "Log time"}</Button>
      </div>
    </form>
  )
}
