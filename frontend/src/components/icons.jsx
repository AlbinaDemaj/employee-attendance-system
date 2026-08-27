/**
 * Ikonat e navigimit — vija të thjeshta 24×24 që marrin ngjyrën e tekstit
 * përreth (`stroke="currentColor"`), që të përshtaten kudo pa u konfiguruar.
 */

function Icon({ children, size = 20, className = '' }) {
  return (
    <svg
      className={`icon ${className}`}
      width={size}
      height={size}
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="1.75"
      strokeLinecap="round"
      strokeLinejoin="round"
      aria-hidden="true"
    >
      {children}
    </svg>
  )
}

export const IconClock = (p) => (
  <Icon {...p}>
    <circle cx="12" cy="12" r="9" />
    <path d="M12 7v5.2l3.4 2" />
  </Icon>
)

export const IconCalendar = (p) => (
  <Icon {...p}>
    <rect x="3" y="5" width="18" height="16" rx="2.5" />
    <path d="M8 3v4M16 3v4M3 10h18" />
  </Icon>
)

export const IconLeave = (p) => (
  <Icon {...p}>
    <path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z" />
    <path d="M14 3v5h5" />
    <path d="M9 13.5h6M9 17h4" />
  </Icon>
)

export const IconBoard = (p) => (
  <Icon {...p}>
    <rect x="3" y="4" width="18" height="16" rx="2.5" />
    <path d="M3 9.5h18M9 9.5V20" />
  </Icon>
)

export const IconInbox = (p) => (
  <Icon {...p}>
    <circle cx="12" cy="12" r="9" />
    <path d="M8.4 12.2l2.4 2.4 4.8-5.2" />
  </Icon>
)

export const IconChart = (p) => (
  <Icon {...p}>
    <path d="M3 21h18" />
    <path d="M7 21v-6M12 21v-11M17 21v-4" />
  </Icon>
)

export const IconUsers = (p) => (
  <Icon {...p}>
    <circle cx="9" cy="8" r="3.5" />
    <path d="M2.5 20.5a6.5 6.5 0 0 1 13 0" />
    <path d="M16.5 5a3.5 3.5 0 0 1 0 6" />
    <path d="M18 15.6a6.5 6.5 0 0 1 3.5 4.9" />
  </Icon>
)

export const IconSettings = (p) => (
  <Icon {...p}>
    <path d="M3 6h12M19 6h2" />
    <path d="M3 12h4M11 12h10" />
    <path d="M3 18h10M17 18h4" />
    <circle cx="17" cy="6" r="2" />
    <circle cx="9" cy="12" r="2" />
    <circle cx="15" cy="18" r="2" />
  </Icon>
)

export const IconBuilding = (p) => (
  <Icon {...p}>
    <path d="M3 21h18" />
    <path d="M5 21V5a2 2 0 0 1 2-2h6a2 2 0 0 1 2 2v16" />
    <path d="M15 21V10h3a2 2 0 0 1 2 2v9" />
    <path d="M9 7h2M9 11h2M9 15h2" />
  </Icon>
)

export const IconBell = (p) => (
  <Icon {...p}>
    <path d="M18 8.8a6 6 0 1 0-12 0c0 4.7-2 6.2-2 6.2h16s-2-1.5-2-6.2" />
    <path d="M10.4 19a2 2 0 0 0 3.2 0" />
  </Icon>
)

export const IconLogout = (p) => (
  <Icon {...p}>
    <path d="M9 4H6.5A2.5 2.5 0 0 0 4 6.5v11A2.5 2.5 0 0 0 6.5 20H9" />
    <path d="M15.5 8.5 19 12l-3.5 3.5" />
    <path d="M19 12H9.5" />
  </Icon>
)

export const IconMenu = (p) => (
  <Icon {...p}>
    <path d="M4 7h16M4 12h16M4 17h16" />
  </Icon>
)

export const IconClose = (p) => (
  <Icon {...p}>
    <path d="M6.5 6.5l11 11M17.5 6.5l-11 11" />
  </Icon>
)

/** Emrat që përdoren te lista e navigimit në Layout. */
export const NAV_ICON = {
  clock: IconClock,
  calendar: IconCalendar,
  leave: IconLeave,
  board: IconBoard,
  inbox: IconInbox,
  chart: IconChart,
  users: IconUsers,
  settings: IconSettings,
  building: IconBuilding,
}
