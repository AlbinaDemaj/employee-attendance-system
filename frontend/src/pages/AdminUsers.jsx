import { useCallback, useEffect, useState } from 'react'
import { api, errorMessage } from '../api'
import { Alert, Avatar, Empty, Loading, Modal, ROLE_LABEL, useToast } from '../components/ui'

const blank = {
  name: '',
  email: '',
  password: '',
  role: 'employee',
  department: '',
  expected_start_time: '08:00',
  manager_id: '',
  phone: '',
  is_active: true,
}

export default function AdminUsers() {
  const toast = useToast()
  const [data, setData] = useState(null)
  const [error, setError] = useState(null)
  const [editing, setEditing] = useState(null) // objekt user ose 'new'
  const [form, setForm] = useState(blank)
  const [busy, setBusy] = useState(false)

  const load = useCallback(() => {
    api
      .get('/admin/users')
      .then((res) => setData(res.data))
      .catch((err) => setError(errorMessage(err)))
  }, [])

  useEffect(() => {
    load()
  }, [load])

  function openNew() {
    setForm(blank)
    setEditing('new')
  }

  function openEdit(user) {
    setForm({
      name: user.name,
      email: user.email,
      password: '',
      role: user.role,
      department: user.department ?? '',
      expected_start_time: user.expected_start_time,
      manager_id: user.manager_id ?? '',
      phone: user.phone ?? '',
      is_active: user.is_active,
    })
    setEditing(user)
  }

  async function save(e) {
    e.preventDefault()
    setBusy(true)

    const payload = {
      ...form,
      manager_id: form.manager_id === '' ? null : Number(form.manager_id),
      department: form.department || null,
      phone: form.phone || null,
    }
    if (editing !== 'new' && !payload.password) delete payload.password

    try {
      const { data: res } =
        editing === 'new'
          ? await api.post('/admin/users', payload)
          : await api.put(`/admin/users/${editing.id}`, payload)
      toast(res.message, 'success')
      setEditing(null)
      load()
    } catch (err) {
      toast(errorMessage(err), 'error')
    } finally {
      setBusy(false)
    }
  }

  async function remove(user) {
    if (!window.confirm(`Të fshihet ${user.name}? Bashkë me të fshihet edhe historiku i prezencës.`)) return
    try {
      const { data: res } = await api.delete(`/admin/users/${user.id}`)
      toast(res.message, 'success')
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
          <h1>Punonjësit</h1>
          <p className="sub">Krijo llogari, cakto menaxherin dhe orën e pritur të fillimit.</p>
        </div>
        <button type="button" className="primary" onClick={openNew}>
          ➕ Shto punonjës
        </button>
      </div>

      {!data ? (
        <Loading />
      ) : (
        <div className="card flush">
          <div className="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>Emri</th>
                  <th>Email</th>
                  <th>Roli</th>
                  <th>Departamenti</th>
                  <th>Ora e pritur</th>
                  <th>Menaxheri</th>
                  <th>Telefoni</th>
                  <th>Aktiv</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                {data.users.length === 0 && (
                  <tr>
                    <td colSpan={9} className="full">
                      <Empty icon="👥">Asnjë përdorues.</Empty>
                    </td>
                  </tr>
                )}
                {data.users.map((u) => (
                  <tr key={u.id}>
                    <td data-label="Emri">
                      <span className="person">
                        <Avatar name={u.name} />
                        <span className="meta">
                          <strong>{u.name}</strong>
                        </span>
                      </span>
                    </td>
                    <td data-label="Email">{u.email}</td>
                    <td data-label="Roli">
                      <span className={`pill role-${u.role}`}>{ROLE_LABEL[u.role]}</span>
                    </td>
                    <td data-label="Departamenti">{u.department ?? '—'}</td>
                    <td data-label="Ora e pritur" className="num">{u.expected_start_time}</td>
                    <td data-label="Menaxheri">{u.manager_name ?? '—'}</td>
                    <td data-label="Telefoni">{u.phone ?? '—'}</td>
                    <td data-label="Aktiv">{u.is_active ? '✅' : '❌'}</td>
                    <td data-label="Veprime">
                      <div className="btn-row">
                        <button type="button" className="small" onClick={() => openEdit(u)}>
                          Ndrysho
                        </button>
                        <button type="button" className="small danger" onClick={() => remove(u)}>
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
      )}

      {editing && (
        <Modal
          title={editing === 'new' ? 'Shto punonjës' : `Ndrysho ${editing.name}`}
          icon={editing === 'new' ? '➕' : '✏️'}
          subtitle="Ora e pritur përcakton kur llogaritet vonesa për këtë person."
          onClose={() => setEditing(null)}
        >
          <form onSubmit={save}>
            <div className="field-row">
              <div className="field">
                <label htmlFor="u-name">Emri i plotë</label>
                <input
                  id="u-name"
                  value={form.name}
                  onChange={(e) => setForm({ ...form, name: e.target.value })}
                  required
                />
              </div>
              <div className="field">
                <label htmlFor="u-email">Email</label>
                <input
                  id="u-email"
                  type="email"
                  value={form.email}
                  onChange={(e) => setForm({ ...form, email: e.target.value })}
                  required
                />
              </div>
            </div>

            <div className="field-row">
              <div className="field">
                <label htmlFor="u-pass">
                  Fjalëkalimi{' '}
                  {editing !== 'new' && <span className="muted">(lëre bosh për ta ruajtur)</span>}
                </label>
                <input
                  id="u-pass"
                  type="password"
                  value={form.password}
                  onChange={(e) => setForm({ ...form, password: e.target.value })}
                  required={editing === 'new'}
                  minLength={editing === 'new' ? 6 : undefined}
                />
              </div>
              <div className="field">
                <label htmlFor="u-role">Roli</label>
                <select
                  id="u-role"
                  value={form.role}
                  onChange={(e) => setForm({ ...form, role: e.target.value })}
                >
                  <option value="employee">Punonjës</option>
                  <option value="manager">Menaxher</option>
                  <option value="admin">Administrator</option>
                </select>
              </div>
            </div>

            <div className="field-row">
              <div className="field">
                <label htmlFor="u-dept">Departamenti</label>
                <input
                  id="u-dept"
                  value={form.department}
                  placeholder="p.sh. Operacione"
                  onChange={(e) => setForm({ ...form, department: e.target.value })}
                />
              </div>
              <div className="field">
                <label htmlFor="u-start">Ora e pritur e fillimit</label>
                <input
                  id="u-start"
                  type="time"
                  value={form.expected_start_time}
                  onChange={(e) => setForm({ ...form, expected_start_time: e.target.value })}
                />
              </div>
            </div>

            <div className="field-row">
              <div className="field">
                <label htmlFor="u-mgr">Menaxheri</label>
                <select
                  id="u-mgr"
                  value={form.manager_id}
                  onChange={(e) => setForm({ ...form, manager_id: e.target.value })}
                >
                  <option value="">— pa menaxher —</option>
                  {data.managers
                    .filter((m) => editing === 'new' || m.id !== editing.id)
                    .map((m) => (
                      <option key={m.id} value={m.id}>
                        {m.name} ({ROLE_LABEL[m.role]})
                      </option>
                    ))}
                </select>
              </div>
              <div className="field">
                <label htmlFor="u-phone">Telefoni</label>
                <input
                  id="u-phone"
                  value={form.phone}
                  placeholder="+383 44 000 000"
                  onChange={(e) => setForm({ ...form, phone: e.target.value })}
                />
              </div>
            </div>

            <div className="field">
              <label className="check" htmlFor="u-active">
                <input
                  id="u-active"
                  type="checkbox"
                  checked={form.is_active}
                  onChange={(e) => setForm({ ...form, is_active: e.target.checked })}
                />
                Aktiv (mund të kyçet dhe shfaqet në tabelën e ditës)
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
    </>
  )
}
