import { useEffect, useMemo, useState } from 'react'
import { Plus, Save, Search, Trash2 } from 'lucide-react'
import { keepPreviousData, useQuery } from '@tanstack/react-query'
import { toast } from 'sonner'

import { useAuth } from '@/context/AuthContext'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Skeleton } from '@/components/ui/skeleton'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { CHECKLIST_CATEGORY_LABELS } from '@/utils/constants'
import api from '@/services/api'

const CATEGORIES = [
  { value: 'food_establishment', label: 'Food Establishment' },
  { value: 'piggery', label: 'Piggery' },
  { value: 'poultry', label: 'Poultry' },
  { value: 'dog_raising_kennel', label: 'Dog Raising / Kennel' },
]

export default function ChecklistsPage() {
  const { user } = useAuth()
  const canWrite = ['administrator', 'barangay_staff'].includes(user?.role?.slug)
  const canArchive = user?.role?.slug === 'administrator'

  const [checklists, setChecklists] = useState([])
  const [searchInput, setSearchInput] = useState('')
  const [dialogOpen, setDialogOpen] = useState(false)
  const [editing, setEditing] = useState(null)
  const [form, setForm] = useState({ name: '', description: '', category: 'food_establishment' })
  const [items, setItems] = useState([{ title: '', is_required: true }])
  const [submitting, setSubmitting] = useState(false)

  const queryParams = useMemo(() => ({}), [])

  const { data, isError, isLoading, refetch } = useQuery({
    queryKey: ['checklist-templates', queryParams],
    queryFn: async () => {
      const res = await api.get('/v1/checklist-templates')
      return res.data
    },
    placeholderData: keepPreviousData,
  })

  useEffect(() => {
    if (isError) toast.error('Unable to load checklists')
  }, [isError])

  useEffect(() => {
    if (!data) return
    const templates = data.data?.checklist_templates ?? data?.checklist_templates ?? []
    setChecklists(Array.isArray(templates) ? templates : [])
  }, [data])

  const filtered = useMemo(() =>
    (checklists ?? []).filter((c) =>
      c.name?.toLowerCase().includes(searchInput.toLowerCase())
    ), [checklists, searchInput])

  function addItem() { setItems((prev) => [...prev, { title: '', is_required: true }]) }

  function updateItem(idx, key, value) {
    setItems((prev) => prev.map((item, i) => i === idx ? { ...item, [key]: value } : item))
  }

  function removeItem(idx) {
    setItems((prev) => prev.filter((_, i) => i !== idx))
  }

  function openCreate() {
    setEditing(null)
    setForm({ name: '', description: '', category: 'food_establishment' })
    setItems([{ title: '', is_required: true }])
    setDialogOpen(true)
  }

  function openEdit(template) {
    setEditing(template)
    setForm({ name: template.name, description: template.description ?? '', category: template.category })
    setItems((template.items ?? [{ title: '', is_required: true }]).map((i) => ({
      id: i.id,
      title: i.title,
      description: i.description ?? '',
      is_required: i.is_required ?? true,
    })))
    setDialogOpen(true)
  }

  async function handleSubmit(event) {
    event.preventDefault()
    setSubmitting(true)
    try {
      const payload = { ...form, items: items.filter((i) => i.title.trim()) }
      if (editing) {
        await api.put(`/v1/checklist-templates/${editing.id}`, payload)
        toast.success('Checklist updated')
      } else {
        await api.post('/v1/checklist-templates', payload)
        toast.success('Checklist created')
      }
      setDialogOpen(false)
      await refetch()
    } catch (err) {
      toast.error(err.response?.data?.message ?? 'Unable to save checklist')
    } finally { setSubmitting(false) }
  }

  async function handleDelete(template) {
    if (!window.confirm(`Delete checklist "${template.name}"?`)) return
    try {
      await api.delete(`/v1/checklist-templates/${template.id}`)
      toast.success('Checklist deleted')
      await refetch()
    } catch { toast.error('Unable to delete checklist') }
  }

  return (
    <div className="space-y-6">
      <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h2 className="text-2xl font-semibold tracking-tight">Compliance Checklists</h2>
          <p className="text-sm text-muted-foreground">Manage inspection checklist templates per category</p>
        </div>
        {canWrite && (
          <Button onClick={openCreate}>
            <Plus className="size-4" />
            New Checklist
          </Button>
        )}
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Checklist Templates</CardTitle>
          <CardDescription>Dynamic checklists for each inspection category</CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="relative max-w-sm">
            <Search className="absolute left-2.5 top-2 size-4 text-muted-foreground" />
            <Input className="pl-8" placeholder="Search checklists..." value={searchInput} onChange={(e) => setSearchInput(e.target.value)} />
          </div>

          {isLoading ? (
            <div className="space-y-3"><Skeleton className="h-10 w-full" /><Skeleton className="h-10 w-full" /></div>
          ) : filtered.length === 0 ? (
            <p className="py-8 text-center text-sm text-muted-foreground">No checklists found.</p>
          ) : (
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Name</TableHead>
                  <TableHead>Category</TableHead>
                  <TableHead>Items</TableHead>
                  <TableHead className="text-right">Actions</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {filtered.map((cl) => (
                  <TableRow key={cl.id}>
                    <TableCell className="font-medium">{cl.name}</TableCell>
                    <TableCell>{CHECKLIST_CATEGORY_LABELS[cl.category] ?? cl.category?.replace(/_/g, ' ')}</TableCell>
                    <TableCell>{cl.items_count ?? cl.items?.length ?? 0}</TableCell>
                    <TableCell className="text-right">
                      <div className="flex justify-end gap-2">
                        <Button variant="outline" size="icon-sm" onClick={() => openEdit(cl)}>
                          <Save className="size-4" />
                        </Button>
                        {canArchive && (
                          <Button variant="destructive" size="icon-sm" onClick={() => handleDelete(cl)}>
                            <Trash2 className="size-4" />
                          </Button>
                        )}
                      </div>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          )}
        </CardContent>
      </Card>

      <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
        <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
          <DialogHeader>
            <DialogTitle>{editing ? 'Edit Checklist' : 'New Checklist'}</DialogTitle>
            <DialogDescription>Define inspection items for this category</DialogDescription>
          </DialogHeader>
          <form className="space-y-4" onSubmit={handleSubmit}>
            <div className="grid gap-4 sm:grid-cols-2">
              <div className="space-y-2">
                <Label>Checklist Name</Label>
                <Input value={form.name} onChange={(e) => setForm((p) => ({ ...p, name: e.target.value }))} required />
              </div>
              <div className="space-y-2">
                <Label>Category</Label>
                <select className="h-8 w-full rounded-lg border border-input bg-background px-2.5 text-sm" value={form.category} onChange={(e) => setForm((p) => ({ ...p, category: e.target.value }))}>
                  {CATEGORIES.map((c) => <option key={c.value} value={c.value}>{c.label}</option>)}
                </select>
              </div>
              <div className="sm:col-span-2 space-y-2">
                <Label>Description <span className="text-muted-foreground font-normal">(optional)</span></Label>
                <Input value={form.description} onChange={(e) => setForm((p) => ({ ...p, description: e.target.value }))} />
              </div>
            </div>

            <div className="space-y-3">
              <div className="flex items-center justify-between">
                <Label>Checklist Items</Label>
                <Button type="button" variant="outline" size="sm" onClick={addItem}>
                  <Plus className="size-3" />
                  Add Item
                </Button>
              </div>
              {items.map((item, idx) => (
                <div key={idx} className="flex items-start gap-2 rounded-lg border border-border p-3">
                  <div className="flex-1 space-y-2">
                    <Input value={item.title} onChange={(e) => updateItem(idx, 'title', e.target.value)} placeholder={`Item ${idx + 1}`} required />
                  </div>
                  <label className="flex items-center gap-1.5 text-xs shrink-0 mt-2">
                    <input type="checkbox" checked={item.is_required} onChange={(e) => updateItem(idx, 'is_required', e.target.checked)} />
                    Required
                  </label>
                  {items.length > 1 && (
                    <Button type="button" variant="ghost" size="icon-sm" className="mt-1 shrink-0" onClick={() => removeItem(idx)}>
                      <Trash2 className="size-3 text-destructive" />
                    </Button>
                  )}
                </div>
              ))}
            </div>

            <DialogFooter>
              <Button type="button" variant="outline" onClick={() => setDialogOpen(false)}>Cancel</Button>
              <Button type="submit" disabled={submitting}>{submitting ? 'Saving...' : 'Save Checklist'}</Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>
    </div>
  )
}
