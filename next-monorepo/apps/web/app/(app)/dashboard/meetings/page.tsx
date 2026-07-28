import { MeetingsModule } from "@/features/meetings/meetings-module"
import type { Metadata } from "next"

export const metadata: Metadata = { title: "Meetings" }

export default function MeetingsPage() { return <MeetingsModule /> }
