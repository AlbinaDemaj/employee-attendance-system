import { useCallback, useState } from 'react'
import { BrowserRouter, Navigate, Route, Routes } from 'react-router-dom'
import { AuthProvider, homeFor, useAuth } from './AuthContext'
import { MetaProvider } from './MetaContext'
import { Loading, ToastProvider } from './components/ui'
import Layout from './components/Layout'
import Login from './pages/Login'
import EmployeeDashboard from './pages/EmployeeDashboard'
import MyHistory from './pages/MyHistory'
import MyLeaveRequests from './pages/MyLeaveRequests'
import ManagerDashboard from './pages/ManagerDashboard'
import ManagerRequests from './pages/ManagerRequests'
import MonthlyReport from './pages/MonthlyReport'
import AdminUsers from './pages/AdminUsers'
import BusinessSettings from './pages/BusinessSettings'
import SuperBusinesses from './pages/SuperBusinesses'

/** Bllokon një rrugë nëse përdoruesi nuk ka një nga rolet e lejuara. */
function Require({ roles, children }) {
  const { user, loading } = useAuth()
  if (loading) return <Loading />
  if (!user) return <Navigate to="/login" replace />
  if (roles && !roles.includes(user.role)) return <Navigate to={homeFor(user)} replace />
  return children
}

// Super-admini nuk ka biznes, ndaj nuk hyn në faqet e prezencës.
const STAFF = ['employee', 'manager', 'admin']
const SUPERVISOR = ['manager', 'admin']

function Shell() {
  const { user, loading } = useAuth()
  const [notificationKey, setNotificationKey] = useState(0)
  const onActivity = useCallback(() => setNotificationKey((k) => k + 1), [])

  if (loading) return <Loading text="Duke u nisur…" />

  return (
    <Routes>
      <Route path="/login" element={user ? <Navigate to={homeFor(user)} replace /> : <Login />} />

      <Route
        element={
          <Require>
            <Layout notificationKey={notificationKey} />
          </Require>
        }
      >
        <Route
          path="/employee"
          element={
            <Require roles={STAFF}>
              <EmployeeDashboard onActivity={onActivity} />
            </Require>
          }
        />
        <Route
          path="/employee/history"
          element={
            <Require roles={STAFF}>
              <MyHistory />
            </Require>
          }
        />
        <Route
          path="/employee/leave"
          element={
            <Require roles={STAFF}>
              <MyLeaveRequests onActivity={onActivity} />
            </Require>
          }
        />

        <Route
          path="/manager"
          element={
            <Require roles={SUPERVISOR}>
              <ManagerDashboard onActivity={onActivity} />
            </Require>
          }
        />
        <Route
          path="/manager/requests"
          element={
            <Require roles={SUPERVISOR}>
              <ManagerRequests onActivity={onActivity} />
            </Require>
          }
        />
        <Route
          path="/manager/report"
          element={
            <Require roles={SUPERVISOR}>
              <MonthlyReport />
            </Require>
          }
        />

        <Route
          path="/admin"
          element={
            <Require roles={['admin']}>
              <AdminUsers />
            </Require>
          }
        />
        <Route
          path="/admin/settings"
          element={
            <Require roles={['admin']}>
              <BusinessSettings />
            </Require>
          }
        />

        <Route
          path="/super"
          element={
            <Require roles={['super_admin']}>
              <SuperBusinesses />
            </Require>
          }
        />
      </Route>

      <Route path="*" element={<Navigate to={homeFor(user)} replace />} />
    </Routes>
  )
}

export default function App() {
  return (
    <BrowserRouter>
      <AuthProvider>
        <MetaProvider>
          <ToastProvider>
            <Shell />
          </ToastProvider>
        </MetaProvider>
      </AuthProvider>
    </BrowserRouter>
  )
}
