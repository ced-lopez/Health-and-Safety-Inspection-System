import { Navigate, Route, Routes } from "react-router-dom";

import { AppLayout } from "@/components/layout/AppLayout";
import { useAuth } from "@/context/AuthContext";
import LoginPage from "@/pages/auth/LoginPage";
import ForgotPasswordPage from "@/pages/auth/ForgotPasswordPage";
import RegisterPage from "@/pages/auth/RegisterPage";
import ResetPasswordPage from "@/pages/auth/ResetPasswordPage";
import VerifyPage from "@/pages/auth/VerifyPage";
import LandingPage from "@/pages/landing/LandingPage";
import VerifyCodePage from "@/pages/public/VerifyCodePage";
import CertificationsPage from "@/pages/certifications/CertificationsPage";
import UsersPage from "@/pages/users/UsersPage";
import DashboardPage from "@/pages/dashboard/DashboardPage";
import InspectorDashboardPage from "@/pages/inspector/InspectorDashboardPage";
import EstablishmentsPage from "@/pages/establishments/EstablishmentsPage";
import EstablishmentProfilePage from "@/pages/establishments/EstablishmentProfilePage";
import InspectionsPage from "@/pages/inspections/InspectionsPage";
import ViolationsPage from "@/pages/violations/ViolationsPage";
import InspectionRequestsPage from "@/pages/inspection-requests/InspectionRequestsPage";
import PaymentsPage from "@/pages/payments/PaymentsPage";
import CalendarPage from "@/pages/scheduling/CalendarPage";
import ChecklistsPage from "@/pages/checklists/ChecklistsPage";
import DocumentsPage from "@/pages/documents/DocumentsPage";
import OcrResultsPage from "@/pages/ocr-results/OcrResultsPage";
import OcrResultDetailPage from "@/pages/ocr-results/OcrResultDetailPage";
import ReportsPage from "@/pages/reports/ReportsPage";
import AuditLogsPage from "@/pages/audit-logs/AuditLogsPage";
import ResidentDashboardPage from "@/pages/resident/DashboardPage";
import RequestInspectionPage from "@/pages/resident/RequestInspectionPage";
import MyApplicationsPage from "@/pages/resident/MyApplicationsPage";
import FollowUpPage from "@/pages/resident/FollowUpPage";
import ClearancePage from "@/pages/resident/ClearancePage";
import NotificationsPage from "@/pages/notifications/NotificationsPage";
import SettingsPage from "@/pages/settings/SettingsPage";
import { GuestRoute } from "@/routes/GuestRoute";
import { ProtectedRoute } from "@/routes/ProtectedRoute";

function RoleAwareDashboard() {
  const { user } = useAuth();

  return user?.role?.slug === "inspector" ? <InspectorDashboardPage /> : <DashboardPage />;
}

export function AppRoutes() {
  return (
    <Routes>
      <Route index element={<LandingPage />} />

      <Route
        path="/verify/:code"
        element={<VerifyCodePage />}
      />

      <Route
        path="/login"
        element={
          <GuestRoute>
            <LoginPage />
          </GuestRoute>
        }
      />

      <Route
        path="/forgot-password"
        element={
          <GuestRoute>
            <ForgotPasswordPage />
          </GuestRoute>
        }
      />

      <Route
        path="/reset-password"
        element={
          <GuestRoute>
            <ResetPasswordPage />
          </GuestRoute>
        }
      />

      <Route
        path="/admin/login"
        element={<Navigate to="/login" replace />}
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
        path="/register/verify"
        element={
          <GuestRoute>
            <VerifyPage />
          </GuestRoute>
        }
      />

      <Route
        path="/resident/login"
        element={<Navigate to="/login" replace />}
      />

      <Route
        element={
          <ProtectedRoute>
            <AppLayout />
          </ProtectedRoute>
        }
      >
        <Route path="dashboard" element={<ProtectedRoute module="dashboard"><RoleAwareDashboard /></ProtectedRoute>} />
        <Route path="establishments" element={<ProtectedRoute module="establishments"><EstablishmentsPage /></ProtectedRoute>} />
        <Route path="establishments/category/:category" element={<ProtectedRoute module="establishments"><EstablishmentsPage /></ProtectedRoute>} />
        <Route path="establishments/:id" element={<ProtectedRoute module="establishments"><EstablishmentProfilePage /></ProtectedRoute>} />
        <Route path="inspections" element={<ProtectedRoute module="inspections"><InspectionsPage /></ProtectedRoute>} />
        <Route path="scheduling" element={<ProtectedRoute module="scheduling"><CalendarPage /></ProtectedRoute>} />
        <Route path="violations" element={<ProtectedRoute module="violations"><ViolationsPage /></ProtectedRoute>} />
        <Route path="certifications" element={<ProtectedRoute module="certifications"><CertificationsPage /></ProtectedRoute>} />
        <Route path="users" element={<ProtectedRoute module="users"><UsersPage /></ProtectedRoute>} />

        <Route path="inspection-requests" element={<ProtectedRoute module="inspection-requests"><InspectionRequestsPage /></ProtectedRoute>} />
        <Route path="payments" element={<ProtectedRoute module="payments"><PaymentsPage /></ProtectedRoute>} />
        <Route path="checklists" element={<ProtectedRoute module="checklists"><ChecklistsPage /></ProtectedRoute>} />
        <Route path="documents" element={<ProtectedRoute module="documents"><DocumentsPage /></ProtectedRoute>} />
        <Route path="ocr-results" element={<ProtectedRoute module="ocr-results"><OcrResultsPage /></ProtectedRoute>} />
        <Route path="ocr-results/:id" element={<ProtectedRoute module="ocr-results"><OcrResultDetailPage /></ProtectedRoute>} />
        <Route path="reports" element={<ProtectedRoute module="reports"><ReportsPage /></ProtectedRoute>} />
        <Route path="audit-logs" element={<ProtectedRoute module="audit-logs"><AuditLogsPage /></ProtectedRoute>} />

        {/* Resident Portal Routes */}
        <Route path="resident/dashboard" element={<ProtectedRoute module="resident-dashboard"><ResidentDashboardPage /></ProtectedRoute>} />
        <Route path="resident/request-inspection" element={<ProtectedRoute module="resident-requests"><RequestInspectionPage /></ProtectedRoute>} />
        <Route path="resident/my-applications" element={<ProtectedRoute module="resident-requests"><MyApplicationsPage /></ProtectedRoute>} />
        <Route path="resident/follow-up" element={<ProtectedRoute module="resident-follow-up"><FollowUpPage /></ProtectedRoute>} />
        <Route path="resident/clearance" element={<ProtectedRoute module="resident-clearance"><ClearancePage /></ProtectedRoute>} />
        <Route path="resident/notifications" element={<ProtectedRoute module="resident-notifications"><NotificationsPage /></ProtectedRoute>} />
        <Route path="notifications" element={<ProtectedRoute><NotificationsPage /></ProtectedRoute>} />
        <Route path="settings" element={<ProtectedRoute><SettingsPage /></ProtectedRoute>} />
      </Route>

      <Route path="*" element={<Navigate to="/" replace />} />
    </Routes>
  );
}
