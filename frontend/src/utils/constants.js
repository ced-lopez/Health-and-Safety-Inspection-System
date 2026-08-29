export const APP_NAME = "Barangay 178 Health & Safety Inspection";
export const APP_SUBTITLE = "North Caloocan City";

// The four establishment categories managed under the Establishment Module.
// The category field determines the applicable compliance checklist during
// inspection and connects an establishment to its inspections, violations,
// documents and clearances.
export const ESTABLISHMENT_CATEGORIES = [
  {
    value: "food_establishment",
    label: "Food Establishments",
    shortLabel: "Food",
    href: "/establishments/category/food-establishment",
    description: "Restaurants, eateries, and food handlers",
  },
  {
    value: "piggery",
    label: "Piggery",
    shortLabel: "Piggery",
    href: "/establishments/category/piggery",
    description: "Backyard and micro-scale piggery operations",
  },
  {
    value: "poultry",
    label: "Poultry",
    shortLabel: "Poultry",
    href: "/establishments/category/poultry",
    description: "Backyard and micro-scale poultry operations",
  },
  {
    value: "dog_raising_kennel",
    label: "Dog Raising / Kennel",
    shortLabel: "Dog Raising",
    href: "/establishments/category/dog-raising-kennel",
    description: "Household dog raising and commercial kennels",
  },
];

export const ESTABLISHMENT_CATEGORY_VALUES = ESTABLISHMENT_CATEGORIES.map(
  (category) => category.value,
);

// Route slug -> API category value mapping for the sidebar category links.
export const ESTABLISHMENT_CATEGORY_SLUGS = {
  "food-establishment": "food_establishment",
  piggery: "piggery",
  poultry: "poultry",
  "dog-raising-kennel": "dog_raising_kennel",
};

export function establishmentCategoryBySlug(slug) {
  const value = ESTABLISHMENT_CATEGORY_SLUGS[slug];

  return ESTABLISHMENT_CATEGORIES.find((category) => category.value === value);
}

export function establishmentCategoryByValue(value) {
  return ESTABLISHMENT_CATEGORIES.find((category) => category.value === value);
}

// Friendly labels for checklist categories (both the establishment categories
// and the retained cross-cutting core checklists).
export const CHECKLIST_CATEGORY_LABELS = {
  food_establishment: "Food Establishment",
  piggery: "Piggery",
  poultry: "Poultry",
  dog_raising_kennel: "Dog Raising / Kennel",
  health_sanitation: "Health & Sanitation",
  fire_safety: "Fire Safety",
  workplace_safety: "Workplace Safety",
};

export const STAFF_NAV_ITEMS = [
  {
    title: "Dashboard",
    href: "/dashboard",
    icon: "LayoutDashboard",
    module: "dashboard",
  },
  {
    title: "Establishments",
    href: "/establishments",
    icon: "Building2",
    module: "establishments",
    children: [
      {
        title: "All Establishments",
        href: "/establishments",
        icon: "Store",
        module: "establishments",
      },
      {
        title: "Food Establishments",
        href: "/establishments/category/food-establishment",
        icon: "Utensils",
        module: "establishments",
      },
      {
        title: "Piggery",
        href: "/establishments/category/piggery",
        icon: "PiggyBank",
        module: "establishments",
      },
      {
        title: "Poultry",
        href: "/establishments/category/poultry",
        icon: "Drumstick",
        module: "establishments",
      },
      {
        title: "Dog Raising / Kennel",
        href: "/establishments/category/dog-raising-kennel",
        icon: "PawPrint",
        module: "establishments",
      },
    ],
  },
  {
    title: "Inspection Requests",
    href: "/inspection-requests",
    icon: "ClipboardCheck",
    module: "inspection-requests",
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
    title: "Violations",
    href: "/violations",
    icon: "AlertTriangle",
    module: "violations",
  },
  {
    title: "Certificates & Clearances",
    href: "/certifications",
    icon: "Award",
    module: "certifications",
  },
  {
    title: "Documents / OCR",
    href: "/documents",
    icon: "FileText",
    module: "documents",
    children: [
      {
        title: "Documents",
        href: "/documents",
        icon: "FileText",
        module: "documents",
      },
      {
        title: "OCR Results",
        href: "/ocr-results",
        icon: "ScanText",
        module: "ocr-results",
      },
    ],
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
    children: [
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
    ],
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