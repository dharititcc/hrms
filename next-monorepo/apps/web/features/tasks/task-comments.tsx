"use client"

import { AtSign, MessageSquare, Reply, Send, Trash2 } from "lucide-react"
import { useRef, useState } from "react"
import { Button } from "@workspace/ui/components/button"
import { MentionText, mentionToken } from "@/features/tasks/mention-text"
import { useTaskComments, useTaskCommentMutations } from "@/hooks/use-task-detail"
import { useWorkspaceUsers } from "@/hooks/use-projects"
import { getApiErrorMessage } from "@/lib/api-error"
import { useToast } from "@/providers/toast-provider"
import type { TaskComment } from "@/types/task"

export function TaskComments({ taskId }: { taskId: number }) {
  const { data: comments, isLoading } = useTaskComments(taskId)
  const { add, remove } = useTaskCommentMutations(taskId)
  const { toast } = useToast()
  const [replyTo, setReplyTo] = useState<number | null>(null)

  const submit = async (body: string, parentId: number | null) => {
    try {
      await add.mutateAsync({ body, parentId })
      setReplyTo(null)
    } catch (error) {
      toast({ tone: "error", title: "Unable to post comment", description: getApiErrorMessage(error) })
      throw error
    }
  }

  const confirmDelete = async (comment: TaskComment) => {
    if (!window.confirm("Delete this comment?")) return
    try {
      await remove.mutateAsync(comment.id)
      toast({ tone: "success", title: "Comment deleted" })
    } catch (error) {
      toast({ tone: "error", title: "Unable to delete comment", description: getApiErrorMessage(error) })
    }
  }

  const thread = comments ?? []

  return (
    <section className="rounded-2xl border bg-background p-5">
      <h2 className="flex items-center gap-2 text-sm font-semibold">
        <MessageSquare className="size-4" />Comments
        <span className="rounded-full bg-muted px-2 py-0.5 text-xs font-normal text-muted-foreground">{thread.length}</span>
      </h2>

      <div className="mt-4">
        <CommentComposer onSubmit={(body) => submit(body, null)} placeholder="Write a comment…" />
      </div>

      {isLoading ? (
        <div className="mt-5 grid gap-3">{[1, 2].map((row) => <div key={row} className="h-16 animate-pulse rounded-xl bg-muted" />)}</div>
      ) : thread.length === 0 ? (
        <p className="mt-5 text-sm text-muted-foreground">No comments yet.</p>
      ) : (
        <ol className="mt-5 grid gap-4">
          {thread.map((comment) => (
            <li key={comment.id} className="grid gap-3">
              <CommentCard comment={comment} onReply={() => setReplyTo(replyTo === comment.id ? null : comment.id)} onDelete={() => confirmDelete(comment)} />

              {(comment.replies ?? []).length > 0 && (
                <ol className="ml-6 grid gap-3 border-l pl-4">
                  {(comment.replies ?? []).map((reply) => (
                    <li key={reply.id}>
                      <CommentCard comment={reply} onDelete={() => confirmDelete(reply)} />
                    </li>
                  ))}
                </ol>
              )}

              {replyTo === comment.id && (
                <div className="ml-6 border-l pl-4">
                  <CommentComposer onSubmit={(body) => submit(body, comment.id)} placeholder="Write a reply…" autoFocus />
                </div>
              )}
            </li>
          ))}
        </ol>
      )}
    </section>
  )
}

function CommentCard({ comment, onReply, onDelete }: { comment: TaskComment; onReply?: () => void; onDelete: () => void }) {
  return (
    <article className="rounded-xl border bg-muted/20 p-3">
      <div className="flex items-center justify-between gap-2">
        <div className="flex items-center gap-2">
          <span className="grid size-6 place-items-center rounded-full bg-primary text-[0.65rem] text-primary-foreground">
            {comment.author?.name.slice(0, 1).toUpperCase() ?? "?"}
          </span>
          <span className="text-sm font-medium">{comment.author?.name ?? "Removed user"}</span>
          <time className="text-xs text-muted-foreground" dateTime={comment.created_at}>
            {new Date(comment.created_at).toLocaleString()}
          </time>
        </div>
        <div className="flex gap-1">
          {onReply && <Button variant="ghost" size="icon-xs" aria-label="Reply to comment" onPress={onReply}><Reply /></Button>}
          {comment.can_delete && <Button variant="ghost" size="icon-xs" aria-label="Delete comment" onPress={onDelete}><Trash2 /></Button>}
        </div>
      </div>
      <div className="mt-2">
        <MentionText body={comment.body} />
      </div>
    </article>
  )
}

function CommentComposer({ onSubmit, placeholder, autoFocus }: { onSubmit: (body: string) => Promise<void>; placeholder: string; autoFocus?: boolean }) {
  const [body, setBody] = useState("")
  const [pickerOpen, setPickerOpen] = useState(false)
  const [pending, setPending] = useState(false)
  const { data: users } = useWorkspaceUsers()
  const textarea = useRef<HTMLTextAreaElement>(null)

  /** Inserts a mention token at the caret so the server can resolve it. */
  const insertMention = (user: { id: number; name: string }) => {
    const field = textarea.current
    const token = `${mentionToken(user)} `
    if (!field) {
      setBody((current) => `${current}${token}`)
    } else {
      const start = field.selectionStart ?? body.length
      setBody(`${body.slice(0, start)}${token}${body.slice(field.selectionEnd ?? start)}`)
    }
    setPickerOpen(false)
    queueMicrotask(() => field?.focus())
  }

  const handleSubmit = async (event: React.FormEvent) => {
    event.preventDefault()
    if (!body.trim() || pending) return
    setPending(true)
    try {
      await onSubmit(body.trim())
      setBody("")
    } catch {
      // Toast is raised by the caller; keep the draft so nothing is lost.
    } finally {
      setPending(false)
    }
  }

  return (
    <form onSubmit={handleSubmit} className="grid gap-2">
      <textarea
        ref={textarea}
        rows={3}
        value={body}
        autoFocus={autoFocus}
        onChange={(event) => setBody(event.target.value)}
        placeholder={placeholder}
        aria-label={placeholder}
        className="w-full rounded-lg border bg-background p-3 text-sm outline-none transition placeholder:text-muted-foreground focus:border-ring focus:ring-3 focus:ring-ring/20"
      />
      <div className="relative flex items-center justify-between">
        <Button type="button" variant="outline" size="sm" onPress={() => setPickerOpen((open) => !open)}>
          <AtSign />Mention
        </Button>

        {pickerOpen && (
          <div className="absolute bottom-10 left-0 z-10 w-64 rounded-xl border bg-popover p-1 shadow-xl">
            {(users ?? []).length === 0 ? (
              <p className="px-3 py-2 text-xs text-muted-foreground">No one to mention yet.</p>
            ) : (
              <ul className="max-h-48 overflow-auto">
                {(users ?? []).map((user) => (
                  <li key={user.id}>
                    <button
                      type="button"
                      onClick={() => insertMention(user)}
                      className="flex w-full items-center justify-between rounded-lg px-3 py-2 text-left text-sm hover:bg-muted"
                    >
                      <span>{user.name}</span>
                      <span className="text-xs text-muted-foreground">{user.email}</span>
                    </button>
                  </li>
                ))}
              </ul>
            )}
          </div>
        )}

        <Button type="submit" size="sm" isDisabled={!body.trim() || pending}>
          <Send />{pending ? "Posting…" : "Comment"}
        </Button>
      </div>
    </form>
  )
}
