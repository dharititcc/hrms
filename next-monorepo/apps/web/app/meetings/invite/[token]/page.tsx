import { GuestInvitation } from "@/features/meetings/guest-invitation"
import type { Metadata } from "next"

export const metadata: Metadata = { title: "Meeting invitation" }

/**
 * Public, deliberately outside the (app) group so no auth guard applies —
 * guests have no account.
 */
export default async function GuestInvitationPage({ params }: { params: Promise<{ token: string }> }) {
  const { token } = await params

  return <GuestInvitation token={token} />
}
