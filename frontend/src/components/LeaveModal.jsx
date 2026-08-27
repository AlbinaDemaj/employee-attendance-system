import { useState } from 'react'
import { useMeta } from '../MetaContext'
import { Modal } from './ui'

const today = () => new Date().toISOString().slice(0, 10)

/**
 * Rrjedha e lejes mjekësore: lloji → nga data → deri më datë → përshkrim →
 * certifikatë. Fusha e skedarit përdor capture="environment", kështu që në
 * telefon hapet direkt kamera; nga kompjuteri zgjidhet një foto ose PDF.
 */
export default function LeaveModal({ defaultType = 'sick_leave', onSubmit, onClose, busy }) {
  const { leave_types: leaveTypes } = useMeta()
  const [type, setType] = useState(defaultType)
  const [startDate, setStartDate] = useState(today())
  const [endDate, setEndDate] = useState(today())
  const [description, setDescription] = useState('')
  const [file, setFile] = useState(null)
  const [error, setError] = useState(null)

  const needsCertificate = type === 'sick_leave'

  function submit(e) {
    e.preventDefault()
    setError(null)

    if (endDate < startDate) {
      setError('Data e mbarimit nuk mund të jetë para datës së fillimit.')
      return
    }

    const form = new FormData()
    form.append('type', type)
    form.append('start_date', startDate)
    form.append('end_date', endDate)
    if (description.trim()) form.append('description', description.trim())
    if (file) form.append('certificate', file)

    onSubmit(form)
  }

  return (
    <Modal
      title="Kërkesë e re për leje"
      icon="📄"
      subtitle="Menaxheri juaj e shqyrton dhe e aprovon ose e refuzon."
      onClose={onClose}
    >
      <form onSubmit={submit}>
        <div className="field">
          <label htmlFor="leave-type">Lloji i lejes</label>
          <select id="leave-type" value={type} onChange={(e) => setType(e.target.value)}>
            {leaveTypes.map((t) => (
              <option key={t.value} value={t.value}>
                {t.emoji} {t.label}
              </option>
            ))}
          </select>
        </div>

        <div className="field-row">
          <div className="field">
            <label htmlFor="leave-from">Nga data</label>
            <input
              id="leave-from"
              type="date"
              value={startDate}
              onChange={(e) => setStartDate(e.target.value)}
              required
            />
          </div>
          <div className="field">
            <label htmlFor="leave-to">Deri më datë</label>
            <input
              id="leave-to"
              type="date"
              value={endDate}
              onChange={(e) => setEndDate(e.target.value)}
              required
            />
          </div>
        </div>

        <div className="field">
          <label htmlFor="leave-desc">Përshkrimi</label>
          <textarea
            id="leave-desc"
            value={description}
            placeholder="p.sh. Kam temperaturë dhe nuk mund të vij sot."
            onChange={(e) => setDescription(e.target.value)}
          />
        </div>

        <div className="field">
          <label htmlFor="leave-cert">
            Certifikata mjekësore {needsCertificate ? '(foto ose PDF)' : '(opsionale)'}
          </label>
          <input
            id="leave-cert"
            type="file"
            accept="image/*,application/pdf"
            capture="environment"
            onChange={(e) => setFile(e.target.files?.[0] ?? null)}
          />
          <p className="help">
            📷 Në telefon kjo hap direkt kamerën, që ta fotografosh certifikatën në vend.
          </p>
        </div>

        {needsCertificate && !file && (
          <div className="alert warn">
            <span className="ico" aria-hidden="true">🔔</span>
            <div>Pa certifikatë, menaxheri me shumë gjasa do të kërkojë informacion shtesë.</div>
          </div>
        )}

        {error && (
          <div className="alert error">
            <span className="ico" aria-hidden="true">⚠️</span>
            <div>{error}</div>
          </div>
        )}

        <div className="modal-actions">
          <button type="button" onClick={onClose} disabled={busy}>
            Anulo
          </button>
          <button type="submit" className="primary" disabled={busy}>
            {busy ? 'Duke dërguar…' : 'Dërgo kërkesën'}
          </button>
        </div>
      </form>
    </Modal>
  )
}
