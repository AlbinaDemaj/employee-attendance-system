import { useCallback, useEffect, useState } from 'react'
import { api, assetUrl, errorMessage } from '../api'
import { Alert, ApprovalPill, Avatar, Empty, Loading, Modal, dmy, useToast } from '../components/ui'

const FILTERS = [
  { value: '', label: 'Të gjitha' },
  { value: 'pending', label: 'Në pritje' },
  { value: 'info_requested', label: 'Kërkohet info' },
  { value: 'approved', label: 'Aprovuar' },
  { value: 'rejected', label: 'Refuzuar' },
]

export default function ManagerRequests({ onActivity }) {
  const toast = useToast()
  const [requests, setRequests] = useState(null)
  const [filter, setFilter] = useState('')
  const [error, setError] = useState(null)
  const [review, setReview] = useState(null)
  const [note, setNote] = useState('')
  const [busy, setBusy] = useState(false)

  const load = useCallback(() => {
    api
      .get('/leave-requests', { params: filter ? { status: filter } : {} })
      .then(({ data }) => setRequests(data.requests))
      .catch((err) => setError(errorMessage(err)))
  }, [filter])

  useEffect(() => {
    load()
  }, [load])

  async function decide(kind) {
    setBusy(true)
    try {
      const { data } = await api.post(`/leave-requests/${review.id}/decide`, {
        decision: kind,
        manager_note: note.trim() || null,
      })
      toast(data.message, 'success')
      setReview(null)
      setNote('')
      load()
      onActivity?.()
    } catch (err) {
      toast(errorMessage(err), 'error')
    } finally {
      setBusy(false)
    }
  }

  if (error) return <Alert kind="error">{error}</Alert>

  const pending = requests?.filter((r) => r.status === 'pending').length ?? 0

  return (
    <>
      <div className="page-head">
        <div>
          <h1>Kërkesat për leje</h1>
          <p className="sub">
            {pending === 0
              ? 'Asnjë kërkesë në pritje.'
              : pending === 1
                ? '1 kërkesë pret vendimin tënd.'
                : `${pending} kërkesa presin vendimin tënd.`}
          </p>
        </div>
        <div>
          <label htmlFor="filter">Filtro sipas statusit</label>
          <select id="filter" value={filter} onChange={(e) => setFilter(e.target.value)}>
            {FILTERS.map((f) => (
              <option key={f.value} value={f.value}>
                {f.label}
              </option>
            ))}
          </select>
        </div>
      </div>

      {!requests ? (
        <Loading />
      ) : (
        <div className="card flush">
          <div className="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>Punonjësi</th>
                  <th>Lloji</th>
                  <th>Nga</th>
                  <th>Deri</th>
                  <th>Ditë</th>
                  <th className="wrap">Përshkrimi</th>
                  <th>Certifikata</th>
                  <th>Statusi</th>
                  <th>Vendosi</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                {requests.length === 0 && (
                  <tr>
                    <td colSpan={10} className="full">
                      <Empty icon="📄">Asnjë kërkesë me këtë filtër.</Empty>
                    </td>
                  </tr>
                )}
                {requests.map((r) => (
                  <tr key={r.id}>
                    <td data-label="Punonjësi">
                      <span className="person">
                        <Avatar name={r.employee_name} />
                        <span className="meta">
                          <strong>{r.employee_name}</strong>
                          {r.department && <span className="muted tiny">{r.department}</span>}
                        </span>
                      </span>
                    </td>
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
                      {r.manager_note && <div className="small muted">{r.manager_note}</div>}
                    </td>
                    <td data-label="Vendosi">{r.decided_by ?? '—'}</td>
                    <td data-label="Veprim">
                      <button
                        type="button"
                        className="small primary"
                        onClick={() => {
                          setReview(r)
                          setNote('')
                        }}
                      >
                        Shqyrto
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {review && (
        <Modal
          title={`${review.employee_name} — ${review.type_label}`}
          icon="📄"
          subtitle={`${dmy(review.start_date)} → ${dmy(review.end_date)} · ${review.days} ditë`}
          onClose={() => setReview(null)}
        >
          <div className="stack" style={{ marginBottom: '1rem' }}>
            <p className="small">
              <strong>Përshkrimi:</strong> {review.description || '—'}
            </p>
            <p className="small">
              <strong>Certifikata:</strong>{' '}
              {review.has_certificate ? (
                <a href={assetUrl(review.certificate_url)} target="_blank" rel="noreferrer">
                  ✅ hape në një skedë të re
                </a>
              ) : (
                '❌ e pangarkuar'
              )}
            </p>
            <p className="small">
              <strong>Statusi aktual:</strong>{' '}
              <ApprovalPill status={review.status} label={review.status_label} />
            </p>
          </div>

          <div className="field">
            <label htmlFor="review-note">Shënim për punonjësin (opsional)</label>
            <textarea id="review-note" value={note} onChange={(e) => setNote(e.target.value)} />
          </div>

          <div className="modal-actions">
            <button type="button" onClick={() => setReview(null)} disabled={busy}>
              Anulo
            </button>
            <button type="button" onClick={() => decide('request_info')} disabled={busy}>
              Kërko info
            </button>
            <button type="button" className="danger" onClick={() => decide('reject')} disabled={busy}>
              Refuzo
            </button>
            <button type="button" className="success" onClick={() => decide('approve')} disabled={busy}>
              Aprovo
            </button>
          </div>
        </Modal>
      )}
    </>
  )
}
