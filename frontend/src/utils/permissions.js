export const ROLE_PERMISSIONS = {
  administrator: [
    "dashboard",
    "establishments",
    "inspections",
    "violations",
    "certifications",
    "users",
    "inspection-requests",
    "documents",
    "ocr-results",
    "reports",
    "audit-logs",
    "checklists",
    "scheduling",
    "settings",
  ],
  barangay_staff: [
    "dashboard",
    "establishments",
    "inspections",
    "certifications",
    "inspection-requests",
    "documents",
    "ocr-results",
    "reports",
    "checklists",
    "scheduling",
    "audit-logs",
    "settings",
  ],
  inspector: ["dashboard", "establishments", "inspections", "violations", "settings"],
  resident: [
    "resident-dashboard",
    "resident-requests",
    "resident-follow-up",
    "resident-clearance",
    "resident-notifications",
    "settings",
  ],
};

export function canAccessModule(roleSlug, module) {
  if (!roleSlug) {
    return false;
  }

  const permissions = ROLE_PERMISSIONS[roleSlug] ?? [];

  return permissions.includes(module);
}

export function getAccessibleModules(roleSlug) {
  return ROLE_PERMISSIONS[roleSlug] ?? [];
}

export function getHomePath(roleSlug) {
  return roleSlug === "resident" ? "/resident/dashboard" : "/dashboard";
}
