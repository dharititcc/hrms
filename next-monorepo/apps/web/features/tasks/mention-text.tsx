"use client"

import { Fragment } from "react"

/**
 * Matches the server's mention token format: @[Name](user:12)
 *
 * Non-global on purpose. A module-level /g regex carries mutable lastIndex
 * state that would be shared across every render, so a fresh global copy is
 * built per call instead.
 */
const MENTION_PATTERN = /@\[([^\]]+)\]\(user:(\d+)\)/

/**
 * Renders a comment body, turning mention tokens into highlighted names.
 * Text is rendered as plain strings, never as HTML, so a comment cannot inject
 * markup.
 */
export function MentionText({ body }: { body: string }) {
  const parts: React.ReactNode[] = []
  let cursor = 0

  for (const match of body.matchAll(new RegExp(MENTION_PATTERN, "g"))) {
    const index = match.index ?? 0
    if (index > cursor) parts.push(body.slice(cursor, index))
    parts.push(
      <span key={`${index}-${match[2]}`} className="rounded bg-primary/10 px-1 font-medium text-primary">
        @{match[1]}
      </span>,
    )
    cursor = index + match[0].length
  }

  if (cursor < body.length) parts.push(body.slice(cursor))

  return <p className="text-sm whitespace-pre-wrap">{parts.map((part, index) => <Fragment key={index}>{part}</Fragment>)}</p>
}

/** Builds the token the server parses back into a mention. */
export function mentionToken(user: { id: number; name: string }) {
  return `@[${user.name}](user:${user.id})`
}
