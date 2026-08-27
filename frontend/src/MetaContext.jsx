import { createContext, useContext, useEffect, useState } from 'react'
import { api } from './api'

/** Statuses, reason list and leave types all come from the backend catalogs. */
const MetaContext = createContext({ statuses: [], reasons: [], leave_types: [] })

export function MetaProvider({ children }) {
  const [meta, setMeta] = useState({ statuses: [], reasons: [], leave_types: [] })

  useEffect(() => {
    api
      .get('/meta')
      .then(({ data }) => setMeta(data))
      .catch(() => {})
  }, [])

  return <MetaContext.Provider value={meta}>{children}</MetaContext.Provider>
}

export function useMeta() {
  return useContext(MetaContext)
}

export function useStatusMap() {
  const { statuses } = useMeta()
  return Object.fromEntries(statuses.map((s) => [s.value, s]))
}
