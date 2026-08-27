import { useEffect, useState } from 'react'
import { api, errorMessage } from '../api'
import { Alert, Avatar, Empty, Loading, monthLabel } from '../components/ui'

const thisMonth = () => new Date().toISOString().slice(0, 7)

export default function MonthlyReport() {
  const [month, setMonth] = useState(thisMonth())
  const [data, setData] = useState(null)
  const [error, setError] = useState(null)

  useEffect(() => {
    setData(null)
    api
      .get('/manager/monthly-report', { params: { month } })
      .then((res) => setData(res.data))
      .catch((err) => setError(errorMessage(err)))
  }, [month])

  if (error) return <Alert kind="error">{error}</Alert>

  const totals = data?.rows.reduce(
    (acc, r) => ({
      late: acc.late + r.late,
      absent: acc.absent + r.absent,
      unexcused: acc.unexcused + r.unexcused_absent,
      sick: acc.sick + r.sick_days,
    }),
    { late: 0, absent: 0, unexcused: 0, sick: 0 },
  )

  return (
    <>
      <div className="page-head">
        <div>
          <h1>Raporti mujor</h1>
          <p className="sub">{monthLabel(month)}</p>
        </div>
        <div>
          <label htmlFor="report-month">Muaji</label>
          <input id="report-month" type="month" value={month} onChange={(e) => setMonth(e.target.value)} />
        </div>
      </div>

      {!data ? (
        <Loading />
      ) : (
        <>
          <div className="grid">
            <div className="stat amber">
              <div className="value">{totals.late}</div>
              <div className="label">Vonesa gjithsej</div>
            </div>
            <div className="stat red">
              <div className="value">{totals.absent}</div>
              <div className="label">Mungesa gjithsej</div>
              <div className="hint">{totals.unexcused} të pajustifikuara</div>
            </div>
            <div className="stat blue">
              <div className="value">{totals.sick}</div>
              <div className="label">Ditë sëmundjeje</div>
            </div>
            <div className="stat brand">
              <div className="value">{data.rows.length}</div>
              <div className="label">Punonjës në raport</div>
            </div>
          </div>

          <div className="card flush">
            <div className="table-wrap">
              <table>
                <thead>
                  <tr>
                    <th>Punonjësi</th>
                    <th>Vonesa</th>
                    <th>Minuta vonesë</th>
                    <th>Sëmundje</th>
                    <th>Mungesa</th>
                    <th>Të pajustifikuara</th>
                    <th>Leje të aprovuara</th>
                    <th>Udhëtim pune</th>
                    <th>Ditë në punë</th>
                  </tr>
                </thead>
                <tbody>
                  {data.rows.length === 0 && (
                    <tr>
                      <td colSpan={9} className="full">
                        <Empty icon="📊">Asnjë punonjës për këtë muaj.</Empty>
                      </td>
                    </tr>
                  )}
                  {data.rows.map((r) => (
                    <tr key={r.user_id}>
                      <td data-label="Punonjësi">
                        <span className="person">
                          <Avatar name={r.name} />
                          <span className="meta">
                            <strong>{r.name}</strong>
                            {r.department && <span className="muted tiny">{r.department}</span>}
                          </span>
                        </span>
                      </td>
                      <td
                        data-label="Vonesa"
                        className="num"
                        style={{ color: r.late ? 'var(--amber)' : undefined, fontWeight: r.late ? 800 : 400 }}
                      >
                        {r.late}
                      </td>
                      <td data-label="Minuta vonesë" className="num muted">{r.late_minutes}</td>
                      <td data-label="Sëmundje" className="num">{r.sick_days ? `${r.sick_days} ditë` : 0}</td>
                      <td
                        data-label="Mungesa"
                        className="num"
                        style={{ color: r.absent ? 'var(--red)' : undefined, fontWeight: r.absent ? 800 : 400 }}
                      >
                        {r.absent}
                      </td>
                      <td data-label="Të pajustifikuara" className="num">
                        {r.unexcused_absent > 0 ? (
                          <span className="pill unexcused">{r.unexcused_absent}</span>
                        ) : (
                          0
                        )}
                      </td>
                      <td data-label="Leje të aprovuara" className="num">
                        {r.approved_leave_days ? `${r.approved_leave_days} ditë` : 0}
                      </td>
                      <td data-label="Udhëtim pune" className="num">{r.business_trip_days}</td>
                      <td data-label="Ditë në punë" className="num">{r.present_days}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>

            <div className="card-foot">
              <p className="small muted">
                “Të pajustifikuara” numëron mungesat ku nuk u dha asnjë arsye dhe nuk u aprovua asgjë.
              </p>
            </div>
          </div>
        </>
      )}
    </>
  )
}
