import { useEffect, useMemo, useState } from 'react'
import { Edit, Plus, Search, Trash2 } from 'lucide-react'
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
  createEstablishment,
  deleteEstablishment,
  fetchEstablishments,
  updateEstablishment,
} from '@/services/establishmentService'

const emptyForm = {
  name: '',
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

export default function EstablishmentsPage() {
  const { user } = useAuth()
  const [establishments, setEstablishments] = useState([])
  const [businessTypes, setBusinessTypes] = useState([])
  const [meta, setMeta] = useState({
    current_page: 1,
    last_page: 1,
    total: 0,
  })
  const [filters, setFilters] = useState({
    search: '',
    status: 'all',
    business_type: 'all',
    page: 1,
  })
  const [loading, setLoading] = useState(true)
  const [submitting, setSubmitting] = useState(false)
  const [dialogOpen, setDialogOpen] = useState(false)
  const [editing, setEditing] = useState(null)
  const [form, setForm] = useState(emptyForm)
  const [errors, setErrors] = useState({})

  const roleSlug = user?.role?.slug
  const canWrite = ['administrator', 'health_officer'].includes(roleSlug)
  const canArchive = roleSlug === 'administrator'

  const queryParams = useMemo(
    () => ({
      search: filters.search || undefined,
      status: filters.status,
      business_type: filters.business_type,
      page: filters.page,
      per_page: 10,
    }),
    [filters],
  )

  async function loadEstablishments() {
    setLoading(true)

    try {
      const response = await fetchEstablishments(queryParams)
      const registry = response.data.establishments ?? []

      setEstablishments(registry.data ?? registry)
      setBusinessTypes(response.data.business_types ?? [])
      setMeta(response.data.meta ?? { current_page: 1, last_page: 1, total: 0 })
    } catch (error) {
      toast.error('Unable to load establishments')
      console.error(error)
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    loadEstablishments()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [queryParams])

  function updateFilter(key, value) {
    setFilters((current) => ({
      ...current,
      [key]: value,
      page: key === 'page' ? value : 1,
    }))
  }

  function openCreateDialog() {
    setEditing(null)
    setForm(emptyForm)
    setErrors({})
    setDialogOpen(true)
  }

  function openEditDialog(establishment) {
    setEditing(establishment)
    setForm({
      name: establishment.name ?? '',
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
      await loadEstablishments()
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
      await loadEstablishments()
    } catch (error) {
      toast.error('Unable to archive establishment')
      console.error(error)
    }
  }

  return (
    <div className="space-y-6">
      <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h2 className="text-2xl font-semibold tracking-tight">Establishments</h2>
          <p className="text-sm text-muted-foreground">
            Manage registered business establishments in Barangay 178
          </p>
        </div>
        {canWrite && (
          <Button onClick={openCreateDialog}>
            <Plus className="size-4" />
            Add Establishment
          </Button>
        )}
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Establishment Registry</CardTitle>
          <CardDescription>
            Search, filter, register, update, and archive establishments
          </CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="grid gap-3 lg:grid-cols-[1fr_180px_220px]">
            <div className="relative">
              <Search className="absolute left-2.5 top-2 size-4 text-muted-foreground" />
              <Input
                className="pl-8"
                placeholder="Search name, owner, or registration no."
                value={filters.search}
                onChange={(event) => updateFilter('search', event.target.value)}
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
            <select
              className="h-8 rounded-lg border border-input bg-background px-2.5 text-sm"
              value={filters.business_type}
              onChange={(event) => updateFilter('business_type', event.target.value)}
            >
              <option value="all">All business types</option>
              {businessTypes.map((type) => (
                <option key={type} value={type}>
                  {type}
                </option>
              ))}
            </select>
          </div>

          {loading ? (
            <div className="space-y-3">
              <Skeleton className="h-10 w-full" />
              <Skeleton className="h-10 w-full" />
              <Skeleton className="h-10 w-full" />
            </div>
          ) : establishments.length === 0 ? (
            <p className="py-8 text-center text-sm text-muted-foreground">
              No establishments found.
            </p>
          ) : (
            <div className="overflow-x-auto">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Business</TableHead>
                    <TableHead>Owner</TableHead>
                    <TableHead>Registration</TableHead>
                    <TableHead>Status</TableHead>
                    <TableHead className="text-right">Actions</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {establishments.map((establishment) => (
                    <TableRow key={establishment.id}>
                      <TableCell>
                        <div className="font-medium">{establishment.name}</div>
                        <div className="text-xs text-muted-foreground">
                          {establishment.business_type} - {establishment.address}
                        </div>
                      </TableCell>
                      <TableCell>{establishment.owner_name}</TableCell>
                      <TableCell>{establishment.registration_number}</TableCell>
                      <TableCell>
                        <Badge variant={statusVariant(establishment.status)}>
                          {statusLabels[establishment.status] ?? establishment.status}
                        </Badge>
                      </TableCell>
                      <TableCell>
                        <div className="flex justify-end gap-2">
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
                  ))}
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
              Keep registry details accurate for inspection scheduling and reporting.
            </DialogDescription>
          </DialogHeader>

          <form className="space-y-4" onSubmit={handleSubmit}>
            <div className="grid gap-4 sm:grid-cols-2">
              <Field
                label="Business name"
                value={form.name}
                error={errors.name}
                onChange={(value) => updateForm('name', value)}
              />
              <Field
                label="Business type"
                value={form.business_type}
                error={errors.business_type}
                onChange={(value) => updateForm('business_type', value)}
              />
              <Field
                label="Owner name"
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
