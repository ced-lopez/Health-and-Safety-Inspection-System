export const APP_NAME = 'Barangay 178 Health & Safety'
export const APP_SUBTITLE = 'North Caloocan City'

export const NAV_ITEMS = [
  {
    title: 'Dashboard',
    href: '/dashboard',
    icon: 'LayoutDashboard',
    module: 'dashboard',
  },
  {
    title: 'Establishments',
    href: '/establishments',
    icon: 'Building2',
    module: 'establishments',
  },
  {
    title: 'Inspections',
    href: '/inspections',
    icon: 'ClipboardCheck',
    module: 'inspections',
  },
  {
    title: 'Violations',
    href: '/violations',
    icon: 'AlertTriangle',
    module: 'violations',
  },
  {
    title: 'Certifications',
    href: '/certifications',
    icon: 'Award',
    module: 'certifications',
  },
]

export const INSPECTION_STATUSES = [
  'Scheduled',
  'Ongoing',
  'Completed',
  'Cancelled',
]

export const COMPLIANCE_STATUSES = [
  'Compliant',
  'Non-Compliant',
  'Needs Correction',
]

export const VIOLATION_SEVERITIES = ['Minor', 'Moderate', 'Major']

export const VIOLATION_STATUSES = ['Open', 'Under Review', 'Resolved']
