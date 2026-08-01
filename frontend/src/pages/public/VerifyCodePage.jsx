import { useEffect, useState } from 'react'
import { useParams } from 'react-router-dom'
import { BadgeCheck, Ban, Clock, ShieldAlert } from 'lucide-react'
import { toast } from 'sonner'

import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'
import { verifyQrCode } from '@/services/certificationService'

function formatDate(value) {
  if (!value) return 'N/A'
  return new Date(`${value}T00:00:00`).toLocaleDateString(undefined, { month: 'long', day: 'numeric', year: 'numeric' })
}

function statusMeta(status) {
  switch (status) {
    case 'active':
      return { label: 'Valid Document', variant: 'default', icon: BadgeCheck, note: 'This document is active and valid.' }
    case 'pending':
      return { label: 'Pending Approval', variant: 'secondary', icon: Clock, note: 'This document has been issued but is not yet approved.' }
    case 'revoked':
      return { label: 'Revoked', variant: 'destructive', icon: Ban, note: 'This document has been revoked and is no longer valid.' }
    case 'expired':
      return { label: 'Expired', variant: 'destructive', icon: Clock, note: 'This document has passed its expiration date.' }
    default:
      return { label: 'Unknown Status', variant: 'outline', icon: ShieldAlert, note: 'The status of this document could not be determined.' }
  }
}

export default function VerifyCodePage() {
  const { code } = useParams()
  const [loading, setLoading] = useState(true)
  const [data, setData] = useState(null)
  const [notFound, setNotFound] = useState(false)

  useEffect(() => {
    let active = true

    async function load() {
      try {
        const res = await verifyQrCode(code)
        if (!active) return
        setData(res.data ?? res)
      } catch (error) {
        if (!active) return
        setNotFound(true)
        toast.error(error.response?.data?.message ?? 'Unable to verify this document')
      } finally {
        if (active) setLoading(false)
      }
    }

    load()
    return () => { active = false }
  }, [code])

  const document = data?.document
  const meta = document ? statusMeta(document.status) : statusMeta(null)

  if (loading) {
    return (
      <div className="flex min-h-[70vh] items-center justify-center p-6">
        <Skeleton className="h-72 w-full max-w-xl" />
      </div>
    )
  }

  return (
    <div className="flex min-h-[70vh] items-center justify-center p-6">
      <Card className="w-full max-w-xl">
        <CardHeader className="text-center">
          <div className="mx-auto mb-2">
            <Badge variant={meta.variant} className="gap-1.5 px-3 py-1">
              <meta.icon className="size-4" />
              {meta.label}
            </Badge>
          </div>
          <CardTitle>Document Verification</CardTitle>
          <CardDescription>
            {notFound
              ? 'The QR code provided does not match any issued document.'
              : meta.note}
          </CardDescription>
        </CardHeader>

        {!notFound && document && (
          <CardContent>
            <div className="rounded-lg border border-border p-4 text-center text-sm text-muted-foreground">
              <p className="font-medium">Verification Code</p>
              <p className="mt-1 font-mono text-xs">{code}</p>
              {data?.qr_code?.last_verified_at && (
                <p className="mt-1 text-xs">
                  This code has been verified {data.qr_code.verification_count ?? 1} time(s).
                </p>
              )}
            </div>

            <div className="mt-4 grid gap-3 text-sm sm:grid-cols-2">
              <Detail label="Document No." value={document.number} />
              <Detail label="Document Type" value={document.document_type} />
              <Detail label="Owner / Applicant" value={document.establishment?.owner_name} />
              <Detail label="Business Name" value={document.establishment?.name} />
              <Detail label="Issue Date" value={formatDate(document.issue_date)} />
              <Detail label="Expiration Date" value={formatDate(document.expiration_date)} />
              <Detail label="Issuing Authority" value="Barangay 178, North Caloocan City" />
              <Detail label="Issued By" value={document.issuer?.name} />
            </div>
          </CardContent>
        )}

        {notFound && (
          <CardContent className="text-center">
            <p className="text-sm text-muted-foreground">
              Please contact the Barangay office if you believe this is an error.
            </p>
          </CardContent>
        )}

        <CardContent className="flex justify-center pb-6">
          <Button variant="outline" onClick={() => window.history.back()}>
            Back
          </Button>
        </CardContent>
      </Card>
    </div>
  )
}

function Detail({ label, value }) {
  return (
    <div>
      <p className="text-xs text-muted-foreground">{label}</p>
      <p className="font-medium">{value ?? 'N/A'}</p>
    </div>
  )
}
