export const APP_NAME = "Barangay 178 Health & Safety Inspection";
export const APP_SUBTITLE = "North Caloocan City";

export const STAFF_NAV_ITEMS = [
  {
    title: "Dashboard",
    href: "/dashboard",
    icon: "LayoutDashboard",
    module: "dashboard",
  },
  {
    title: "Inspection Requests",
    href: "/inspection-requests",
    icon: "ClipboardCheck",
    module: "inspection-requests",
  },
  {
    title: "Establishments",
    href: "/establishments",
    icon: "Building2",
    module: "establishments",
  },
  {
    title: "Inspections",
    href: "/inspections",
    icon: "ClipboardCheck",
    module: "inspections",
  },
  {
    title: "Scheduling Calendar",
    href: "/scheduling",
    icon: "CalendarDays",
    module: "scheduling",
  },
  {
    title: "Checklists",
    href: "/checklists",
    icon: "CheckSquare",
    module: "checklists",
  },
  {
    title: "Documents",
    href: "/documents",
    icon: "FileText",
    module: "documents",
  },
  {
    title: "Violations",
    href: "/violations",
    icon: "AlertTriangle",
    module: "violations",
  },
  {
    title: "Certifications",
    href: "/certifications",
    icon: "Award",
    module: "certifications",
  },
  {
    title: "Reports",
    href: "/reports",
    icon: "BarChart",
    module: "reports",
  },
  {
    title: "Users",
    href: "/users",
    icon: "Users",
    module: "users",
  },
  {
    title: "Audit Logs",
    href: "/audit-logs",
    icon: "History",
    module: "audit-logs",
  },
];

export const RESIDENT_NAV_ITEMS = [
  {
    title: "Dashboard",
    href: "/resident/dashboard",
    icon: "LayoutDashboard",
    module: "resident-dashboard",
  },
  {
    title: "Request Inspection",
    href: "/resident/request-inspection",
    icon: "ClipboardCheck",
    module: "resident-requests",
  },
  {
    title: "My Applications",
    href: "/resident/my-applications",
    icon: "FileText",
    module: "resident-requests",
  },
  {
    title: "Follow-Up",
    href: "/resident/follow-up",
    icon: "RefreshCw",
    module: "resident-follow-up",
  },
  {
    title: "Clearance",
    href: "/resident/clearance",
    icon: "Award",
    module: "resident-clearance",
  },
  {
    title: "Settings",
    href: "/settings",
    icon: "Settings",
    module: "settings",
  },
];

export const INSPECTION_STATUSES = [
  "Scheduled",
  "Ongoing",
  "Completed",
  "Cancelled",
];

export const COMPLIANCE_STATUSES = [
  "Compliant",
  "Non-Compliant",
  "Needs Correction",
];

export const VIOLATION_SEVERITIES = ["Minor", "Moderate", "Major"];

export const VIOLATION_STATUSES = ["Open", "Under Review", "Resolved"];
