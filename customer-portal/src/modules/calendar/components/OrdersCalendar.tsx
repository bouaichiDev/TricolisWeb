import frLocale from '@fullcalendar/core/locales/fr'
import dayGridPlugin from '@fullcalendar/daygrid'
import interactionPlugin from '@fullcalendar/interaction'
import listPlugin from '@fullcalendar/list'
import FullCalendar from '@fullcalendar/react'
import timeGridPlugin from '@fullcalendar/timegrid'

import type { CalendarOrderEvent, CalendarRange } from '../types'
import { toDateOnly } from '../utils/range'
import { CalendarEventContent } from './CalendarEventContent'

interface OrdersCalendarProps {
  events: CalendarOrderEvent[]
  onRangeChange: (range: CalendarRange) => void
  onEventClick: (event: CalendarOrderEvent) => void
}

/**
 * FullCalendar, vues mois / semaine / jour / liste (§4).
 *
 * `datesSet` annonce la période visible : c'est elle, et elle seule, que la
 * page demande au backend. Chaque événement porte la teinte et le libellé
 * du statut de sa commande (`CalendarEventContent`), les mêmes que partout
 * ailleurs dans le portail.
 */
export function OrdersCalendar({ events, onRangeChange, onEventClick }: OrdersCalendarProps) {
  return (
    <div className="portal-calendar bg-card rounded-lg border p-2 sm:p-4">
      <FullCalendar
        plugins={[dayGridPlugin, timeGridPlugin, listPlugin, interactionPlugin]}
        locale={frLocale}
        initialView="dayGridMonth"
        headerToolbar={{ left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek' }}
        buttonText={{ month: 'Mois', week: 'Semaine', day: 'Jour', list: 'Liste' }}
        height="auto"
        dayMaxEvents={4}
        nowIndicator
        eventDisplay="block"
        eventBackgroundColor="transparent"
        eventBorderColor="transparent"
        eventTextColor="inherit"
        events={events.map((event) => ({ ...event, end: event.end ?? undefined }))}
        eventContent={(arg) => {
          const event = events.find((candidate) => candidate.id === arg.event.id)

          return event ? <CalendarEventContent event={event} timeText={arg.timeText} /> : arg.event.title
        }}
        datesSet={(info) => onRangeChange({ from: toDateOnly(info.start), to: toDateOnly(info.end) })}
        eventClick={(info) => {
          info.jsEvent.preventDefault()
          const event = events.find((candidate) => candidate.id === info.event.id)
          if (event) onEventClick(event)
        }}
      />
    </div>
  )
}
