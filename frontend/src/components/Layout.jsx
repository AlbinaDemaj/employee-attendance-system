import { useEffect, useState } from 'react'
import { NavLink, Outlet, useNavigate } from 'react-router-dom'
import { useAuth } from '../AuthContext'
import Notifications from './Notifications'
import Logo, { LogoMark } from './Logo'
import { IconClose, IconLogout, IconMenu, NAV_ICON } from './icons'
import { Avatar, ROLE_LABEL, longDate } from './ui'

/**
 * Navigimi grupohet sipas asaj që bën përdoruesi, jo sipas rolit: fillimisht
 * detyrat e tij si drejtues, në fund prezenca e tij personale. Kështu një
 * administrator i sheh të tetë faqet pa u ngatërruar.
 */
const MINE = {
  title: 'Prezenca ime',
  links: [
    { to: '/employee', label: 'Dita ime', icon: 'clock', end: true },
    { to: '/employee/history', label: 'Historiku im', icon: 'calendar' },
    { to: '/employee/leave', label: 'Lejet e mia', icon: 'leave' },
  ],
}

const TEAM = {
  title: 'Ekipi',
  links: [
    { to: '/manager', label: 'Tabela e ditës', icon: 'board', end: true },
    { to: '/manager/requests', label: 'Kërkesat për leje', icon: 'inbox' },
    { to: '/manager/report', label: 'Raporti mujor', icon: 'chart' },
  ],
}

const ADMIN = {
  title: 'Administrimi',
  links: [
    { to: '/admin', label: 'Punonjësit', icon: 'users', end: true },
    { to: '/admin/settings', label: 'Orari dhe rrjeti', icon: 'settings' },
  ],
}

const navByRole = {
  super_admin: [
    { title: 'Platforma', links: [{ to: '/super', label: 'Bizneset', icon: 'building', end: true }] },
  ],
  employee: [MINE],
  manager: [TEAM, MINE],
  admin: [ADMIN, TEAM, MINE],
}

export default function Layout({ notificationKey }) {
  const { user, logout } = useAuth()
  const navigate = useNavigate()
  const [menuOpen, setMenuOpen] = useState(false)

  const groups = navByRole[user.role] ?? []
  const isSuper = user.role === 'super_admin'

  useEffect(() => {
    if (!menuOpen) return
    const onKey = (e) => e.key === 'Escape' && setMenuOpen(false)
    window.addEventListener('keydown', onKey)
    return () => window.removeEventListener('keydown', onKey)
  }, [menuOpen])

  async function onLogout() {
    await logout()
    navigate('/login', { replace: true })
  }

  return (
    <div className="shell">
      <aside className={`sidebar ${menuOpen ? 'open' : ''}`}>
        <div className="sidebar-head">
          <Logo size={34} tag={isSuper ? 'Platforma' : null} />
          <button
            type="button"
            className="icon-btn on-mobile"
            onClick={() => setMenuOpen(false)}
            aria-label="Mbyll menynë"
          >
            <IconClose />
          </button>
        </div>

        <nav className="sidenav">
          {groups.map((g) => (
            <div key={g.title} className="nav-group">
              <p className="nav-title">{g.title}</p>
              {g.links.map((l) => {
                const Ico = NAV_ICON[l.icon]
                return (
                  // Në telefon shiriti është sirtar: mbyllet sapo hapet faqja.
                  <NavLink key={l.to} to={l.to} end={l.end} onClick={() => setMenuOpen(false)}>
                    <Ico />
                    {l.label}
                  </NavLink>
                )
              })}
            </div>
          ))}
        </nav>

        <div className="sidebar-foot">
          <span className="usercard">
            <Avatar name={user.name} className="lg" />
            <span className="who">
              <strong>{user.name}</strong>
              <span>{isSuper ? 'Pronar i platformës' : ROLE_LABEL[user.role]}</span>
            </span>
          </span>
          {!isSuper && user.business_name && (
            <p className="biz">{user.business_name}</p>
          )}
          <button type="button" className="logout" onClick={onLogout}>
            <IconLogout />
            Dil nga llogaria
          </button>
        </div>
      </aside>

      {menuOpen && <div className="scrim" onClick={() => setMenuOpen(false)} />}

      <div className="content">
        <header className="topbar">
          <button
            type="button"
            className="icon-btn on-mobile"
            onClick={() => setMenuOpen(true)}
            aria-label="Hap menynë"
          >
            <IconMenu />
          </button>

          <span className="topbar-brand on-mobile">
            <LogoMark size={28} />
            Prezenca
          </span>

          <span className="today on-desktop">{longDate(new Date().toISOString().slice(0, 10))}</span>

          <span className="spacer" />

          {!isSuper && <Notifications refreshKey={notificationKey} />}
        </header>

        <main className="page">
          <Outlet />
        </main>
      </div>
    </div>
  )
}
