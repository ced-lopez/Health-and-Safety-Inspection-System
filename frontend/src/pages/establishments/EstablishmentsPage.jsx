import { useEffect, useMemo, useState } from 'react'
import {
  Check,
  ChevronRight,
  Edit,
  MapPin,
  Phone,
  Plus,
  Search,
  Store,
  Trash2,
  X,
} from 'lucide-react'
import { keepPreviousData, useQuery, useQueryClient } from '@tanstack/react-query'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { toast } from 'sonner'

import { useAuth } from '@/context/AuthContext'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Skeleton } from '@/components/ui/skeleton'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import {
  ESTABLISHMENT_CATEGORIES,
  establishmentCategoryBySlug,
  establishmentCategoryByValue,
} from '@/utils/constants'
import {
  approveClaim,
  createEstablishment,
  deleteEstablishment,
  fetchEstablishments,
  fetchPendingClaims,
  rejectClaim,
  updateEstablishment,
} from '@/services/establishmentService'

const emptyForm = {
  name: '',
  category: 'food_establishment',
  business_type: '',
  owner_name: '',
  address: '',
  contact_number: '',
  email: '',
  registration_number: '',
  status: 'pending',
  latitude: '',
  longitude: '',
}

const statusLabels = {
  active: 'Active',
  inactive: 'Inactive',
  pending: 'Pending',
}

function statusVariant(status) {
  if (status === 'active') {
    return 'default'
  }

  if (status === 'pending') {
    return 'secondary'
  }

  return 'outline'
}

function deriveCompliance(establishment) {
  const openViolations = establishment.open_violations_count ?? 0

  if (openViolations > 0) {
    return { label: 'Non-Compliant', variant: 'destructive' }
  }

  if (establishment.last_inspection_date) {
    return { label: 'Compliant', variant: 'default' }
  }

  return { label: 'Not Yet Inspected', variant: 'outline' }
}

function formatDate(value) {
  if (!value) {
    return '—'
  }

  const date = new Date(`${value.slice(0, 10)}T00:00:00`)

  return date.toLocaleDateString(undefined, {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  })
}

