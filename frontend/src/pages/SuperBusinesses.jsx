import { useCallback, useEffect, useState } from 'react'
import { api, errorMessage } from '../api'
import { Alert, Avatar, Empty, Loading, Modal, dmy, useToast } from '../components/ui'

const DAY_SHORT = ['H', 'M', 'M', 'E', 'P', 'Sh', 'D']

const blankBusiness = {
  name: '',
  contact_email: '',
  contact_phone: '',
  address: '',
  notes: '',
  admin_name: '',
  admin_email: '',
  admin_password: '',
}

/** Regjistri i klientëve — vetëm pronari i produktit e sheh këtë faqe. */
export default function SuperBusinesses() {
  const toast = useToast()
  const [data, setData] = useState(null)
  const [error, setError] = useState(null)
  const [creating, setCreating] = useState(false)
  const [form, setForm] = useState(blankBusiness)
  const [editing, setEditing] = useState(null)
  const [viewing, setViewing] = useState(null)
  const [busy, setBusy] = useState(false)

  const load = useCallback(() => {
    api
      .get('/super/businesses')
      .then((res) => setData(res.data))
      .catch((err) => setError(errorMessage(err)))
  }, [])

  useEffect(() => {
    load()
  }, [load])

  async function create(e) {
    e.preventDefault()
    setBusy(true)
    try {
      const { data: res } = await api.post('/super/businesses', form)
      toast(res.message, 'success')
      setCreating(false)
      setForm(blankBusiness)
      load()
    } catch (err) {
      toast(errorMessage(err), 'error')
    } finally {
      setBusy(false)
    }
  }

  async function saveEdit(e) {
    e.preventDefault()
    setBusy(true)
    try {
      const { data: res } = await api.put(`/super/businesses/${editing.id}`, {
        name: editing.name,
        contact_email: editing.contact_email || null,
        contact_phone: editing.contact_phone || null,
        address: editing.address || null,
        notes: editing.notes || null,
        is_active: editing.is_active,
      })
      toast(res.message, 'success')
      setEditing(null)
      load()
    } catch (err) {
      toast(errorMessage(err), 'error')
    } finally {
      setBusy(false)
    }
  }

  async function toggleActive(b) {
    try {
      const { data: res } = await api.put(`/super/businesses/${b.id}`, { is_active: !b.is_active })
      toast(res.message, 'success')
      load()
    } catch (err) {
      toast(errorMessage(err), 'error')
    }
  }

  async function remove(b) {
    const typed = window.prompt(
      `Kjo fshin PËRGJITHMONË biznesin "${b.name}" me ${b.users_count} përdorues dhe gjithë historikun e prezencës.\n\n` +
        `Shkruaj emrin e biznesit për të konfirmuar:`,
    )
    if (typed !== b.name) {
      if (typed !== null) toast('Emri nuk përputhi — asgjë nuk u fshi.', 'error')
      return
    }
    try {
      const { data: res } = await api.delete(`/super/businesses/${b.id}`)
      toast(res.message, 'success')
      load()
    } catch (err) {
      toast(errorMessage(err), 'error')
    }
  }

  async function openUsers(b) {
    try {
      const { data: res } = await api.get(`/super/businesses/${b.id}/users`)
      setViewing(res)
    } catch (err) {
      toast(errorMessage(err), 'error')
    }
  }

  if (error) return <Alert kind="error">{error}</Alert>

  return (
    <>
      <div className="page-head">
        <div>
          <h1>Bizneset klientë</h1>
          <p className="sub">
            Krijo llogarinë e një biznesi bashkë me adminin e tij. Nga aty e tutje, biznesi
            i menaxhon vetë punonjësit, orarin dhe rrjetet.
          </p>
        </div>
        <button type="button" className="primary" onClick={() => setCreating(true)}>
          ➕ Biznes i ri
        </button>
      </div>

      {!data ? (
        <Loading />
      ) : (
        <>
          <div className="grid">
            <div className="stat brand">
              <div className="value">{data.totals.businesses}</div>
              <div className="label">Biznese</div>
            </div>
            <div className="stat green">
              <div className="value">{data.totals.active}</div>
              <div className="label">Aktive</div>
            </div>
            <div className="stat blue">
              <div className="value">{data.totals.users}</div>
              <div className="label">Përdorues gjithsej</div>
            </div>
            <div className="stat amber">
              <div className="value">{data.totals.checkins_today}</div>
              <div className="label">Check-in sot</div>
            </div>
          </div>

          <div className="card flush">
            <div className="table-wrap">
              <table>
                <thead>
                  <tr>
                    <th>Biznesi</th>
                    <th>Kontakti</th>
                    <th>Orari</th>
                    <th>Ditët</th>
                    <th>Përdorues</th>
                    <th>Rrjete</th>
                    <th>Check-in sot</th>
                    <th>Gjendja</th>
                    <th>Krijuar</th>
                    <th></th>
                  </tr>
                </thead>
                <tbody>
                  {data.businesses.length === 0 && (
                    <tr>
                      <td colSpan={10} className="full">
                        <Empty icon="🏢">Ende asnjë biznes. Shto klientin e parë.</Empty>
                      </td>
                    </tr>
                  )}
                  {data.businesses.map((b) => (
                    <tr key={b.id} style={{ opacity: b.is_active ? 1 : 0.55 }}>
                      <td data-label="Biznesi">
                        <span className="person">
                          <Avatar name={b.name} />
                          <span className="meta">
                            <strong>{b.name}</strong>
                            <span className="muted tiny">{b.slug}</span>
                          </span>
                        </span>
                      </td>
                      <td data-label="Kontakti">
                        {b.contact_email ?? '—'}
                        {b.contact_phone && <div className="muted tiny">{b.contact_phone}</div>}
                      </td>
                      <td data-label="Orari" className="num">
                        {b.start_time}–{b.end_time}
                      </td>
                      <td data-label="Ditët">
                        <span className="tiny">
                          {b.working_days.map((d) => DAY_SHORT[d - 1]).join(' ')}
                        </span>
                      </td>
                      <td data-label="Përdorues" className="num">
                        {b.users_count}
                        <div className="muted tiny">
                          {b.managers_count} menaxherë · {b.employees_count} punonjës
                        </div>
                      </td>
                      <td data-label="Rrjete" className="num">{b.networks_count}</td>
                      <td data-label="Check-in sot" className="num">{b.checkins_today ?? 0}</td>
                      <td data-label="Gjendja">
                        <span className={`pill ${b.is_active ? 'approved' : 'rejected'}`}>
                          {b.is_active ? 'Aktiv' : 'Pezulluar'}
                        </span>
                      </td>
                      <td data-label="Krijuar">{dmy(b.created_at)}</td>
                      <td data-label="Veprime" className="wrap">
                        <div className="btn-row">
                          <button type="button" className="small" onClick={() => openUsers(b)}>
                            Përdoruesit
                          </button>
                          <button type="button" className="small" onClick={() => setEditing({ ...b })}>
                            Ndrysho
                          </button>
                          <button
                            type="button"
                            className={`small ${b.is_active ? '' : 'success'}`}
                            onClick={() => toggleActive(b)}
                          >
                            {b.is_active ? 'Pezullo' : 'Riaktivizo'}
                          </button>
                          <button type="button" className="small danger" onClick={() => remove(b)}>
                            Fshi
                          </button>
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        </>
      )}

      {/* -------------------------------------------------- biznes i ri --- */}
      {creating && (
        <Modal
          title="Biznes i ri"
          icon="🏢"
          subtitle="Krijohet biznesi dhe admini i tij i parë në një hap."
          onClose={() => setCreating(false)}
        >
          <form onSubmit={create}>
            <h3 style={{ marginBottom: '0.6rem' }}>Të dhënat e biznesit</h3>
            <div className="field">
              <label htmlFor="b-name">Emri i biznesit</label>
              <input
                id="b-name"
                value={form.name}
                placeholder="p.sh. Restorant Liburnia sh.p.k."
                onChange={(e) => setForm({ ...form, name: e.target.value })}
                required
              />
            </div>
            <div className="field-row">
              <div className="field">
                <label htmlFor="b-email">Email kontakti</label>
                <input
                  id="b-email"
                  type="email"
                  value={form.contact_email}
                  onChange={(e) => setForm({ ...form, contact_email: e.target.value })}
                />
              </div>
              <div className="field">
                <label htmlFor="b-phone">Telefoni</label>
                <input
                  id="b-phone"
                  value={form.contact_phone}
                  onChange={(e) => setForm({ ...form, contact_phone: e.target.value })}
                />
              </div>
            </div>
            <div className="field">
              <label htmlFor="b-addr">Adresa</label>
              <input
                id="b-addr"
                value={form.address}
                onChange={(e) => setForm({ ...form, address: e.target.value })}
              />
            </div>

            <h3 style={{ margin: '1.2rem 0 0.6rem' }}>Admini i parë i biznesit</h3>
            <p className="small muted" style={{ marginBottom: '0.7rem' }}>
              Ky person do të krijojë vetë menaxherët, punonjësit dhe orarin.
            </p>
            <div className="field">
              <label htmlFor="b-aname">Emri i adminit</label>
              <input
                id="b-aname"
                value={form.admin_name}
                onChange={(e) => setForm({ ...form, admin_name: e.target.value })}
                required
              />
            </div>
            <div className="field-row">
              <div className="field">
                <label htmlFor="b-aemail">Email i adminit</label>
                <input
                  id="b-aemail"
                  type="email"
                  value={form.admin_email}
                  onChange={(e) => setForm({ ...form, admin_email: e.target.value })}
                  required
                />
              </div>
              <div className="field">
                <label htmlFor="b-apass">Fjalëkalimi fillestar</label>
                <input
                  id="b-apass"
                  type="text"
                  value={form.admin_password}
                  minLength={6}
                  onChange={(e) => setForm({ ...form, admin_password: e.target.value })}
                  required
                />
              </div>
            </div>

            <div className="modal-actions">
              <button type="button" onClick={() => setCreating(false)} disabled={busy}>
                Anulo
              </button>
              <button type="submit" className="primary" disabled={busy}>
                {busy ? 'Duke krijuar…' : 'Krijo biznesin'}
              </button>
            </div>
          </form>
        </Modal>
      )}

      {/* ------------------------------------------------------ ndryshim --- */}
      {editing && (
        <Modal title={`Ndrysho ${editing.name}`} icon="✏️" onClose={() => setEditing(null)}>
          <form onSubmit={saveEdit}>
            <div className="field">
              <label htmlFor="e-name">Emri</label>
              <input
                id="e-name"
                value={editing.name}
                onChange={(e) => setEditing({ ...editing, name: e.target.value })}
                required
              />
            </div>
            <div className="field-row">
              <div className="field">
                <label htmlFor="e-email">Email kontakti</label>
                <input
                  id="e-email"
                  type="email"
                  value={editing.contact_email ?? ''}
                  onChange={(e) => setEditing({ ...editing, contact_email: e.target.value })}
                />
              </div>
              <div className="field">
                <label htmlFor="e-phone">Telefoni</label>
                <input
                  id="e-phone"
                  value={editing.contact_phone ?? ''}
                  onChange={(e) => setEditing({ ...editing, contact_phone: e.target.value })}
                />
              </div>
            </div>
            <div className="field">
              <label htmlFor="e-addr">Adresa</label>
              <input
                id="e-addr"
                value={editing.address ?? ''}
                onChange={(e) => setEditing({ ...editing, address: e.target.value })}
              />
            </div>
            <div className="field">
              <label htmlFor="e-notes">Shënime të brendshme</label>
              <textarea
                id="e-notes"
                value={editing.notes ?? ''}
                onChange={(e) => setEditing({ ...editing, notes: e.target.value })}
              />
            </div>
            <div className="field">
              <label className="check" htmlFor="e-active">
                <input
                  id="e-active"
                  type="checkbox"
                  checked={editing.is_active}
                  onChange={(e) => setEditing({ ...editing, is_active: e.target.checked })}
                />
                Aktiv (nëse hiqet, askush nga ky biznes nuk kyçet dot)
              </label>
            </div>
            <div className="modal-actions">
              <button type="button" onClick={() => setEditing(null)} disabled={busy}>
                Anulo
              </button>
              <button type="submit" className="primary" disabled={busy}>
                {busy ? 'Duke ruajtur…' : 'Ruaj'}
              </button>
            </div>
          </form>
        </Modal>
      )}

      {/* ----------------------------------------------------- përdoruesit --- */}
      {viewing && (
        <Modal
          title={`Përdoruesit — ${viewing.business.name}`}
          icon="👥"
          subtitle="Pamje vetëm për lexim, për mbështetje teknike."
          onClose={() => setViewing(null)}
        >
          <div className="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>Emri</th>
                  <th>Email</th>
                  <th>Roli</th>
                  <th>Menaxheri</th>
                </tr>
              </thead>
              <tbody>
                {viewing.users.map((u) => (
                  <tr key={u.id}>
                    <td data-label="Emri">{u.name}</td>
                    <td data-label="Email">{u.email}</td>
                    <td data-label="Roli">
                      <span className={`pill role-${u.role}`}>{u.role}</span>
                    </td>
                    <td data-label="Menaxheri">{u.manager_name ?? '—'}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          <div className="modal-actions">
            <button type="button" className="primary" onClick={() => setViewing(null)}>
              Mbyll
            </button>
          </div>
        </Modal>
      )}
    </>
  )
}
