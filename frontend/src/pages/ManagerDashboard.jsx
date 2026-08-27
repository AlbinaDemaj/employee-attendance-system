import { useCallback, useEffect, useState } from 'react'
import { api, assetUrl, errorMessage } from '../api'
import { useMeta } from '../MetaContext'
import {
  Alert, ApprovalPill, Avatar, Empty, Loading, Modal, StatusBadge, StatusLegend,
  dmy, fmtMinutes, longDate, useToast,
} from '../components/ui'

const today = () => new Date().toISOString().slice(0, 10)

const DECISION = {
  approve: { title: 'Aprovo lejen', icon: '✅', cls: 'success', verb: 'Aprovo' },
  reject: { title: 'Refuzo lejen', icon: '⛔', cls: 'danger', verb: 'Refuzo' },
  request_info: { title: 'Kërko më shumë informacion', icon: '❔', cls: 'primary', verb: 'Dërgo kërkesën' },
}

export default function ManagerDashboard({ onActivity }) {
  const toast = useToast()
  const { statuses } = useMeta()
  const [date, setDate] = useState(today())
  const [data, setData] = useState(null)
  const [error, setError] = useState(null)
  const [decision, setDecision] = useState(null) // { row, kind }
  const [note, setNote] = useState('')
  const [busy, setBusy] = useState(false)

  const load = useCallback(
    (silent = false) => {
      if (!silent) setData(null)
      api
        .get('/manager/dashboard', { params: { date } })
        .then((res) => {
          setData(res.data)
          // Backend-i sapo krijoi njoftimet që mungonin ndërsa përgjigjej.
          onActivity?.()
        })
        .catch((err) => setError(errorMessage(err)))
    },
    [date, onActivity],
  )

  useEffect(() => {
    load()
  }, [load])

  async function decide() {
    const { row, kind } = decision
    setBusy(true)
    try {
      const { data: res } = await api.post(`/leave-requests/${row.leave_request_id}/decide`, {
        decision: kind,
        manager_note: note.trim() || null,
      })
      toast(res.message, 'success')
      setDecision(null)
      setNote('')
      load(true)
      onActivity?.()
    } catch (err) {
      toast(errorMessage(err), 'error')
    } finally {
      setBusy(false)
    }
  }

  /**
   * Kur punonjësi ka dhënë vetëm një arsye (pa kërkesë leje), menaxheri
   * vendos nëse mungesa quhet e justifikuar apo jo.
   */
  async function setExcused(row, excused) {
    setBusy(true)
    try {
      const { data: res } = await api.post(`/manager/employees/${row.user_id}/override`, {
        work_date: date,
        status: row.status,
        excused,
      })
      toast(`${row.name}: ${excused ? 'u justifikua' : 'u shënua e pajustifikuar'}. ${res.message}`, 'success')
      load(true)
    } catch (err) {
      toast(errorMessage(err), 'error')
    } finally {
      setBusy(false)
    }
  }

  if (error) return <Alert kind="error">{error}</Alert>

  return (
    <>
      <div className="page-head">
        <div>
          <h1>Tabela e ditës</h1>
          <p className="sub">
            {longDate(date)}
            {data && ` · ora e serverit ${data.server_time}`}
            {data && !data.is_working_day && ' · nuk është ditë pune'}
          </p>
        </div>
        <div className="btn-row" style={{ alignItems: 'flex-end' }}>
          <div>
            <label htmlFor="board-date">Data</label>
            <input id="board-date" type="date" value={date} onChange={(e) => setDate(e.target.value)} />
          </div>
          <button type="button" onClick={() => load(true)} disabled={busy}>
            🔄 Rifresko
          </button>
        </div>
      </div>

      {!data ? (
        <Loading />
      ) : (
        <>
          <div className="grid">
            <div className="stat green">
              <div className="value">{data.summary.present}</div>
              <div className="label">🟢 Në punë</div>
            </div>
            <div className="stat amber">
              <div className="value">{data.summary.late}</div>
              <div className="label">🟡 Me vonesë</div>
            </div>
            <div className="stat red">
              <div className="value">{data.summary.absent}</div>
              <div className="label">🔴 Mungojnë</div>
              <div className="hint">{data.summary.unexcused} pa arsye</div>
            </div>
            <div className="stat blue">
              <div className="value">{data.summary.on_leave}</div>
              <div className="label">Me leje</div>
            </div>
            <div className="stat brand">
              <div className="value">{data.summary.pending_approvals}</div>
              <div className="label">Presin aprovim</div>
            </div>
          </div>

          <div className="card flush">
            <div className="table-wrap">
              <table>
                <thead>
                  <tr>
                    <th>Punonjësi</th>
                    <th>Pritej</th>
                    <th>Statusi</th>
                    <th>Raportoi</th>
                    <th>Deri më</th>
                    <th>Certifikata</th>
                    <th>Aprovimi</th>
                    <th className="wrap">Arsyeja</th>
                    <th className="wrap">Veprime</th>
                  </tr>
                </thead>
                <tbody>
                  {data.rows.length === 0 && (
                    <tr>
                      <td colSpan={9} className="full">
                        <Empty icon="👥">Nuk ke ende punonjës të caktuar.</Empty>
                      </td>
                    </tr>
                  )}
                  {data.rows.map((row) => (
                    <tr key={row.user_id}>
                      <td data-label="Punonjësi">
                        <span className="person">
                          <Avatar name={row.name} />
                          <span className="meta">
                            <strong>{row.name}</strong>
                            {row.department && <span className="muted tiny">{row.department}</span>}
                          </span>
                        </span>
                      </td>
                      <td data-label="Pritej" className="num">{row.expected_time}</td>
                      <td data-label="Statusi">
                        <StatusBadge
                          status={row.status}
                          label={row.status_label}
                          color={row.status_color}
                          emoji={row.status_emoji}
                        />
                        {row.status === 'late' && row.late_minutes > 0 && (
                          <div className="tiny" style={{ color: 'var(--amber)', marginTop: 3 }}>
                            {fmtMinutes(row.late_minutes)} vonesë · hyri {row.checkin_time}
                          </div>
                        )}
                        {row.status === 'absent' && row.late_minutes > 0 && (
                          <div className="tiny" style={{ color: 'var(--red)', marginTop: 3 }}>
                            {fmtMinutes(row.late_minutes)} pa u paraqitur
                          </div>
                        )}
                        {row.unexcused_absence && (
                          <div style={{ marginTop: 4 }}>
                            <span className="pill unexcused">mungesë e pajustifikuar</span>
                          </div>
                        )}
                      </td>
                      <td data-label="Raportoi" className="num">{row.reported_at ?? '—'}</td>
                      <td data-label="Deri më">{row.until ? dmy(row.until) : '—'}</td>
                      <td data-label="Certifikata">
                        {row.has_certificate ? (
                          <a href={assetUrl(row.certificate_url)} target="_blank" rel="noreferrer">
                            ✅ shiko
                          </a>
                        ) : (
                          '❌'
                        )}
                      </td>
                      <td data-label="Aprovimi">
                        <ApprovalPill status={row.approval_status} label={row.approval_label} />
                      </td>
                      <td data-label="Arsyeja" className="wrap">
                        {row.reason_label ?? '—'}
                        {row.reason_note && <div className="small muted">“{row.reason_note}”</div>}
                        {row.manager_note && <div className="small muted">Shënim: {row.manager_note}</div>}
                      </td>
                      <td data-label="Veprime" className="wrap">
                        {row.leave_request_id ? (
                          <div className="btn-row">
                            <button
                              type="button"
                              className="small success"
                              disabled={busy}
                              onClick={() => setDecision({ row, kind: 'approve' })}
                            >
                              Aprovo
                            </button>
                            <button
                              type="button"
                              className="small danger"
                              disabled={busy}
                              onClick={() => setDecision({ row, kind: 'reject' })}
                            >
                              Refuzo
                            </button>
                            <button
                              type="button"
                              className="small"
                              disabled={busy}
                              onClick={() => setDecision({ row, kind: 'request_info' })}
                            >
                              Kërko info
                            </button>
                          </div>
                        ) : row.status !== 'awaiting' ? (
                          <div className="btn-row">
                            <button
                              type="button"
                              className="small success"
                              disabled={busy || row.excused}
                              onClick={() => setExcused(row, true)}
                            >
                              Justifiko
                            </button>
                            <button
                              type="button"
                              className="small danger"
                              disabled={busy || !row.excused}
                              onClick={() => setExcused(row, false)}
                            >
                              Hiq justifikimin
                            </button>
                          </div>
                        ) : (
                          <span className="muted small">turni s'ka nisur</span>
                        )}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>

            <div className="card-foot">
              <StatusLegend statuses={statuses} />
            </div>
          </div>
        </>
      )}

      {decision && (
        <Modal
          title={`${DECISION[decision.kind].title} — ${decision.row.name}`}
          icon={DECISION[decision.kind].icon}
          subtitle={`${decision.row.leave_type_label} · deri më ${dmy(decision.row.until)}`}
          onClose={() => setDecision(null)}
        >
          {decision.row.has_certificate ? (
            <div className="alert info">
              <span className="ico" aria-hidden="true">📎</span>
              <div>
                Certifikata mjekësore:{' '}
                <a href={assetUrl(decision.row.certificate_url)} target="_blank" rel="noreferrer">
                  hape në një skedë të re
                </a>
              </div>
            </div>
          ) : (
            <div className="alert warn">
              <span className="ico" aria-hidden="true">🔔</span>
              <div>Nuk është ngarkuar asnjë certifikatë për këtë kërkesë.</div>
            </div>
          )}

          {decision.row.reason_note && (
            <p className="small soft" style={{ marginBottom: '0.9rem' }}>
              Shpjegimi i punonjësit: “{decision.row.reason_note}”
            </p>
          )}

          <div className="field">
            <label htmlFor="decide-note">Shënim për punonjësin (opsional)</label>
            <textarea
              id="decide-note"
              value={note}
              onChange={(e) => setNote(e.target.value)}
              placeholder={
                decision.kind === 'request_info'
                  ? 'p.sh. Ju lutem dërgoni një foto më të qartë të certifikatës.'
                  : 'p.sh. Shërim të shpejtë.'
              }
            />
          </div>

          <div className="modal-actions">
            <button type="button" onClick={() => setDecision(null)} disabled={busy}>
              Anulo
            </button>
            <button
              type="button"
              className={DECISION[decision.kind].cls}
              onClick={decide}
              disabled={busy}
            >
              {busy ? 'Duke ruajtur…' : DECISION[decision.kind].verb}
            </button>
          </div>
        </Modal>
      )}
    </>
  )
}
