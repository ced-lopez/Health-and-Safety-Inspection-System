import { Navigate, useLocation } from 'react-router-dom'

import { useAuth } from '@/context/AuthContext'
import { getHomePath } from '@/utils/permissions'

export function ProtectedRoute({ children, module }) {
  const { isAuthenticated, loading, canAccess, user } = useAuth()
  const location = useLocation()

  if (loading) {
    return (
      <div className="flex flex-1 items-center justify-center p-8">
        <p className="text-sm text-muted-foreground">Loading session...</p>
      </div>
    )
  }

  if (!isAuthenticated) {
    const residentPath = location.pathname.startsWith("/resident");
    return (
      <Navigate
        to={residentPath ? "/" : "/admin/login"}
        replace
        state={{ from: location }}
      />
    )
  }

  if (module && !canAccess(module)) {
    return <Navigate to={getHomePath(user?.role?.slug)} replace />
  }

  return children
}
