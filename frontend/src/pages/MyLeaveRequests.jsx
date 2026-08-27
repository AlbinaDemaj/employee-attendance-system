import { useCallback, useEffect, useState } from 'react'
import { api, assetUrl, errorMessage } from '../api'
import { Alert, ApprovalPill, Empty, Loading, dmy, useToast } from '../components/ui'
import LeaveModal from '../components/LeaveModal'

export default function MyLeaveRequests({ onActivity }) {
  const toast = useToast()
  const [requests, setRequests] = useState(null)
  const [error, setError] = useState(null)
  const [showModal, setShowModal] = useState(false)
  const [busy, setBusy] = useState(false)

  const load = useCallback(() => {
    api
      .get('/leave-requests')
      .then(({ data }) => setRequests(data.requests))
      .catch((err) => setError(errorMessage(err)))
  }, [])

  useEffect(() => {
    load()
  }, [load])

  async function submit(form) {
    setBusy(true)
    try {
      const { data } = await api.post('/leave-requests', form)
      toast(data.message, 'success')
      setShowModal(false)
      load()
      onActivity?.()
    } catch (err) {
      toast(errorMessage(err), 'error')
    } finally {
      setBusy(false)
    }
  }

  async function withdraw(id) {
    if (!window.confirm('Të tërhiqet kjo kërkesë?')) return
    try {
      const { data } = await api.delete(`/leave-requests/${id}`)
      toast(data.message, 'success')
      load()
    } catch (err) {
      toast(errorMessage(err), 'error')
    }
  }

  if (error) return <Alert kind="error">{error}</Alert>

  return (
    <>
      <div className="page-head">
        <div>
          <h1>Lejet e mia</h1>
          <p className="sub">Kërkesat që ke dërguar dhe vendimi i menaxherit.</p>
        </div>
        <button type="button" className="primary" onClick={() => setShowModal(true)}>
          ➕ Kërkesë e re
        </button>
      </div>

      {!requests ? (
        <Loading />
      ) : (
        <div className="card flush">
          <div className="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>Lloji</th>
                  <th>Nga</th>
                  <th>Deri</th>
                  <th>Ditë</th>
                  <th className="wrap">Përshkrimi</th>
                  <th>Certifikata</th>
                  <th>Statusi</th>
                  <th className="wrap">Shënimi i menaxherit</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                {requests.length === 0 && (
                  <tr>
                    <td colSpan={9} className="full">
                      <Empty icon="📄">Ende asnjë kërkesë për leje.</Empty>
                    </td>
                  </tr>
                )}
                {requests.map((r) => (
                  <tr key={r.id}>
                    <td data-label="Lloji">{r.type_label}</td>
                    <td data-label="Nga">{dmy(r.start_date)}</td>
                    <td data-label="Deri">{dmy(r.end_date)}</td>
                    <td data-label="Ditë" className="num">{r.days}</td>
                    <td data-label="Përshkrimi" className="wrap">{r.description ?? '—'}</td>
                    <td data-label="Certifikata">
                      {r.has_certificate ? (
                        <a href={assetUrl(r.certificate_url)} target="_blank" rel="noreferrer">
                          ✅ shiko
                        </a>
                      ) : (
                        '❌'
                      )}
                    </td>
                    <td data-label="Statusi">
                      <ApprovalPill status={r.status} label={r.status_label} />
                    </td>
                    <td data-label="Shënimi" className="wrap">{r.manager_note ?? '—'}</td>
                    <td data-label="Veprim">
                      {(r.status === 'pending' || r.status === 'info_requested') && (
                        <button type="button" className="small" onClick={() => withdraw(r.id)}>
                          Tërhiq
                        </button>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {showModal && <LeaveModal busy={busy} onSubmit={submit} onClose={() => setShowModal(false)} />}
    </>
  )
}
