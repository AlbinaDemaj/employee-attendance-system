import { useCallback, useEffect, useState } from 'react'
import { api } from '../api'
import { Empty, Modal } from './ui'
import { IconBell } from './icons'

/**
 * Zilja në shiritin e sipërm. Backend-i i krijon njoftimet "nuk ka bërë
 * check-in" ndërsa përgjigjet për tabelën e ditës, prandaj mjafton ta
 * rifreskojmë listën pas ngarkimit të tabelës — pa cron në sfond.
 */
const ICONS = {
  missing_checkin: '🔴',
  late_checkin: '🟡',
  reason_reported: '💬',
  leave_requested: '📄',
  leave_decided: '✅',
  info_requested: '❔',
  day_overridden: '✏️',
}

export default function Notifications({ refreshKey }) {
  const [items, setItems] = useState([])
  const [unread, setUnread] = useState(0)
  const [open, setOpen] = useState(false)

  const load = useCallback(() => {
    api
      .get('/notifications')
      .then(({ data }) => {
        setItems(data.notifications)
        setUnread(data.unread_count)
      })
      .catch(() => {})
  }, [])

  useEffect(() => {
    load()
  }, [load, refreshKey])

  async function markAllRead() {
    await api.post('/notifications/read-all')
    load()
  }

  return (
    <>
      <button
        type="button"
        className="bell"
        title="Njoftimet"
        onClick={() => {
          setOpen(true)
          load()
        }}
      >
        <IconBell />
        {unread > 0 && <span className="count">{unread}</span>}
      </button>

      {open && (
        <Modal
          title="Njoftimet"
          icon="🔔"
          subtitle={unread > 0 ? `${unread} të palexuara` : 'Nuk ka njoftime të palexuara'}
          onClose={() => setOpen(false)}
        >
          <div className="notif-list">
            {items.length === 0 && <Empty icon="🔕">Ende asnjë njoftim.</Empty>}
            {items.map((n) => (
              <div key={n.id} className={`notif ${n.read_at ? '' : 'unread'}`}>
                <span className="ico" aria-hidden="true">{ICONS[n.type] ?? '🔔'}</span>
                <div>
                  <div className="title">{n.title}</div>
                  <div className="body">{n.body}</div>
                  <div className="when">{n.created_at_human}</div>
                </div>
              </div>
            ))}
          </div>

          <div className="modal-actions">
            {unread > 0 && (
              <button type="button" onClick={markAllRead}>
                Shëno të gjitha si të lexuara
              </button>
            )}
            <button type="button" className="primary" onClick={() => setOpen(false)}>
              Mbyll
            </button>
          </div>
        </Modal>
      )}
    </>
  )
}
