import { useCallback, useEffect, useState } from 'react'
import { api, errorMessage } from '../api'
import { Alert, Loading, fmtMinutes, longDate, useToast } from '../components/ui'
import ReasonModal from '../components/ReasonModal'
import LeaveModal from '../components/LeaveModal'

export default function EmployeeDashboard({ onActivity }) {
  const toast = useToast()
  const [today, setToday] = useState(null)
  const [error, setError] = useState(null)
  const [busy, setBusy] = useState(false)
  const [showReason, setShowReason] = useState(false)
  const [showLeave, setShowLeave] = useState(false)

  const load = useCallback(() => {
    api
      .get('/attendance/today')
      .then(({ data }) => setToday(data))
      .catch((err) => setError(errorMessage(err)))
  }, [])

  useEffect(() => {
    load()
  }, [load])

  // Modali hapet vetë sapo backend-i thotë se mungon një arsye.
  useEffect(() => {
    if (today && (today.needs_reason || today.needs_late_reason)) setShowReason(true)
  }, [today])

  async function run(fn, after) {
    setBusy(true)
    try {
      const { data } = await fn()
      toast(data.message, after?.kind ?? 'success')
      after?.close?.()
      load()
      onActivity?.()
    } catch (err) {
      toast(errorMessage(err), 'error')
    } finally {
      setBusy(false)
    }
  }

  const checkIn = () =>
    run(() => api.post('/attendance/check-in'), {
      kind: today?.minutes_overdue > 0 ? 'error' : 'success',
    })

  const checkOut = () => run(() => api.post('/attendance/check-out'))

  const sendReason = (reason_category, reason_note) =>
    run(() => api.post('/attendance/reason', { reason_category, reason_note }), {
      close: () => setShowReason(false),
    })

  const sendLeave = (form) =>
    run(() => api.post('/leave-requests', form), { close: () => setShowLeave(false) })

  if (error) return <Alert kind="error">{error}</Alert>
  if (!today) return <Loading />

  const owesReason = today.needs_reason || today.needs_late_reason
  const networkOk = !today.network?.required || today.network?.allowed
  const heroColor = today.status_color

  return (
    <>
      <div className="page-head">
        <div>
          <h1>Dita ime</h1>
          <p className="sub">
            {longDate(today.date)} · ora e serverit {today.server_time}
            {!today.is_working_day && ' · nuk është ditë pune'}
          </p>
        </div>
      </div>

      {/* Karta kryesore: një vështrim i vetëm tregon ku je dhe çfarë të duhet të bësh */}
      <section
        className="hero"
        style={{ background: `linear-gradient(120deg, ${heroColor}, ${heroColor}cc)` }}
      >
        <div className="hero-top">STATUSI I SOTËM</div>
        <div className="hero-status">
          <span aria-hidden="true">{today.status_emoji}</span>
          {today.status_label}
        </div>

        <p className="hero-note">
          {today.checked_in ? (
            <>
              Check-in në orën <strong>{today.checkin_time}</strong>
              {today.late_minutes > 0 && <> · {fmtMinutes(today.late_minutes)} vonesë</>}
              {today.checkout_time && <> · dalja në {today.checkout_time}</>}
            </>
          ) : today.status === 'awaiting' ? (
            <>Turni yt fillon në orën <strong>{today.expected_time}</strong>.</>
          ) : owesReason ? (
            today.prompt
          ) : today.reason_label ? (
            <>
              Arsyeja e raportuar: <strong>{today.reason_label}</strong>
              {today.excused ? ' · e justifikuar' : ' · pret vendimin e menaxherit'}
            </>
          ) : (
            <>Ora e pritur: <strong>{today.expected_time}</strong></>
          )}
        </p>

        <div className="hero-actions">
          <button
            type="button"
            className="solid"
            onClick={checkIn}
            disabled={busy || today.checked_in || !networkOk}
            title={networkOk ? undefined : 'Kërkohet lidhja me rrjetin e punës'}
          >
            {today.checked_in
              ? `✅ Check-in në ${today.checkin_time}`
              : networkOk
                ? '🚪 Bëj check-in tani'
                : '🚫 Check-in i bllokuar'}
          </button>
          <button
            type="button"
            onClick={checkOut}
            disabled={busy || !today.checked_in || !!today.checkout_time || !networkOk}
          >
            {today.checkout_time ? `Dola në ${today.checkout_time}` : '🏁 Dil nga puna'}
          </button>
          <button type="button" onClick={() => setShowReason(true)} disabled={busy}>
            💬 Raporto arsyen
          </button>
          <button type="button" onClick={() => setShowLeave(true)} disabled={busy}>
            📄 Kërko leje
          </button>
        </div>
      </section>

      {/* A je ne rrjetin e punes? Kjo percakton nese butoni i check-in-it punon. */}
      {today.network?.required && (
        <div className={`netbar ${today.network.allowed ? 'ok' : 'no'}`}>
          <span className="ico" aria-hidden="true">{today.network.allowed ? '📶' : '🚫'}</span>
          <div>
            {today.network.allowed ? (
              <>
                Jeni i lidhur me rrjetin e punës
                {today.network.network_label && <> — <strong>{today.network.network_label}</strong></>}.
                Check-in-i është i mundur.
              </>
            ) : (
              <>
                Nuk jeni në rrjetin e punës, prandaj check-in-i është i bllokuar. Mund të
                raportoni arsyen ose të kërkoni leje nga kudo.
              </>
            )}
            <div className="ip tiny">Adresa juaj: {today.network.ip}</div>
          </div>
        </div>
      )}

      {owesReason && (
        <Alert kind="warn">
          {today.prompt} Zgjidh një arsye që menaxheri ta dijë pse nuk je në punë.
        </Alert>
      )}

      <div className="grid">
        <div className="stat brand">
          <div className="value">{today.expected_time}</div>
          <div className="label">Ora e pritur</div>
        </div>
        <div className="stat green">
          <div className="value">
            {today.checkin_time ?? <span className="muted" style={{ fontSize: '1rem' }}>ende jo</span>}
          </div>
          <div className="label">Hyrja</div>
        </div>
        <div className={`stat ${today.late_minutes ? 'red' : 'green'}`}>
          <div className="value">
            {today.late_minutes ? (
              <span style={{ fontSize: '1.35rem' }}>{fmtMinutes(today.late_minutes)}</span>
            ) : (
              <span className="muted" style={{ fontSize: '1rem' }}>pa vonesë</span>
            )}
          </div>
          <div className="label">Vonesa sot</div>
        </div>
        <div className={`stat ${today.excused ? 'green' : 'amber'}`}>
          <div className="value">{today.excused ? '✅' : '⏳'}</div>
          <div className="label">{today.excused ? 'E justifikuar' : 'Pa justifikim ende'}</div>
        </div>
      </div>

      {today.reason_label && (
        <div className="card">
          <div className="card-head">
            <h2>Arsyeja e raportuar</h2>
            {today.reported_at && <span className="pill">raportuar në {today.reported_at}</span>}
          </div>
          <p>
            <strong>{today.reason_label}</strong>
          </p>
          {today.reason_note && <p className="soft small">“{today.reason_note}”</p>}
        </div>
      )}

      {showReason && (
        <ReasonModal
          prompt={today.prompt}
          expectedTime={today.expected_time}
          busy={busy}
          onSubmit={sendReason}
          onClose={() => setShowReason(false)}
        />
      )}

      {showLeave && (
        <LeaveModal busy={busy} onSubmit={sendLeave} onClose={() => setShowLeave(false)} />
      )}
    </>
  )
}