export default function EstablishmentsPage() {
  const { user } = useAuth()
  const queryClient = useQueryClient()
  const navigate = useNavigate()
  const { category: categorySlug } = useParams()

  const activeCategoryValue = categorySlug
    ? establishmentCategoryBySlug(categorySlug)?.value
    : 'all'

  const [establishments, setEstablishments] = useState([])
  const [meta, setMeta] = useState({
    current_page: 1,
    last_page: 1,
    total: 0,
  })
  const [filters, setFilters] = useState({
    search: '',
    status: 'all',
    address: '',
    page: 1,
  })
  const [searchInput, setSearchInput] = useState('')
  const [submitting, setSubmitting] = useState(false)
  const [dialogOpen, setDialogOpen] = useState(false)
  const [editing, setEditing] = useState(null)
  const [form, setForm] = useState(emptyForm)
  const [errors, setErrors] = useState({})

  const roleSlug = user?.role?.slug
  const canWrite = ['administrator', 'barangay_staff'].includes(roleSlug)
  const canArchive = roleSlug === 'administrator'

  const claimsQuery = useQuery({
    queryKey: ['establishment-claims'],
    queryFn: async () => {
      const response = await fetchPendingClaims()
      return response.data ?? []
    },
    enabled: canWrite,
  })

  async function handleClaimReview(establishment, action) {
    try {
      if (action === 'approve') {
        await approveClaim(establishment.id)
        toast.success(`${establishment.name} claim approved`)
      } else {
        await rejectClaim(establishment.id)
        toast.success(`${establishment.name} claim rejected`)
      }
      await claimsQuery.refetch()
      await queryClient.invalidateQueries({ queryKey: ['establishments'] })
    } catch (error) {
      toast.error(error.response?.data?.message || 'Unable to review claim')
    }
  }

  const queryParams = useMemo(
    () => ({
      search: filters.search || undefined,
      status: filters.status,
      category:
        activeCategoryValue === 'all' ? undefined : activeCategoryValue,
      address: filters.address || undefined,
      page: filters.page,
      per_page: 10,
    }),
    [filters, activeCategoryValue],
  )

  const establishmentsQuery = useQuery({
    queryKey: ['establishments', queryParams],
    queryFn: async () => fetchEstablishments(queryParams),
    placeholderData: keepPreviousData,
  })

  useEffect(() => {
    if (establishmentsQuery.isError) {
      toast.error('Unable to load establishments')
      console.error(establishmentsQuery.error)
    }
  }, [establishmentsQuery.error, establishmentsQuery.isError])

  useEffect(() => {
    const response = establishmentsQuery.data

    if (!response) {
      return
    }

    const registry = response.data.establishments ?? []

    setEstablishments(registry.data ?? registry)
    setMeta(response.data.meta ?? { current_page: 1, last_page: 1, total: 0 })
  }, [establishmentsQuery.data])

  useEffect(() => {
    const timeout = window.setTimeout(() => {
      setFilters((current) => {
        if (current.search === searchInput) {
          return current
        }

        return {
          ...current,
          search: searchInput,
          page: 1,
        }
      })
    }, 150)

    return () => window.clearTimeout(timeout)
  }, [searchInput])

  const loading = establishmentsQuery.isLoading && establishments.length === 0

  function updateFilter(key, value) {
    setFilters((current) => ({
      ...current,
      [key]: value,
      page: key === 'page' ? value : 1,
    }))
  }

  const activeCategory = activeCategoryValue !== 'all'
    ? establishmentCategoryByValue(activeCategoryValue)
    : null

  function openCreateDialog() {
    setEditing(null)
    setForm({
      ...emptyForm,
      category: activeCategory?.value ?? 'food_establishment',
    })
    setErrors({})
    setDialogOpen(true)
  }

  function openEditDialog(establishment) {
    setEditing(establishment)
    setForm({
      name: establishment.name ?? '',
      category: establishment.category ?? 'food_establishment',
      business_type: establishment.business_type ?? '',
      owner_name: establishment.owner_name ?? '',
      address: establishment.address ?? '',
      contact_number: establishment.contact_number ?? '',
      email: establishment.email ?? '',
      registration_number: establishment.registration_number ?? '',
      status: establishment.status ?? 'pending',
      latitude: establishment.latitude ?? '',
      longitude: establishment.longitude ?? '',
    })
    setErrors({})
    setDialogOpen(true)
  }

  function updateForm(key, value) {
    setForm((current) => ({ ...current, [key]: value }))
    setErrors((current) => ({ ...current, [key]: undefined }))
  }

  function normalizePayload() {
    return {
      ...form,
      latitude: form.latitude === '' ? null : form.latitude,
      longitude: form.longitude === '' ? null : form.longitude,
    }
  }

  async function handleSubmit(event) {
    event.preventDefault()
    setSubmitting(true)
    setErrors({})

    try {
      if (editing) {
        await updateEstablishment(editing.id, normalizePayload())
        toast.success('Establishment updated')
      } else {
        await createEstablishment(normalizePayload())
        toast.success('Establishment registered')
      }

      setDialogOpen(false)
      await establishmentsQuery.refetch()
    } catch (error) {
      const validationErrors = error.response?.data?.errors

      if (validationErrors) {
        setErrors(validationErrors)
        toast.error('Please review the highlighted fields')
      } else {
        toast.error('Unable to save establishment')
      }

      console.error(error)
    } finally {
      setSubmitting(false)
    }
  }

  async function handleArchive(establishment) {
    const confirmed = window.confirm(
      `Archive ${establishment.name}? This removes it from active registry lists.`,
    )

    if (!confirmed) {
      return
    }

    try {
      await deleteEstablishment(establishment.id)
      toast.success('Establishment archived')
      await establishmentsQuery.refetch()
    } catch (error) {
      toast.error('Unable to archive establishment')
      console.error(error)
    }
  }

  return (
    <div className="space-y-6">
      <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <div className="flex items-center gap-2">
            <h2 className="text-2xl font-semibold tracking-tight">
              {activeCategory ? activeCategory.label : 'All Establishments'}
            </h2>
            {establishmentsQuery.isFetching && !loading && (
              <span className="text-xs text-muted-foreground">Refreshing...</span>
            )}
          </div>
          <p className="text-sm text-muted-foreground">
            Master records of every establishment, business, and operation
            handled and inspected in Barangay 178
          </p>
        </div>
        {canWrite && (
          <Button onClick={openCreateDialog}>
            <Plus className="size-4" />
            Add Establishment
          </Button>
        )}
      </div>

      {/* Category tabs — Establishments is the parent module and the four
          categories are the filters under it. */}
      <div className="flex flex-wrap gap-2">
        {[{ href: '/establishments', label: 'All Establishments', value: 'all' }, ...ESTABLISHMENT_CATEGORIES].map(
          (category) => {
            const isActive =
              (category.value === 'all' && activeCategoryValue === 'all') ||
              (category.value !== 'all' &&
                activeCategoryValue === category.value)

            return (
              <Button
                key={category.href}
                variant={isActive ? 'default' : 'outline'}
                size="sm"
                nativeButton={false}
                render={<Link to={category.href} />}
                className="h-8"
              >
                <Store className="size-3.5" />
                {category.label}
              </Button>
            )
          },
        )}
      </div>

      {canWrite && (claimsQuery.data ?? []).length > 0 && (
        <Card>
          <CardHeader>
            <CardTitle>Pending Claims</CardTitle>
            <CardDescription>
              Resident establishment link requests awaiting your review
            </CardDescription>
          </CardHeader>
          <CardContent>
            <div className="overflow-x-auto">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Business</TableHead>
                    <TableHead>Owner</TableHead>
                    <TableHead>Claimed By</TableHead>
                    <TableHead className="text-right">Actions</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {(claimsQuery.data ?? []).map((establishment) => (
                    <TableRow key={establishment.id}>
                      <TableCell>
                        <div className="font-medium">{establishment.name}</div>
                        <div className="text-xs text-muted-foreground">
                          {establishment.business_type} - {establishment.address}
                        </div>
                      </TableCell>
                      <TableCell>{establishment.owner_name}</TableCell>
                      <TableCell>{establishment.resident?.name ?? 'N/A'}</TableCell>
                      <TableCell>
                        <div className="flex justify-end gap-2">
                          <Button
                            variant="outline"
                            size="sm"
                            onClick={() => handleClaimReview(establishment, 'approve')}
                          >
                            <Check className="size-4" />
                            Approve
                          </Button>
                          <Button
                            variant="destructive"
                            size="sm"
                            onClick={() => handleClaimReview(establishment, 'reject')}
                          >
                            <X className="size-4" />
                            Reject
                          </Button>
                        </div>
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </div>
          </CardContent>
        </Card>
      )}

      <Card>
        <CardHeader>
          <CardTitle>Establishment Registry</CardTitle>
          <CardDescription>
            Search, filter, register, update, and open establishment profiles
          </CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="grid gap-3 lg:grid-cols-[1fr_1fr_180px]">
            <div className="relative">
              <Search className="absolute left-2.5 top-2 size-4 text-muted-foreground" />
              <Input
                className="pl-8"
                placeholder="Search name, owner, or registration no."
                value={searchInput}
                onChange={(event) => setSearchInput(event.target.value)}
              />
            </div>
            <div className="relative">
              <MapPin className="absolute left-2.5 top-2 size-4 text-muted-foreground" />
              <Input
                className="pl-8"
                placeholder="Filter by address / location"
                value={filters.address}
                onChange={(event) => updateFilter('address', event.target.value)}
              />
            </div>
            <select
              className="h-8 rounded-lg border border-input bg-background px-2.5 text-sm"
              value={filters.status}
              onChange={(event) => updateFilter('status', event.target.value)}
            >
              <option value="all">All statuses</option>
              <option value="active">Active</option>
              <option value="pending">Pending</option>
              <option value="inactive">Inactive</option>
            </select>
          </div>

          {loading ? (
            <div className="space-y-3">
              <Skeleton className="h-10 w-full" />
              <Skeleton className="h-10 w-full" />
              <Skeleton className="h-10 w-full" />
            </div>
          ) : establishments.length === 0 ? (
            <div className="py-10 text-center">
              <Store className="mx-auto size-8 text-muted-foreground/40" />
              <p className="mt-3 text-sm font-medium">
                No establishments found
              </p>
              <p className="mt-1 text-sm text-muted-foreground">
                {activeCategory
                  ? `There are no ${activeCategory.label.toLowerCase()} establishments matching your filters yet.`
                  : 'Try adjusting your search or add a new establishment.'}
              </p>
              {canWrite && (
                <Button variant="outline" size="sm" className="mt-4" onClick={openCreateDialog}>
                  <Plus className="size-4" />
                  Add Establishment
                </Button>
              )}
            </div>
          ) : (
            <div className="overflow-x-auto">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Establishment</TableHead>
                    <TableHead>Category</TableHead>
                    <TableHead>Owner / Operator</TableHead>
                    <TableHead>Address</TableHead>
                    <TableHead>Contact</TableHead>
                    <TableHead>Status</TableHead>
                    <TableHead>Last Inspection</TableHead>
                    <TableHead>Compliance</TableHead>
                    <TableHead className="text-right">Actions</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {establishments.map((establishment) => {
                    const compliance = deriveCompliance(establishment)
                    const category = establishmentCategoryByValue(
                      establishment.category,
                    )

                    return (
                      <TableRow
                        key={establishment.id}
                        className="group cursor-pointer"
                        onClick={() =>
                          navigate(`/establishments/${establishment.id}`)
                        }
                      >
                        <TableCell>
                          <div className="font-medium">
                            {establishment.name}
                          </div>
                          <div className="text-xs text-muted-foreground">
                            {establishment.registration_number ?? 'No registration'}
                          </div>
                        </TableCell>
                        <TableCell>
                          <Badge variant="secondary">
                            {category?.label ??
                              establishment.category_label ??
                              establishment.category}
                          </Badge>
                        </TableCell>
                        <TableCell>{establishment.owner_name}</TableCell>
                        <TableCell className="max-w-[220px]">
                          <span className="line-clamp-2 text-sm text-muted-foreground">
                            {establishment.address}
                          </span>
                        </TableCell>
                        <TableCell>
                          <span className="inline-flex items-center gap-1.5 text-sm">
                            <Phone className="size-3.5 text-muted-foreground" />
                            {establishment.contact_number ?? '—'}
                          </span>
                        </TableCell>
                        <TableCell>
                          <Badge variant={statusVariant(establishment.status)}>
                            {statusLabels[establishment.status] ?? establishment.status}
                          </Badge>
                        </TableCell>
                        <TableCell className="text-sm">
                          {formatDate(establishment.last_inspection_date)}
                        </TableCell>
                        <TableCell>
                          <Badge variant={compliance.variant}>
                            {compliance.label}
                          </Badge>
                        </TableCell>
                        <TableCell onClick={(event) => event.stopPropagation()}>
                          <div className="flex items-center justify-end gap-2">
                            <Button
                              variant="outline"
                              size="icon-sm"
                              aria-label={`View ${establishment.name}`}
                              onClick={() =>
                                navigate(`/establishments/${establishment.id}`)
                              }
                            >
                              <ChevronRight className="size-4" />
                            </Button>
                            {canWrite && (
                              <Button
                                variant="outline"
                                size="icon-sm"
                                aria-label={`Edit ${establishment.name}`}
                                onClick={() => openEditDialog(establishment)}
                              >
                                <Edit className="size-4" />
                              </Button>
                            )}
                            {canArchive && (
                              <Button
                                variant="destructive"
                                size="icon-sm"
                                aria-label={`Archive ${establishment.name}`}
                                onClick={() => handleArchive(establishment)}
                              >
                                <Trash2 className="size-4" />
                              </Button>
                            )}
                          </div>
                        </TableCell>
                      </TableRow>
                    )
                  })}
                </TableBody>
              </Table>
            </div>
          )}

          <div className="flex flex-col gap-3 border-t pt-4 text-sm text-muted-foreground sm:flex-row sm:items-center sm:justify-between">
            <span>{meta.total} establishments</span>
            <div className="flex items-center gap-2">
              <Button
                variant="outline"
                size="sm"
                disabled={loading || meta.current_page <= 1}
                onClick={() => updateFilter('page', meta.current_page - 1)}
              >
                Previous
              </Button>
              <span>
                Page {meta.current_page} of {meta.last_page}
              </span>
              <Button
                variant="outline"
                size="sm"
                disabled={loading || meta.current_page >= meta.last_page}
                onClick={() => updateFilter('page', meta.current_page + 1)}
              >
                Next
              </Button>
            </div>
          </div>
        </CardContent>
      </Card>

      <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
        <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
          <DialogHeader>
            <DialogTitle>
              {editing ? 'Edit Establishment' : 'Add Establishment'}
            </DialogTitle>
            <DialogDescription>
              Register the establishment under the appropriate category so the
              correct inspection checklist applies.
            </DialogDescription>
          </DialogHeader>

          <form className="space-y-4" onSubmit={handleSubmit}>
            <div className="grid gap-4 sm:grid-cols-2">
              <Field
                label="Establishment name"
                value={form.name}
                error={errors.name}
                onChange={(value) => updateForm('name', value)}
              />
              <div className="space-y-2">
                <Label>Category</Label>
                <select
                  className="h-8 w-full rounded-lg border border-input bg-background px-2.5 text-sm"
                  value={form.category}
                  onChange={(event) => updateForm('category', event.target.value)}
                >
                  {ESTABLISHMENT_CATEGORIES.map((category) => (
                    <option key={category.value} value={category.value}>
                      {category.label}
                    </option>
                  ))}
                </select>
                {errors.category && (
                  <p className="text-xs text-destructive">{errors.category[0]}</p>
                )}
              </div>
              <Field
                label="Business type"
                value={form.business_type}
                error={errors.business_type}
                required={false}
                onChange={(value) => updateForm('business_type', value)}
              />
              <Field
                label="Owner / operator name"
                value={form.owner_name}
                error={errors.owner_name}
                onChange={(value) => updateForm('owner_name', value)}
              />
              <Field
                label="Registration number"
                value={form.registration_number}
                error={errors.registration_number}
                onChange={(value) => updateForm('registration_number', value)}
              />
              <Field
                label="Contact number"
                value={form.contact_number}
                error={errors.contact_number}
                required={false}
                onChange={(value) => updateForm('contact_number', value)}
              />
              <Field
                label="Email"
                type="email"
                value={form.email}
                error={errors.email}
                required={false}
                onChange={(value) => updateForm('email', value)}
              />
              <div className="space-y-2">
                <Label>Status</Label>
                <select
                  className="h-8 w-full rounded-lg border border-input bg-background px-2.5 text-sm"
                  value={form.status}
                  onChange={(event) => updateForm('status', event.target.value)}
                >
                  <option value="active">Active</option>
                  <option value="pending">Pending</option>
                  <option value="inactive">Inactive</option>
                </select>
                {errors.status && (
                  <p className="text-xs text-destructive">{errors.status[0]}</p>
                )}
              </div>
              <Field
                label="Latitude"
                type="number"
                value={form.latitude}
                error={errors.latitude}
                required={false}
                step="any"
                onChange={(value) => updateForm('latitude', value)}
              />
              <Field
                label="Longitude"
                type="number"
                value={form.longitude}
                error={errors.longitude}
                required={false}
                step="any"
                onChange={(value) => updateForm('longitude', value)}
              />
            </div>

            <div className="space-y-2">
              <Label>Address</Label>
              <textarea
                className="min-h-20 w-full rounded-lg border border-input bg-background px-2.5 py-2 text-sm outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50"
                value={form.address}
                onChange={(event) => updateForm('address', event.target.value)}
                required
              />
              {errors.address && (
                <p className="text-xs text-destructive">{errors.address[0]}</p>
              )}
            </div>

            <DialogFooter>
              <Button
                type="button"
                variant="outline"
                onClick={() => setDialogOpen(false)}
              >
                Cancel
              </Button>
              <Button type="submit" disabled={submitting}>
                {submitting ? 'Saving...' : 'Save Establishment'}
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>
    </div>
  )
}

function Field({
  label,
  value,
  onChange,
  error,
  type = 'text',
  required = true,
  step,
}) {
  const id = label.toLowerCase().replaceAll(' ', '-')

  return (
    <div className="space-y-2">
      <Label htmlFor={id}>{label}</Label>
      <Input
        id={id}
        type={type}
        step={step}
        value={value}
        required={required}
        onChange={(event) => onChange(event.target.value)}
      />
      {error && <p className="text-xs text-destructive">{error[0]}</p>}
    </div>
  )
}