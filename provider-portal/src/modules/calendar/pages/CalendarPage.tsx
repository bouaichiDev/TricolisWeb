import frLocale from '@fullcalendar/core/locales/fr'
import dayGridPlugin from '@fullcalendar/daygrid'
import interactionPlugin from '@fullcalendar/interaction'
import listPlugin from '@fullcalendar/list'
import FullCalendar from '@fullcalendar/react'
import timeGridPlugin from '@fullcalendar/timegrid'
import { keepPreviousData, useQuery } from '@tanstack/react-query'
import { useState } from 'react'
import { useNavigate } from 'react-router-dom'

import { toursApi } from '@/modules/tours/api/toursApi'
import { errorMessage } from '@/shared/api/errors'
import { ErrorState } from '@/shared/components/feedback/States'
import { PageHeader } from '@/shared/components/layout/PageHeader'

/** Date locale `AAAA-MM-JJ` : FullCalendar raisonne en jours du fuseau du navigateur. */
const dateOnly = (date: Date) =>
  `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`

/**
 * Calendrier des tournées attribuées : seule la période visible est demandée
 * au serveur. Un clic ouvre le détail de la tournée.
 */
export function CalendarPage() {
  const navigate = useNavigate()
  const [range, setRange] = useState<{ from: string; to: string } | null>(null)
  const events = useQuery({
    queryKey: ['tour-calendar', range],
    queryFn: () => toursApi.calendar(range!.from, range!.to),
    enabled: range !== null,
    placeholderData: keepPreviousData,
  })

  return (
    <div className="flex flex-col gap-6">
      <PageHeader title="Calendrier" description="Vos tournées, au jour, à la semaine ou au mois." />
      {events.isError && <ErrorState message={errorMessage(events.error)} onRetry={() => void events.refetch()} />}
      <div className="bg-card rounded-lg border p-2 sm:p-4">
        <FullCalendar
          plugins={[dayGridPlugin, timeGridPlugin, listPlugin, interactionPlugin]}
          locale={frLocale}
          initialView="dayGridMonth"
          headerToolbar={{ left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek' }}
          buttonText={{ month: 'Mois', week: 'Semaine', day: 'Jour', list: 'Liste' }}
          height="auto"
          dayMaxEvents={4}
          nowIndicator
          events={(events.data ?? []).map((event) => ({ id: event.id, title: event.title, start: event.start, end: event.end ?? undefined, allDay: event.allDay }))}
          datesSet={(info) => setRange({ from: dateOnly(info.start), to: dateOnly(info.end) })}
          eventClick={(info) => {
            info.jsEvent.preventDefault()
            navigate(`/tours/${info.event.id}`)
          }}
        />
      </div>
    </div>
  )
}
