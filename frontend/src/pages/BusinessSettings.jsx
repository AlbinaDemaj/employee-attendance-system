import { useCallback, useEffect, useState } from 'react'
import { api, errorMessage } from '../api'
import { Alert, Empty, Loading, Modal, useToast } from '../components/ui'

const DAYS = [
  { value: 1, label: 'E hënë' },
  { value: 2, label: 'E martë' },
  { value: 3, label: 'E mërkurë' },
  { value: 4, label: 'E enjte' },
  { value: 5, label: 'E premte' },
  { value: 6, label: 'E shtunë' },
  { value: 7, label: 'E diel' },
]

/** Admini i biznesit: orari, rregullat dhe rrjetet e lejuara për check-in. */
export default function BusinessSettings() {
  const toast = useToast()
  const [data, setData] = useState(null)
  const [form, setForm] = useState(null)
  const [applyToAll, setApplyToAll] = useState(false)
  const [error, setError] = useState(null)
  const [busy, setBusy] = useState(false)
  const [adding, setAdding] = useState(false)
  const [newNet, setNewNet] = useState({ label: '', ip_range: '' })

  const load = useCallback(() => {
    api
      .get('/business/settings')
      .then((res) => {
        setData(res.data)
        setForm(res.data.business)
      })
      .catch((err) => setError(errorMessage(err)))
  }, [])

  useEffect(() => {
    load()
  }, [load])

  function toggleDay(day) {
    const days = form.working_days.includes(day)
      ? form.working_days.filter((d) => d !== day)
      : [...form.working_days, day].sort((a, b) => a - b)
    setForm({ ...form, working_days: days })
  }

  async function save(e) {
    e.preventDefault()
    if (form.working_days.length === 0) {
      toast('Zgjidh të paktën një ditë pune.', 'error')
      return
    }
    setBusy(true)
    try {
      const { data: res } = await api.put('/business/settings', {
        name: form.name,
        contact_email: form.contact_email || null,
        contact_phone: form.contact_phone || null,
        address: form.address || null,
        default_start_time: form.default_start_time,
        default_end_time: form.default_end_time,
        working_days: form.working_days,
        late_grace_minutes: Number(form.late_grace_minutes),
        manager_alert_after_minutes: Number(form.manager_alert_after_minutes),
        auto_approve_sick_with_certificate: form.auto_approve_sick_with_certificate,
        require_network_for_checkin: form.require_network_for_checkin,
        apply_time_to_all: applyToAll,
      })
      toast(res.message, 'success')
      setApplyToAll(false)
      load()
    } catch (err) {
      toast(errorMessage(err), 'error')
    } finally {
      setBusy(false)
    }
  }

  async function addNetwork(e) {
    e.preventDefault()
    setBusy(true)
    try {
      const { data: res } = await api.post('/business/networks', newNet)
      toast(res.message, 'success')
      setAdding(false)
      setNewNet({ label: '', ip_range: '' })
      load()
    } catch (err) {
      toast(errorMessage(err), 'error')
    } finally {
      setBusy(false)
    }
  }

  async function toggleNetwork(n) {
    try {
      await api.put(`/business/networks/${n.id}`, { is_active: !n.is_active })
      load()
    } catch (err) {
      toast(errorMessage(err), 'error')
    }
  }

  async function removeNetwork(n) {
    if (!window.confirm(`Të fshihet rrjeti "${n.label}"?`)) return
    try {
      const { data: res } = await api.delete(`/business/networks/${n.id}`)
      toast(res.message, 'success')
      load()
    } catch (err) {
      toast(errorMessage(err), 'error')
    }
  }

  if (error) return <Alert kind="error">{error}</Alert>
  if (!data || !form) return <Loading />

  return (
    <>
      <div className="page-head">
        <div>
          <h1>Orari dhe rrjeti</h1>
          <p className="sub">Rregullat vlejnë për të gjithë punonjësit e biznesit tuaj.</p>
        </div>
      </div>

      <form onSubmit={save}>
        <div className="card">
          <div className="card-head">
            <h2>🕒 Orari i punës</h2>
          </div>

          <div className="field-row">
            <div className="field">
              <label htmlFor="s-start">Fillimi i punës</label>
              <input
                id="s-start"
                type="time"
                value={form.default_start_time}
                onChange={(e) => setForm({ ...form, default_start_time: e.target.value })}
              />
              <p className="help">Ora nga e cila fillon të numërohet vonesa.</p>
            </div>
            <div className="field">
              <label htmlFor="s-end">Mbarimi i punës</label>
              <input
                id="s-end"
                type="time"
                value={form.default_end_time}
                onChange={(e) => setForm({ ...form, default_end_time: e.target.value })}
              />
            </div>
          </div>

          <div className="field">
            <label>Ditët e punës</label>
            <div className="reason-list">
              {DAYS.map((d) => (
                <button
                  type="button"
                  key={d.value}
                  className={`reason-option ${form.working_days.includes(d.value) ? 'selected' : ''}`}
                  onClick={() => toggleDay(d.value)}
                  aria-pressed={form.working_days.includes(d.value)}
                >
                  <span className="emoji" aria-hidden="true">
                    {form.working_days.includes(d.value) ? '✅' : '⬜'}
                  </span>
                  {d.label}
                </button>
              ))}
            </div>
            <p className="help">Ditët jashtë kësaj liste nuk numërohen kurrë si mungesa.</p>
          </div>

          <div className="field">
            <label className="check" htmlFor="s-apply">
              <input
                id="s-apply"
                type="checkbox"
                checked={applyToAll}
                onChange={(e) => setApplyToAll(e.target.checked)}
              />
              Zbato orën e fillimit te të gjithë punonjësit ekzistues
            </label>
            <p className="help">
              Pa këtë, ora e re vlen vetëm për punonjësit e rinj; të tjerët ruajnë orarin e tyre
              individual.
            </p>
          </div>
        </div>

        <div className="card">
          <div className="card-head">
            <h2>⚙️ Rregullat e prezencës</h2>
          </div>

          <div className="field-row">
            <div className="field">
              <label htmlFor="s-grace">Tolerancë vonese (minuta)</label>
              <input
                id="s-grace"
                type="number"
                min={0}
                max={240}
                value={form.late_grace_minutes}
                onChange={(e) => setForm({ ...form, late_grace_minutes: e.target.value })}
              />
              <p className="help">0 = çdo minutë pas orës së fillimit numërohet vonesë.</p>
            </div>
            <div className="field">
              <label htmlFor="s-alert">Njofto menaxherin pas (minuta)</label>
              <input
                id="s-alert"
                type="number"
                min={0}
                max={480}
                value={form.manager_alert_after_minutes}
                onChange={(e) => setForm({ ...form, manager_alert_after_minutes: e.target.value })}
              />
              <p className="help">Sa minuta pas fillimit krijohet njoftimi “nuk ka bërë check-in”.</p>
            </div>
          </div>

          <div className="field">
            <label className="check" htmlFor="s-auto">
              <input
                id="s-auto"
                type="checkbox"
                checked={form.auto_approve_sick_with_certificate}
                onChange={(e) =>
                  setForm({ ...form, auto_approve_sick_with_certificate: e.target.checked })
                }
              />
              Aprovo automatikisht lejen mjekësore kur ka certifikatë të bashkëngjitur
            </label>
          </div>
        </div>

        <div className="card">
          <div className="card-head">
            <h2>📶 Check-in vetëm nga rrjeti i punës</h2>
          </div>

          <div className="alert info">
            <span className="ico" aria-hidden="true">ℹ️</span>
            <div>
              Shfletuesi nuk mund ta lexojë emrin e WiFi-t — asnjë browser nuk e lejon. Prandaj
              rrjeti i punës identifikohet me <strong>adresën IP</strong>: të gjitha pajisjet e
              lidhura me WiFi-n tuaj dalin në internet me të njëjtën IP publike.
              <br />
              <strong>Raportimi i arsyeve dhe kërkesat për leje nuk kufizohen kurrë</strong> — ato
              bëhen nga kudo.
            </div>
          </div>

          <div className="field">
            <label className="check" htmlFor="s-net">
              <input
                id="s-net"
                type="checkbox"
                checked={form.require_network_for_checkin}
                onChange={(e) => setForm({ ...form, require_network_for_checkin: e.target.checked })}
              />
              Lejo check-in/check-out vetëm nga rrjetet e listuara më poshtë
            </label>
          </div>
        </div>

        <div className="btn-row" style={{ marginBottom: '1.1rem' }}>
          <button type="submit" className="primary big" disabled={busy}>
            {busy ? 'Duke ruajtur…' : '💾 Ruaj cilësimet'}
          </button>
        </div>
      </form>

      <div className="card flush">
        <div className="card-head">
          <h2>Rrjetet e lejuara</h2>
          <div className="btn-row">
            <span className="pill">IP-ja juaj tani: {data.your_ip}</span>
            <button type="button" className="primary small" onClick={() => setAdding(true)}>
              ➕ Shto rrjet
            </button>
          </div>
        </div>

        <div className="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Emërtimi</th>
                <th>IP ose rang</th>
                <th>Gjendja</th>
                <th>Ju tani</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              {data.networks.length === 0 && (
                <tr>
                  <td colSpan={5} className="full">
                    <Empty icon="📶">
                      Asnjë rrjet i regjistruar. Shtoni IP-në e zyrës që check-in-i të kufizohet.
                    </Empty>
                  </td>
                </tr>
              )}
              {data.networks.map((n) => (
                <tr key={n.id}>
                  <td data-label="Emërtimi">
                    <strong>{n.label}</strong>
                  </td>
                  <td data-label="IP ose rang" className="num">{n.ip_range}</td>
                  <td data-label="Gjendja">
                    <span className={`pill ${n.is_active ? 'approved' : 'rejected'}`}>
                      {n.is_active ? 'Aktiv' : 'Joaktiv'}
                    </span>
                  </td>
                  <td data-label="Ju tani">
                    {n.matches_you ? (
                      <span className="pill approved">✅ jeni këtu</span>
                    ) : (
                      <span className="muted">—</span>
                    )}
                  </td>
                  <td data-label="Veprime">
                    <div className="btn-row">
                      <button type="button" className="small" onClick={() => toggleNetwork(n)}>
                        {n.is_active ? 'Çaktivizo' : 'Aktivizo'}
                      </button>
                      <button type="button" className="small danger" onClick={() => removeNetwork(n)}>
                        Fshi
                      </button>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>

        <div className="card-foot">
          <p className="small muted">
            Formatet e pranuara: një IP e vetme (<code>88.99.12.34</code>) ose një rang CIDR
            (<code>192.168.1.0/24</code>). IP-në publike të zyrës e gjeni duke hapur
            “what is my ip” në një pajisje të lidhur me atë WiFi.
          </p>
        </div>
      </div>

      {adding && (
        <Modal
          title="Shto rrjet të lejuar"
          icon="📶"
          subtitle="Vetëm nga këto adresa do të lejohet check-in-i."
          onClose={() => setAdding(false)}
        >
          <form onSubmit={addNetwork}>
            <div className="field">
              <label htmlFor="n-label">Emërtimi</label>
              <input
                id="n-label"
                value={newNet.label}
                placeholder="p.sh. WiFi — Zyra qendrore"
                onChange={(e) => setNewNet({ ...newNet, label: e.target.value })}
                required
              />
            </div>
            <div className="field">
              <label htmlFor="n-ip">IP ose rang CIDR</label>
              <input
                id="n-ip"
                value={newNet.ip_range}
                placeholder="88.99.12.34  ose  192.168.1.0/24"
                onChange={(e) => setNewNet({ ...newNet, ip_range: e.target.value })}
                required
              />
              <p className="help">
                Po shikoni nga <strong>{data.your_ip}</strong> —{' '}
                <button
                  type="button"
                  className="small"
                  onClick={() => setNewNet({ ...newNet, ip_range: data.your_ip })}
                >
                  përdore këtë
                </button>
              </p>
            </div>
            <div className="modal-actions">
              <button type="button" onClick={() => setAdding(false)} disabled={busy}>
                Anulo
              </button>
              <button type="submit" className="primary" disabled={busy}>
                {busy ? 'Duke shtuar…' : 'Shto'}
              </button>
            </div>
          </form>
        </Modal>
      )}
    </>
  )
}
