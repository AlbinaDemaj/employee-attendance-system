import { useEffect, useState } from 'react'
import { api, errorMessage } from '../api'
import { Alert, Empty, Loading, StatusBadge, dmy, fmtMinutes, monthLabel } from '../components/ui'

const thisMonth = () => new Date().toISOString().slice(0, 7)

export default function MyHistory() {
  const [month, setMonth] = useState(thisMonth())
  const [data, setData] = useState(null)
  const [error, setError] = useState(null)

  useEffect(() => {
    setData(null)
    api
      .get('/attendance/history', { params: { month } })
      .then((res) => setData(res.data))
      .catch((err) => setError(errorMessage(err)))
  }, [month])

  if (error) return <Alert kind="error">{error}</Alert>

  return (
    <>
      <div className="page-head">
        <div>
          <h1>Historiku im</h1>
          <p className="sub">{monthLabel(month)}</p>
        </div>
        <div className="inline-field">
          <div>
            <label htmlFor="month">Muaji</label>
            <input id="month" type="month" value={month} onChange={(e) => setMonth(e.target.value)} />
          </div>
        </div>
      </div>

      {!data ? (
        <Loading />
      ) : (
        <>
          <div className="grid">
            <div className="stat green">
              <div className="value">{data.summary.present}</div>
              <div className="label">Ditë në punë</div>
            </div>
            <div className="stat amber">
              <div className="value">{data.summary.late}</div>
              <div className="label">Vonesa</div>
              <div className="hint">gjithsej {fmtMinutes(data.summary.late_minutes_total)}</div>
            </div>
            <div className="stat red">
              <div className="value">{data.summary.absent}</div>
              <div className="label">Mungesa</div>
              <div className="hint">{data.summary.unexcused_absent} të pajustifikuara</div>
            </div>
            <div className="stat blue">
              <div className="value">{data.summary.sick_days}</div>
              <div className="label">Ditë sëmundjeje</div>
            </div>
          </div>

          <div className="card flush">
            <div className="table-wrap">
              <table>
                <thead>
                  <tr>
                    <th>Data</th>
                    <th>Statusi</th>
                    <th>Pritej</th>
                    <th>Hyrja</th>
                    <th>Dalja</th>
                    <th>Vonesa</th>
                    <th className="wrap">Arsyeja</th>
                    <th>Justifikuar</th>
                  </tr>
                </thead>
                <tbody>
                  {data.records.length === 0 && (
                    <tr>
                      <td colSpan={8} className="full">
                        <Empty icon="📅">Nuk ka të dhëna për këtë muaj.</Empty>
                      </td>
                    </tr>
                  )}
                  {data.records.map((r) => (
                    <tr key={r.id}>
                      <td data-label="Data">{dmy(r.work_date)}</td>
                      <td data-label="Statusi">
                        <StatusBadge
                          status={r.status}
                          label={r.status_label}
                          color={r.status_color}
                          emoji={r.status_emoji}
                        />
                      </td>
                      <td data-label="Pritej" className="num">{r.expected_time}</td>
                      <td data-label="Hyrja" className="num">{r.checkin_time ?? '—'}</td>
                      <td data-label="Dalja" className="num">{r.checkout_time ?? '—'}</td>
                      <td data-label="Vonesa" className="num" style={{ color: r.late_minutes ? 'var(--red)' : undefined }}>
                        {r.late_minutes ? fmtMinutes(r.late_minutes) : '—'}
                      </td>
                      <td data-label="Arsyeja" className="wrap">
                        {r.reason_label ?? '—'}
                        {r.reason_note && <div className="small muted">“{r.reason_note}”</div>}
                      </td>
                      <td data-label="Justifikuar">{r.excused ? '✅' : '❌'}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        </>
      )}
    </>
  )
}
