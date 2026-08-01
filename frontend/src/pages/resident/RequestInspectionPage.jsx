import { useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { ArrowLeft, ArrowRight, Check, FileUp, Send, ShieldAlert, Store } from 'lucide-react'
import { keepPreviousData, useQuery } from '@tanstack/react-query'
import { toast } from 'sonner'

import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Skeleton } from '@/components/ui/skeleton'
import { cn } from '@/lib/utils'
import {
  createInspectionRequest,
  fetchDocumentRequirements,
  fetchInspectionRequestOptions,
  uploadRequestDocumentType,
} from '@/services/inspectionRequestService'

const ANIMAL_CATEGORIES = ['piggery', 'poultry']

const emptyForm = {
  inspection_category_id: '',
  application_type_id: '',
  sub_path: '',
  declared_animal_count: '',
  applicant_name: '',
  applicant_age: '',
  applicant_address: '',
  contact_number: '',
  email: '',
  business_name: '',
  remarks: '',
}

const steps = [
  { key: 'category', label: 'Category' },
  { key: 'scale', label: 'Type / Scale' },
  { key: 'application_type', label: 'Application Type' },
  { key: 'documents', label: 'Documents' },
  { key: 'applicant', label: 'Applicant Info' },
  { key: 'review', label: 'Review' },
]

