import { Navigate } from 'react-router-dom'

import { useAuth } from '@/context/AuthContext'
import { getHomePath } from '@/utils/permissions'

export function GuestRoute({ children }) {
  const { isAuthenticated, loading, user } = useAuth()

  if (loading) {
    return (
      <div className="flex min-h-svh items-center justify-center">
        <p className="text-sm text-muted-foreground">Loading session...</p>
      </div>
    )
  }

  if (isAuthenticated) {
    return <Navigate to={getHomePath(user?.role?.slug)} replace />
  }

  return children
}
