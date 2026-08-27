import { useState } from 'react'
import { useMeta } from '../MetaContext'
import { Modal } from './ui'

/**
 * "Ishit pritur në orën 08:00. Cila është arsyeja pse nuk keni bërë check-in?"
 * Zgjidhet një arsye nga lista fikse, plus një shënim i lirë teksti.
 */
export default function ReasonModal({ prompt, expectedTime, onSubmit, onClose, busy }) {
  const { reasons } = useMeta()
  const [category, setCategory] = useState(null)
  const [note, setNote] = useState('')

  return (
    <Modal
      title="Cila është arsyeja?"
      icon="🕒"
      subtitle={prompt || `Ishit pritur në orën ${expectedTime}.`}
      onClose={onClose}
    >
      <div className="reason-list">
        {reasons.map((r) => (
          <button
            type="button"
            key={r.value}
            className={`reason-option ${category === r.value ? 'selected' : ''}`}
            onClick={() => setCategory(r.value)}
            aria-pressed={category === r.value}
          >
            <span className="emoji" aria-hidden="true">{r.emoji}</span>
            {r.label}
          </button>
        ))}
      </div>

      <div className="field">
        <label htmlFor="reason-note">Shënim shtesë (opsional)</label>
        <textarea
          id="reason-note"
          value={note}
          placeholder="p.sh. Kam temperaturë dhe nuk mund të vij sot."
          onChange={(e) => setNote(e.target.value)}
        />
      </div>

      {category === 'sick' && (
        <div className="alert info">
          <span className="ico" aria-hidden="true">💡</span>
          <div>
            Për sëmundje duhet dërguar edhe kërkesa me certifikatën mjekësore — përdor butonin
            <strong> Kërko leje</strong> pas këtij hapi.
          </div>
        </div>
      )}

      <div className="modal-actions">
        <button type="button" onClick={onClose} disabled={busy}>
          Anulo
        </button>
        <button
          type="button"
          className="primary"
          disabled={!category || busy}
          onClick={() => onSubmit(category, note.trim() || null)}
        >
          {busy ? 'Duke dërguar…' : 'Dërgo arsyen'}
        </button>
      </div>
    </Modal>
  )
}
