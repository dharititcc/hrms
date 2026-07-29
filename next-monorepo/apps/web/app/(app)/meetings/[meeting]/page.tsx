import { MeetingDetail } from "@/features/meetings/meeting-detail"
import type { Metadata } from "next"

export const metadata: Metadata = { title: "Meeting" }

export default async function MeetingDetailPage({ params }: { params: Promise<{ meeting: string }> }) {
  const { meeting } = await params

  return <MeetingDetail meetingId={Number(meeting)} />
}