export default function RequestInspectionPage() {
  const navigate = useNavigate()
  const [step, setStep] = useState(0)
  const [form, setForm] = useState(emptyForm)
  const [files, setFiles] = useState({})
  const [submitting, setSubmitting] = useState(false)
  const [errors, setErrors] = useState({})
  const [blocked, setBlocked] = useState(false)

  const { data: optionsData, isError: optionsError, isLoading: optionsLoading } = useQuery({
    queryKey: ['inspection-request-options'],
    queryFn: fetchInspectionRequestOptions,
    placeholderData: keepPreviousData,
  })

  const categories = optionsData?.data?.categories ?? []
  const applicationTypes = optionsData?.data?.application_types ?? []

  const selectedCategory = categories.find((c) => String(c.id) === String(form.inspection_category_id))
  const selectedAppType = applicationTypes.find((t) => String(t.id) === String(form.application_type_id))
  const requiresBusinessDetails = selectedCategory?.requires_business_details ?? false
  const isAnimalCategory = ANIMAL_CATEGORIES.includes(selectedCategory?.slug)
  const isDogCategory = selectedCategory?.slug === 'animal_raising_dogs'
  const needsScaleStep = Boolean(selectedCategory) && (isAnimalCategory || isDogCategory)

  const requirementsQuery = useQuery({
    queryKey: [
      'document-requirements',
      form.inspection_category_id,
      form.sub_path,
      form.application_type_id,
    ],
    queryFn: () =>
      fetchDocumentRequirements({
        inspection_category_id: form.inspection_category_id,
        application_type_id: form.application_type_id,
        sub_path: form.sub_path || undefined,
      }),
    enabled: Boolean(form.inspection_category_id && form.application_type_id),
  })

  useEffect(() => {
    setBlocked(false)
  }, [form.inspection_category_id])

  useEffect(() => {
    if (isDogCategory && form.sub_path === 'commercial_kennel') {
      setForm((prev) => ({ ...prev, declared_animal_count: '' }))
    }
  }, [form.sub_path, isDogCategory])

  function updateForm(key, value) {
    setForm((prev) => ({ ...prev, [key]: value }))
    setErrors((prev) => ({ ...prev, [key]: undefined }))
  }

  function handleCategorySelect(category) {
    setForm((prev) => ({
      ...prev,
      inspection_category_id: String(category.id),
      sub_path: '',
      declared_animal_count: '',
    }))
    setBlocked(false)
    setErrors({})
  }

  function handleScaleSelect(subPath) {
    setForm((prev) => ({ ...prev, sub_path: subPath, declared_animal_count: '' }))

    // Piggery/Poultry: commercial scale is blocked instantly.
    if (isAnimalCategory && subPath === 'commercial') {
      setBlocked(true)
      return
    }

    setBlocked(false)
    goNext()
  }

  function goNext() {
    setStep((prev) => Math.min(prev + 1, steps.length - 1))
  }

  function goBack() {
    if (step === 0) {
      navigate('/resident/dashboard')
      return
    }
    setStep((prev) => prev - 1)
  }

  function handleStepNext() {
    setErrors({})

    if (step === 0) {
      if (!form.inspection_category_id) {
        setErrors({ inspection_category_id: 'Please select an inspection category.' })
        return
      }

      if (needsScaleStep) {
        setStep(1)
        return
      }

      setStep(2)
      return
    }

    if (step === 1) {
      if (!form.sub_path) {
        setErrors({ sub_path: 'Please declare the type or scale of your operation.' })
        return
      }

      if (isAnimalCategory && form.sub_path === 'backyard_micro_scale') {
        const count = Number(form.declared_animal_count)
        if (!count || count < 2 || count > 5) {
          setErrors({ declared_animal_count: 'Backyard micro-scale requires 2–5 animals.' })
          return
        }
      }

      setStep(2)
      return
    }

    if (step === 2) {
      if (!form.application_type_id) {
        setErrors({ application_type_id: 'Please select an application type.' })
        return
      }
      setStep(3)
      return
    }

    if (step === 3) {
      const requiredTypes = requirements.filter((r) => r.is_required).map((r) => r.document_type)
      const missing = requiredTypes.filter((t) => !files[t])
      if (missing.length > 0) {
        toast.error('All required documents must be uploaded before continuing.')
        return
      }
      setStep(4)
      return
    }

    if (step === 4) {
      const required = ['applicant_name', 'applicant_address', 'contact_number', 'email']
      const missing = required.filter((k) => !form[k]?.trim())
      if (missing.length > 0) {
        setErrors({
          applicant_name: 'Required',
          applicant_address: 'Required',
          contact_number: 'Required',
          email: 'Required',
        })
        toast.error('Please complete all required applicant fields.')
        return
      }
      setStep(5)
      return
    }
  }

  const requirements = requirementsQuery.data?.data?.requirements ?? []

  async function handleSubmit() {
    setSubmitting(true)
    setErrors({})

    try {
      const payload = {
        inspection_category_id: Number(form.inspection_category_id),
        application_type_id: Number(form.application_type_id),
        sub_path: form.sub_path || null,
        declared_animal_count: form.declared_animal_count ? Number(form.declared_animal_count) : null,
        applicant_name: form.applicant_name,
        applicant_age: form.applicant_age ? Number(form.applicant_age) : null,
        applicant_address: form.applicant_address,
        contact_number: form.contact_number,
        email: form.email,
        business_name: form.business_name || null,
        remarks: form.remarks || null,
      }

      const response = await createInspectionRequest(payload)
      const requestId = response?.data?.id ?? response?.id

      if (!requestId) {
        throw new Error('No request id returned')
      }

      const uploads = Object.entries(files).filter(([, file]) => file)
      for (const [documentType, file] of uploads) {
        await uploadRequestDocumentType(requestId, documentType, file)
      }

      toast.success('Inspection request submitted')
      navigate('/resident/my-applications')
    } catch (error) {
      const validationErrors = error.response?.data?.errors

      if (validationErrors) {
        setErrors(validationErrors)
        toast.error('Please review the highlighted fields')
      } else {
        toast.error(error.response?.data?.message ?? 'Unable to submit request')
      }
      console.error(error)
    } finally {
      setSubmitting(false)
    }
  }

  function handleFileSelect(documentType, event) {
    const file = event.target.files?.[0]
    setFiles((prev) => ({ ...prev, [documentType]: file ?? undefined }))
  }

  const canSkipScale = !needsScaleStep && step === 1

  return (
    <div className="space-y-6">
      <div>
        <div className="flex items-center gap-2">
          <Button variant="ghost" size="icon" onClick={goBack}>
            <ArrowLeft className="size-4" />
          </Button>
          <h2 className="text-2xl font-semibold tracking-tight">Request Inspection</h2>
        </div>
        <p className="text-sm text-muted-foreground ml-10">Apply for a barangay health and safety inspection</p>
      </div>

      {!blocked && (
        <div className="flex flex-wrap items-center gap-2">
          {steps.map((s, i) => (
            <div
              key={s.key}
              className={cn(
                'flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-medium',
                i === step && 'bg-accent text-accent-foreground',
                i < step && 'text-muted-foreground',
                i > step && 'text-muted-foreground/50',
              )}
            >
              <span
                className={cn(
                  'flex size-4 items-center justify-center rounded-full text-[10px]',
                  i < step ? 'bg-primary text-primary-foreground' : i === step ? 'bg-foreground text-background' : 'bg-muted',
                )}
              >
                {i < step ? <Check className="size-3" /> : i + 1}
              </span>
              {s.label}
            </div>
          ))}
        </div>
      )}

      {blocked ? (
        <Card className="border-destructive/40">
          <CardHeader className="text-center">
            <div className="mx-auto flex size-14 items-center justify-center rounded-full bg-destructive/10">
              <ShieldAlert className="size-7 text-destructive" />
            </div>
            <CardTitle className="text-xl">Application Not Allowed</CardTitle>
            <CardDescription className="mx-auto max-w-lg">
              Zoning clearances cannot be issued for commercial-scale {selectedCategory?.name.toLowerCase()} operations in
              Barangay 178. Only backyard micro-scale operations (2–5 animals, personal use only) may be applied for.
            </CardDescription>
          </CardHeader>
          <CardContent className="flex flex-wrap justify-center gap-2">
            <Button variant="outline" onClick={() => { setForm((prev) => ({ ...prev, sub_path: 'backyard_micro_scale', declared_animal_count: '' })); setBlocked(false); setStep(1) }}>
              Switch to Backyard Micro-Scale
            </Button>
            <Button variant="outline" onClick={() => { setForm(emptyForm); setFiles({}); setStep(0); setBlocked(false) }}>
              Choose a Different Category
            </Button>
          </CardContent>
        </Card>
      ) : (
        <Card>
          <CardHeader>
            <CardTitle>{stepLabels(step)}</CardTitle>
            <CardDescription>{stepDescriptions(step, selectedCategory)}</CardDescription>
          </CardHeader>
          <CardContent>
            {optionsLoading ? (
              <div className="space-y-4">
                <Skeleton className="h-10 w-full" />
                <Skeleton className="h-10 w-full" />
              </div>
            ) : optionsError ? (
              <p className="text-sm text-destructive">Unable to load inspection categories. Please try again.</p>
            ) : (
              <div className="space-y-6">
                {step === 0 && (
                  <div className="grid gap-3 sm:grid-cols-2">
                    {categories.map((category) => (
                      <button
                        key={category.id}
                        type="button"
                        onClick={() => handleCategorySelect(category)}
                        className={cn(
                          'rounded-xl border border-border p-4 text-left transition-colors hover:border-ring hover:bg-accent/50',
                          form.inspection_category_id === String(category.id) && 'border-ring bg-accent/60',
                        )}
                      >
                        <div className="flex items-center gap-2">
                          <Store className="size-4 text-muted-foreground" />
                          <p className="text-sm font-semibold">{category.name}</p>
                        </div>
                        <p className="mt-1 text-xs text-muted-foreground">{category.description}</p>
                        {category.requires_business_details && (
                          <Badge variant="secondary" className="mt-2">Commercial / Business</Badge>
                        )}
                      </button>
                    ))}
                    {errors.inspection_category_id && (
                      <p className="text-xs text-destructive sm:col-span-2">{errors.inspection_category_id}</p>
                    )}
                  </div>
                )}

                {step === 1 && needsScaleStep && (
                  <div className="space-y-4">
                    {isDogCategory ? (
                      <div className="grid gap-3 sm:grid-cols-2">
                        {[
                          { value: 'household', title: 'Household / Pet Dog Keeping', description: 'Non-commercial. Pet registration form and anti-rabies certificate required.' },
                          { value: 'commercial_kennel', title: 'Commercial Kennel / Breeding', description: 'DTI/SEC registration, BAI registration (RA 8485), HOA clearance, and neighbor consent.' },
                        ].map((option) => (
                          <button
                            key={option.value}
                            type="button"
                            onClick={() => handleScaleSelect(option.value)}
                            className={cn(
                              'rounded-xl border border-border p-4 text-left transition-colors hover:border-ring hover:bg-accent/50',
                              form.sub_path === option.value && 'border-ring bg-accent/60',
                            )}
                          >
                            <p className="text-sm font-semibold">{option.title}</p>
                            <p className="mt-1 text-xs text-muted-foreground">{option.description}</p>
                          </button>
                        ))}
                      </div>
                    ) : (
                      <div className="grid gap-3 sm:grid-cols-2">
                        <button
                          type="button"
                          onClick={() => handleScaleSelect('commercial')}
                          className="rounded-xl border border-destructive/40 p-4 text-left transition-colors hover:border-destructive"
                        >
                          <Badge variant="destructive" className="mb-2">Not Allowed</Badge>
                          <p className="text-sm font-semibold">Commercial Scale</p>
                          <p className="mt-1 text-xs text-muted-foreground">
                            Large-scale operations. Blocked in Barangay 178 — no zoning clearance can be issued.
                          </p>
                        </button>

                        <div className="rounded-xl border border-border p-4">
                          <button
                            type="button"
                            onClick={() => { setForm((prev) => ({ ...prev, sub_path: 'backyard_micro_scale' })); setBlocked(false) }}
                            className={cn(
                              'w-full rounded-lg border border-border p-3 text-left transition-colors hover:border-ring hover:bg-accent/50',
                              form.sub_path === 'backyard_micro_scale' && 'border-ring bg-accent/60',
                            )}
                          >
                            <p className="text-sm font-semibold">Backyard Micro-Scale</p>
                            <p className="mt-1 text-xs text-muted-foreground">
                              Strictly 2–5 animals, personal use only. Zoning, waste management, and neighbor consent required.
                            </p>
                          </button>

                          {form.sub_path === 'backyard_micro_scale' && (
                            <div className="mt-3 space-y-2">
                              <Label htmlFor="declared_animal_count">Declared Number of Animals (2–5)</Label>
                              <Input
                                id="declared_animal_count"
                                type="number"
                                min="2"
                                max="5"
                                value={form.declared_animal_count}
                                onChange={(e) => updateForm('declared_animal_count', e.target.value)}
                              />
                              {errors.declared_animal_count && (
                                <p className="text-xs text-destructive">{errors.declared_animal_count}</p>
                              )}
                              <Button
                                className="mt-2 w-full"
                                onClick={() => {
                                  const count = Number(form.declared_animal_count)
                                  if (!count || count < 2 || count > 5) {
                                    setErrors({ declared_animal_count: 'Backyard micro-scale requires 2–5 animals.' })
                                    return
                                  }
                                  setErrors({})
                                  goNext()
                                }}
                              >
                                Continue
                                <ArrowRight className="size-4" />
                              </Button>
                            </div>
                          )}
                        </div>
                      </div>
                    )}
                    {errors.sub_path && <p className="text-xs text-destructive">{errors.sub_path}</p>}
                  </div>
                )}

                {step === 1 && canSkipScale && (
                  <p className="text-sm text-muted-foreground">This category does not require a scale declaration.</p>
                )}

                {step === 2 && (
                  <div className="grid gap-3 sm:grid-cols-2">
                    {applicationTypes.map((type) => (
                      <button
                        key={type.id}
                        type="button"
                        onClick={() => updateForm('application_type_id', String(type.id))}
                        className={cn(
                          'rounded-xl border border-border p-4 text-left transition-colors hover:border-ring hover:bg-accent/50',
                          form.application_type_id === String(type.id) && 'border-ring bg-accent/60',
                        )}
                      >
                        <p className="text-sm font-semibold">{type.name}</p>
                        <p className="mt-1 text-xs text-muted-foreground">{type.description}</p>
                      </button>
                    ))}
                    {errors.application_type_id && (
                      <p className="text-xs text-destructive sm:col-span-2">{errors.application_type_id}</p>
                    )}
                  </div>
                )}

                {step === 3 && (
                  <div className="space-y-4">
                    {requirementsQuery.isLoading && <Skeleton className="h-24 w-full" />}
                    {requirementsQuery.isError && (
                      <p className="text-sm text-destructive">Unable to load document requirements.</p>
                    )}
                    {!requirementsQuery.isLoading && !requirementsQuery.isError && (
                      <>
                        <p className="text-sm text-muted-foreground">
                          Upload all required documents ({requirements.filter((r) => r.is_required).length} required). Additional
                          conditional documents can be uploaded if applicable.
                        </p>
                        <div className="grid gap-3 sm:grid-cols-2">
                          {requirements.map((requirement) => (
                            <div key={requirement.document_type} className="space-y-2 rounded-xl border border-border p-4">
                              <div className="flex items-start justify-between gap-2">
                                <div className="min-w-0">
                                  <p className="text-sm font-medium">{requirement.document_name}</p>
                                  <p className="font-mono text-[10px] text-muted-foreground">{requirement.document_type}</p>
                                </div>
                                <Badge variant={requirement.is_required ? 'default' : 'outline'}>
                                  {requirement.is_required ? 'Required' : 'Conditional'}
                                </Badge>
                              </div>
                              <label className="flex cursor-pointer items-center justify-center gap-2 rounded-lg border border-dashed border-border px-3 py-2.5 text-xs text-muted-foreground hover:border-ring hover:text-foreground">
                                <FileUp className="size-4" />
                                {files[requirement.document_type] ? files[requirement.document_type].name : 'Choose file'}
                                <input
                                  type="file"
                                  accept="image/*,.pdf"
                                  className="hidden"
                                  onChange={(e) => handleFileSelect(requirement.document_type, e)}
                                />
                              </label>
                              {requirement.notes && <p className="text-[11px] text-muted-foreground">{requirement.notes}</p>}
                            </div>
                          ))}
                        </div>
                      </>
                    )}
                  </div>
                )}

                {step === 4 && (
                  <div className="grid gap-4 sm:grid-cols-2">
                    <div className="space-y-2">
                      <Label htmlFor="applicant_name">Full Name</Label>
                      <Input id="applicant_name" value={form.applicant_name} onChange={(e) => updateForm('applicant_name', e.target.value)} />
                      {errors.applicant_name && <p className="text-xs text-destructive">{errors.applicant_name}</p>}
                    </div>
                    <div className="space-y-2">
                      <Label htmlFor="applicant_age">Age</Label>
                      <Input id="applicant_age" type="number" min="1" max="150" value={form.applicant_age} onChange={(e) => updateForm('applicant_age', e.target.value)} />
                    </div>
                    <div className="space-y-2">
                      <Label htmlFor="contact_number">Contact Number</Label>
                      <Input id="contact_number" value={form.contact_number} onChange={(e) => updateForm('contact_number', e.target.value)} />
                      {errors.contact_number && <p className="text-xs text-destructive">{errors.contact_number}</p>}
                    </div>
                    <div className="space-y-2">
                      <Label htmlFor="email">Email Address</Label>
                      <Input id="email" type="email" value={form.email} onChange={(e) => updateForm('email', e.target.value)} />
                      {errors.email && <p className="text-xs text-destructive">{errors.email}</p>}
                    </div>
                    {requiresBusinessDetails && (
                      <div className="space-y-2">
                        <Label htmlFor="business_name">Business / Facility Name</Label>
                        <Input id="business_name" value={form.business_name} onChange={(e) => updateForm('business_name', e.target.value)} />
                        {errors.business_name && <p className="text-xs text-destructive">{errors.business_name}</p>}
                      </div>
                    )}
                    <div className="space-y-2 sm:col-span-2">
                      <Label htmlFor="applicant_address">Business / Facility Address</Label>
                      <textarea
                        id="applicant_address"
                        className="min-h-20 w-full rounded-lg border border-input bg-background px-2.5 py-2 text-sm outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50"
                        value={form.applicant_address}
                        onChange={(e) => updateForm('applicant_address', e.target.value)}
                      />
                      {errors.applicant_address && <p className="text-xs text-destructive">{errors.applicant_address}</p>}
                    </div>
                    <div className="space-y-2 sm:col-span-2">
                      <Label htmlFor="remarks">Purpose / Notes</Label>
                      <textarea
                        id="remarks"
                        className="min-h-20 w-full rounded-lg border border-input bg-background px-2.5 py-2 text-sm outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50"
                        value={form.remarks}
                        onChange={(e) => updateForm('remarks', e.target.value)}
                      />
                    </div>
                  </div>
                )}

                {step === 5 && (
                  <div className="space-y-4">
                    <div className="grid gap-3 text-sm sm:grid-cols-2">
                      <div>
                        <p className="text-xs text-muted-foreground">Category</p>
                        <p className="font-medium">{selectedCategory?.name}</p>
                      </div>
                      <div>
                        <p className="text-xs text-muted-foreground">Application Type</p>
                        <p className="font-medium">{selectedAppType?.name}</p>
                      </div>
                      {(form.sub_path || form.declared_animal_count) && (
                        <div>
                          <p className="text-xs text-muted-foreground">Type / Scale</p>
                          <p className="font-medium">{scaleLabel(form.sub_path)}</p>
                        </div>
                      )}
                      {form.declared_animal_count && (
                        <div>
                          <p className="text-xs text-muted-foreground">Declared Animal Count</p>
                          <p className="font-medium">{form.declared_animal_count}</p>
                        </div>
                      )}
                      <div>
                        <p className="text-xs text-muted-foreground">Applicant</p>
                        <p className="font-medium">{form.applicant_name}</p>
                      </div>
                      <div>
                        <p className="text-xs text-muted-foreground">Contact</p>
                        <p className="font-medium">{form.contact_number} · {form.email}</p>
                      </div>
                    </div>

                    <div className="border-t pt-4">
                      <p className="mb-2 text-xs text-muted-foreground">Documents ({Object.values(files).filter(Boolean).length} selected)</p>
                      <div className="flex flex-wrap gap-2">
                        {Object.entries(files).filter(([, file]) => file).map(([type, file]) => (
                          <Badge key={type} variant="outline">{file.name}</Badge>
                        ))}
                        {Object.values(files).filter(Boolean).length === 0 && (
                          <p className="text-sm text-muted-foreground">No documents selected.</p>
                        )}
                      </div>
                    </div>

                    {errors.sub_path && <p className="text-xs text-destructive">{errors.sub_path}</p>}
                    {errors.declared_animal_count && <p className="text-xs text-destructive">{errors.declared_animal_count}</p>}
                  </div>
                )}

                <div className="flex justify-between gap-2 border-t pt-4">
                  <div className="flex gap-2">
                    <Button variant="ghost" onClick={() => navigate('/resident/my-applications')}>
                      Cancel
                    </Button>
                    {step > 0 && (
                      <Button type="button" variant="outline" onClick={goBack}>
                        <ArrowLeft className="size-4" />
                        Back
                      </Button>
                    )}
                  </div>

                  {step < steps.length - 1 ? (
                    <Button type="button" onClick={handleStepNext}>
                      Continue
                      <ArrowRight className="size-4" />
                    </Button>
                  ) : (
                    <Button type="button" disabled={submitting} onClick={handleSubmit}>
                      <Send className="size-4" />
                      {submitting ? 'Submitting...' : 'Submit Application'}
                    </Button>
                  )}
                </div>
              </div>
            )}
          </CardContent>
        </Card>
      )}
    </div>
  )
}

function stepLabels(step) {
  const base = ['Select Category', 'Type / Scale', 'Application Type', 'Upload Documents', 'Applicant Information', 'Review & Submit']
  return base[step]
}

function stepDescriptions(step, category) {
  switch (step) {
    case 0:
      return 'Choose the inspection category that best matches your activity.'
    case 1:
      return category ? `Declare the scale/type for ${category.name}.` : 'Declare the scale or type of your operation.'
    case 2:
      return 'Indicate whether this is a new application or a renewal.'
    case 3:
      return 'Upload the required supporting documents. Requirements depend on your selections.'
    case 4:
      return 'Provide your contact details so the barangay can reach you.'
    case 5:
      return 'Review your application before submitting.'
    default:
      return ''
  }
}

function scaleLabel(subPath) {
  switch (subPath) {
    case 'household':
      return 'Household / Pet Dog Keeping'
    case 'commercial_kennel':
      return 'Commercial Kennel / Breeding'
    case 'backyard_micro_scale':
      return 'Backyard Micro-Scale'
    case 'commercial':
      return 'Commercial (Blocked)'
    default:
      return 'N/A'
  }
}
