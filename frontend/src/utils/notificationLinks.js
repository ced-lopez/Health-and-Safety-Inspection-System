export function getNotificationHref(notification, roleSlug) {
  const type = notification?.data?.type ?? notification?.type;

  const routes = {
    resident: {
      application_submitted: "/resident/my-applications",
      inspector_assigned: "/resident/my-applications",
      inspection_completed: "/resident/my-applications",
      violation_notice: "/resident/follow-up",
      missing_requirements: "/resident/my-applications",
      follow_up_scheduled: "/resident/follow-up",
      clearance_approved: "/resident/clearance",
      clearance_expired: "/resident/clearance",
      renewal_reminder: "/resident/clearance",
      registration: "/resident/dashboard",
    },
    staff: {
      new_application_submitted: "/inspection-requests",
      inspection_submitted: "/inspections",
      violation_filed: "/violations",
    },
    inspector: {
      new_inspection_assignment: "/inspections",
      inspection_submitted: "/inspections",
    },
  };

  const roleKey =
    roleSlug === "resident"
      ? "resident"
      : roleSlug === "inspector"
        ? "inspector"
        : "staff";

  const map = routes[roleKey] ?? {};

  if (map[type]) return map[type];

  return roleSlug === "resident" ? "/resident/dashboard" : "/dashboard";
}
