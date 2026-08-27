import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useAuth, homeFor } from '../AuthContext'
import { errorMessage } from '../api'
import { Alert, Avatar } from '../components/ui'
import Logo, { LogoMark } from '../components/Logo'
import { IconBoard, IconClock, IconLeave } from '../components/icons'

const highlights = [
  { Icon: IconClock, title: 'Check-in me një klikim', text: 'Ora e turnit, vonesa dhe dalja — të gjitha në një ekran.' },
  { Icon: IconBoard, title: 'Tabela e ditës', text: 'Menaxheri sheh në çast kush është në punë dhe kush mungon.' },
  { Icon: IconLeave, title: 'Lejet pa letra', text: 'Kërkesa, arsyeja dhe vendimi ruhen bashkë me raportin mujor.' },
]

const demoAccounts = [
  {
    group: 'Platforma',
    items: [
      { email: 'super@demo.com', name: 'Pronari i Sistemit', role: 'Pronar i platformës', note: 'krijon llogaritë e bizneseve klientë' },
    ],
  },
  {
    group: 'Teknologji Prishtina sh.p.k.',
    items: [
      { email: 'admin@demo.com', name: 'Admin Aliu', role: 'Administrator', note: 'punonjësit, orari dhe rrjetet' },
      { email: 'manager@demo.com', name: 'Menaxher Krasniqi', role: 'Menaxher', note: 'tabela e ditës dhe aprovimet' },
      { email: 'ardit@demo.com', name: 'Ardit Berisha', role: 'Punonjës', note: 'pritet 08:00 · check-in i lejuar nga localhost' },
      { email: 'drita@demo.com', name: 'Drita Hoxha', role: 'Punonjës', note: 'pritet 09:00' },
    ],
  },
  {
    group: 'Market Dardania',
    items: [
      { email: 'admin2@demo.com', name: 'Lira Dema', role: 'Administrator', note: 'orar 07:00–15:00, punojnë edhe të shtunën' },
      { email: 'blerim@demo.com', name: 'Blerim Krasniqi', role: 'Punonjës', note: 'check-in i bllokuar — jashtë rrjetit të marketit' },
    ],
  },
]

export default function Login() {
  const { login } = useAuth()
  const navigate = useNavigate()
  const [email, setEmail] = useState('manager@demo.com')
  const [password, setPassword] = useState('password')
  const [error, setError] = useState(null)
  const [busy, setBusy] = useState(false)

  async function submit(e) {
    e.preventDefault()
    setBusy(true)
    setError(null)
    try {
      const user = await login(email, password)
      navigate(homeFor(user), { replace: true })
    } catch (err) {
      setError(errorMessage(err, 'Kyçja dështoi.'))
    } finally {
      setBusy(false)
    }
  }

  return (
    <div className="auth">
      {/* Paneli i majtë është vetëm prezantues — fshihet në telefon. */}
      <aside className="auth-brand">
        <Logo size={40} />

        <div className="auth-pitch">
          <h1>Prezenca e ekipit, e qartë çdo mëngjes.</h1>
          <p>
            Një vend i vetëm për orarin, mungesat dhe lejet — pa tabela Excel dhe
            pa pyetur njeri se kush ka ardhur.
          </p>
        </div>

        <ul className="auth-points">
          {highlights.map(({ Icon, title, text }) => (
            <li key={title}>
              <span className="pt-ico"><Icon size={18} /></span>
              <span>
                <strong>{title}</strong>
                {text}
              </span>
            </li>
          ))}
        </ul>
      </aside>

      <main className="auth-panel">
        <div className="auth-card">
          <div className="auth-head">
            <LogoMark size={44} className="on-mobile" />
            <h2>Mirë se erdhe</h2>
            <p className="muted small">Kyçu për të vazhduar te sistemi i prezencës.</p>
          </div>

          {error && <Alert kind="error">{error}</Alert>}

          <form onSubmit={submit}>
            <div className="field">
              <label htmlFor="email">Email</label>
              <input
                id="email"
                type="email"
                value={email}
                autoComplete="username"
                placeholder="emri@kompania.com"
                onChange={(e) => setEmail(e.target.value)}
                required
              />
            </div>
            <div className="field">
              <label htmlFor="password">Fjalëkalimi</label>
              <input
                id="password"
                type="password"
                value={password}
                autoComplete="current-password"
                placeholder="••••••••"
                onChange={(e) => setPassword(e.target.value)}
                required
              />
            </div>
            <button type="submit" className="primary big block" disabled={busy}>
              {busy ? 'Duke u kyçur…' : 'Kyçu'}
            </button>
          </form>

          {/* Llogaritë e demos rrinë të mbledhura, që forma të mbetet e pastër. */}
          <details className="demo-accounts">
            <summary>
              Llogari demo për provë
              <span className="muted tiny">fjalëkalimi: password</span>
            </summary>
            {demoAccounts.map((g) => (
              <div key={g.group} className="demo-group">
                <p className="demo-group-title">{g.group}</p>
                {g.items.map((a) => (
                  <button
                    key={a.email}
                    type="button"
                    className="demo-btn"
                    onClick={() => {
                      setEmail(a.email)
                      setPassword('password')
                      setError(null)
                    }}
                  >
                    <Avatar name={a.name} />
                    <span className="txt">
                      <strong>{a.role}</strong>
                      <span className="muted tiny">{a.email} · {a.note}</span>
                    </span>
                  </button>
                ))}
              </div>
            ))}
          </details>
        </div>
      </main>
    </div>
  )
}
