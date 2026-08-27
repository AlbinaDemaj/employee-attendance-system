import { createContext, useCallback, useContext, useEffect, useRef, useState } from 'react'
import { createPortal } from 'react-dom'

/* ------------------------------------------------------------- ndihmëse */

/** "Ardit Berisha" -> "AB" */
export function initials(name = '') {
  return name
    .trim()
    .split(/\s+/)
    .slice(0, 2)
    .map((w) => w[0])
    .join('')
    .toUpperCase()
}

export const ROLE_LABEL = {
  super_admin: 'Pronar i platformës',
  admin: 'Administrator',
  manager: 'Menaxher',
  employee: 'Punonjës',
}

export function Avatar({ name, className = '' }) {
  return <span className={`avatar ${className}`}>{initials(name)}</span>
}

/* --------------------------------------------------------------- shenja */

export function StatusBadge({ status, label, color, emoji }) {
  return (
    <span
      className="badge"
      style={{ background: `${color}1f`, borderColor: `${color}59`, color: '#101828' }}
      title={label}
    >
      <span aria-hidden="true">{emoji}</span>
      {label}
    </span>
  )
}

export function ApprovalPill({ status, label }) {
  if (!status) return <span className="muted">—</span>
  return <span className={`pill ${status}`}>{label || status}</span>
}

/* ---------------------------------------------------------------- modal */

export function Modal({ title, subtitle, icon = '📝', onClose, children }) {
  const boxRef = useRef(null)

  // Prindërit e kalojnë onClose si funksion të ri në çdo render
  // (`onClose={() => setEditing(null)}`). Po ta varnim efektin poshtë nga ai,
  // efekti do të rilëshohej pas çdo shkronje të shkruar dhe do t'ia vidhte
  // fokusin fushës. Ndaj e mbajmë të freskët në një ref dhe efekti lëshohet
  // vetëm një herë, kur hapet dialogu.
  const closeRef = useRef(onClose)
  useEffect(() => {
    closeRef.current = onClose
  }, [onClose])

  useEffect(() => {
    const onKey = (e) => e.key === 'Escape' && closeRef.current?.()
    window.addEventListener('keydown', onKey)
    document.body.style.overflow = 'hidden'

    // Fokusi kalon te dialogu, që Escape të funksionojë menjëherë pa pasur
    // nevojë të klikohet fillimisht brenda tij, dhe që lexuesit e ekranit ta
    // njoftojnë hapjen.
    const previous = document.activeElement
    boxRef.current?.focus()

    return () => {
      window.removeEventListener('keydown', onKey)
      document.body.style.overflow = ''
      if (previous instanceof HTMLElement) previous.focus()
    }
  }, [])

  // Dialogu shkon te <body>, jo aty ku thirret. Zilja e njoftimeve rri brenda
  // shiritit të sipërm, i cili ka `backdrop-filter`; ajo veti e bën shiritin
  // bllok përmbajtës për fëmijët `position: fixed`, ndaj sfondi i dialogut do
  // të matej ndaj shiritit dhe do të dilte i shtrembëruar jashtë ekranit.
  return createPortal(
    <div className="modal-backdrop" onMouseDown={(e) => e.target === e.currentTarget && onClose?.()}>
      <div
        className="modal"
        role="dialog"
        aria-modal="true"
        aria-label={title}
        ref={boxRef}
        tabIndex={-1}
      >
        {title && (
          <div className="modal-title">
            <span className="ico" aria-hidden="true">{icon}</span>
            <div>
              <h2>{title}</h2>
              {subtitle && <p className="sub">{subtitle}</p>}
            </div>
          </div>
        )}
        {children}
      </div>
    </div>,
    document.body,
  )
}

/* --------------------------------------------------------------- toasts */

const ToastContext = createContext(null)

const TOAST_ICON = { success: '✅', error: '⚠️', info: 'ℹ️' }

export function ToastProvider({ children }) {
  const [toasts, setToasts] = useState([])

  const push = useCallback((message, kind = 'info') => {
    const id = Math.random().toString(36).slice(2)
    setToasts((t) => [...t, { id, message, kind }])
    setTimeout(() => setToasts((t) => t.filter((x) => x.id !== id)), 5000)
  }, [])

  return (
    <ToastContext.Provider value={push}>
      {children}
      <div className="toasts">
        {toasts.map((t) => (
          <div
            key={t.id}
            className={`toast ${t.kind}`}
            onClick={() => setToasts((x) => x.filter((y) => y.id !== t.id))}
          >
            <span className="ico">{TOAST_ICON[t.kind] ?? 'ℹ️'}</span>
            <span>{t.message}</span>
          </div>
        ))}
      </div>
    </ToastContext.Provider>
  )
}

export function useToast() {
  return useContext(ToastContext) ?? (() => {})
}

/* ----------------------------------------------------------------- misc */

export function Loading({ text = 'Duke u ngarkuar…' }) {
  return (
    <div className="loading">
      <div className="spinner" />
      {text}
    </div>
  )
}

const ALERT_ICON = { error: '⚠️', success: '✅', warn: '🔔', info: 'ℹ️' }

export function Alert({ kind = 'info', children }) {
  if (!children) return null
  return (
    <div className={`alert ${kind}`}>
      <span className="ico" aria-hidden="true">{ALERT_ICON[kind]}</span>
      <div>{children}</div>
    </div>
  )
}

export function Empty({ icon = '📭', children }) {
  return (
    <div className="empty">
      <span className="icon" aria-hidden="true">{icon}</span>
      {children}
    </div>
  )
}

/** Legjenda e ngjyrave nën tabelat. */
export function StatusLegend({ statuses }) {
  return (
    <div className="legend">
      {statuses
        .filter((s) => s.value !== 'awaiting')
        .map((s) => (
          <span key={s.value}>
            <span className="dot" style={{ background: s.color }} />
            {s.label}
          </span>
        ))}
    </div>
  )
}

/** 394 -> "6 orë 34 min", 27 -> "27 min" */
export function fmtMinutes(total) {
  const n = Number(total) || 0
  if (n < 60) return `${n} min`
  const h = Math.floor(n / 60)
  const m = n % 60
  return m ? `${h} orë ${m} min` : `${h} orë`
}

/** "2026-08-20" -> "20.08.2026" */
export function dmy(iso) {
  if (!iso) return '—'
  const [y, m, d] = iso.split('-')
  return `${d}.${m}.${y}`
}

const WEEKDAYS = ['e diel', 'e hënë', 'e martë', 'e mërkurë', 'e enjte', 'e premte', 'e shtunë']
const MONTHS = [
  'janar', 'shkurt', 'mars', 'prill', 'maj', 'qershor',
  'korrik', 'gusht', 'shtator', 'tetor', 'nëntor', 'dhjetor',
]

/** "2026-08-20" -> "e enjte, 20 gusht 2026" */
export function longDate(iso) {
  if (!iso) return ''
  const d = new Date(`${iso}T00:00:00`)
  return `${WEEKDAYS[d.getDay()]}, ${d.getDate()} ${MONTHS[d.getMonth()]} ${d.getFullYear()}`
}

/** "2026-08" -> "gusht 2026" */
export function monthLabel(ym) {
  if (!ym) return ''
  const [y, m] = ym.split('-')
  return `${MONTHS[Number(m) - 1]} ${y}`
}
