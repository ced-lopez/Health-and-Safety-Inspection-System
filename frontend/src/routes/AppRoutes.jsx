import { Navigate, Route, Routes } from 'react-router-dom'

import { AppLayout } from '@/components/layout/AppLayout'
import LoginPage from '@/pages/auth/LoginPage'
import RegisterPage from '@/pages/auth/RegisterPage'
import CertificationsPage from '@/pages/certifications/CertificationsPage'
import DashboardPage from '@/pages/dashboard/DashboardPage'
import EstablishmentsPage from '@/pages/establishments/EstablishmentsPage'
import InspectionsPage from '@/pages/inspections/InspectionsPage'
import ViolationsPage from '@/pages/violations/ViolationsPage'
import { GuestRoute } from '@/routes/GuestRoute'
import { ProtectedRoute } from '@/routes/ProtectedRoute'

export function AppRoutes() {
  return (
    <Routes>
      <Route
        path="/login"
        element={
          <GuestRoute>
            <LoginPage />
          </GuestRoute>
        }
      />
      <Route
        path="/register"
        element={
          <GuestRoute>
            <RegisterPage />
          </GuestRoute>
        }
      />

      <Route
        element={
          <ProtectedRoute>
            <AppLayout />
          </ProtectedRoute>
        }
      >
        <Route index element={<Navigate to="/dashboard" replace />} />
        <Route
          path="dashboard"
          element={
            <ProtectedRoute module="dashboard">
              <DashboardPage />
            </ProtectedRoute>
          }
        />
        <Route
          path="establishments"
          element={
            <ProtectedRoute module="establishments">
              <EstablishmentsPage />
            </ProtectedRoute>
          }
        />
        <Route
          path="inspections"
          element={
            <ProtectedRoute module="inspections">
              <InspectionsPage />
            </ProtectedRoute>
          }
        />
        <Route
          path="violations"
          element={
            <ProtectedRoute module="violations">
              <ViolationsPage />
            </ProtectedRoute>
          }
        />
        <Route
          path="certifications"
          element={
            <ProtectedRoute module="certifications">
              <CertificationsPage />
            </ProtectedRoute>
          }
        />
      </Route>

      <Route path="*" element={<Navigate to="/dashboard" replace />} />
    </Routes>
  )
}
