export const ROLE_PERMISSIONS = {
  administrator: [
    'dashboard',
    'establishments',
    'inspections',
    'violations',
    'certifications',
  ],
  health_officer: [
    'dashboard',
    'establishments',
    'inspections',
    'certifications',
  ],
  inspector: ['dashboard', 'establishments', 'inspections', 'violations'],
  staff: ['dashboard'],
}

export function canAccessModule(roleSlug, module) {
  if (!roleSlug) {
    return false
  }

  const permissions = ROLE_PERMISSIONS[roleSlug] ?? []

  return permissions.includes(module)
}

export function getAccessibleModules(roleSlug) {
  return ROLE_PERMISSIONS[roleSlug] ?? []
}
