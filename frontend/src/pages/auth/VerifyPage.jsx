import { useEffect, useState } from 'react'
import { Link, useNavigate, useSearchParams } from 'react-router-dom'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { MailCheck, ShieldCheck } from 'lucide-react'
import { toast } from 'sonner'

import { useAuth } from '@/context/AuthContext'
import { getHomePath } from '@/utils/permissions'
import { Button } from '@/components/ui/button'
import {
  Card,
  CardContent,
  CardDescription,
  CardFooter,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import {
  Form,
  FormControl,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from '@/components/ui/form'
import { Input } from '@/components/ui/input'

const verifySchema = z.object({
  code: z
    .string()
    .length(6, 'The verification code is 6 digits')
    .regex(/^\d{6}$/, 'The verification code must be numeric'),
})

const RESEND_COOLDOWN_SECONDS = 60

export default function VerifyPage() {
  const { verifyEmail, resendVerification } = useAuth()
  const navigate = useNavigate()
  const [searchParams] = useSearchParams()
  const [email, setEmail] = useState(() => searchParams.get('email') ?? '')
  const [submitting, setSubmitting] = useState(false)
  const [resending, setResending] = useState(false)
  const [resendIn, setResendIn] = useState(0)

  useEffect(() => {
    if (resendIn <= 0) {
      return undefined
    }

    const timer = setInterval(() => {
      setResendIn((seconds) => seconds - 1)
    }, 1000)

    return () => clearInterval(timer)
  }, [resendIn])

  const form = useForm({
    resolver: zodResolver(verifySchema),
    defaultValues: { code: '' },
  })

  async function onSubmit(values) {
    if (!email) {
      toast.error('Please provide your email address first.')
      return
    }

    setSubmitting(true)

    try {
      const response = await verifyEmail({ email, code: values.code })
      toast.success('Verification successful')
      navigate(getHomePath(response?.data?.user?.role?.slug), { replace: true })
    } catch (error) {
      const errors = error.response?.data?.errors
      const message =
        errors?.code?.[0] ||
        errors?.email?.[0] ||
        error.response?.data?.message ||
        'Unable to verify the code. Please try again.'
      toast.error(message)
    } finally {
      setSubmitting(false)
    }
  }

  async function handleResend() {
    if (!email || resendIn > 0 || resending) {
      return
    }

    setResending(true)

    try {
      await resendVerification({ email })
      toast.success('A new verification code has been sent.')
      setResendIn(RESEND_COOLDOWN_SECONDS)
    } catch (error) {
      const errors = error.response?.data?.errors
      const message =
        errors?.email?.[0] ||
        error.response?.data?.message ||
        'Unable to resend the code. Please try again.'
      toast.error(message)
    } finally {
      setResending(false)
    }
  }

  return (
    <div className="flex min-h-svh items-center justify-center bg-background p-4">
      <Card className="w-full max-w-md">
        <CardHeader className="space-y-3 text-center">
          <div className="mx-auto flex size-12 items-center justify-center rounded-xl bg-primary text-primary-foreground">
            <MailCheck className="size-6" />
          </div>
          <CardTitle className="text-2xl">Verify Your Identity</CardTitle>
          <CardDescription>
            Enter the 6-digit code we sent to your email to continue.
          </CardDescription>
        </CardHeader>

        <CardContent className="space-y-4">
          <Form {...form}>
            <FormField
              control={form.control}
              name="email"
            render={() => (
              <FormItem>
                <FormLabel>Email address</FormLabel>
                <FormControl>
                  <Input
                    type="email"
                    placeholder="resident@email.com"
                    value={email}
                    onChange={(event) => setEmail(event.target.value)}
                    autoComplete="email"
                  />
                </FormControl>
                <FormMessage />
              </FormItem>
            )}
          />

          <form onSubmit={form.handleSubmit(onSubmit)} className="space-y-4">
              <FormField
                control={form.control}
                name="code"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>Verification code</FormLabel>
                    <FormControl>
                      <Input
                        type="text"
                        inputMode="numeric"
                        maxLength={6}
                        placeholder="000000"
                        className="text-center text-lg tracking-[0.5em]"
                        autoComplete="one-time-code"
                        {...field}
                      />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />

              <Button type="submit" className="w-full" disabled={submitting}>
                {submitting ? 'Verifying...' : 'Continue'}
              </Button>
            </form>
          </Form>

          <div className="flex items-center justify-center gap-1 text-sm text-muted-foreground">
            <span>Didn&apos;t receive the code?</span>
            <Button
              variant="link"
              size="sm"
              type="button"
              className="h-auto p-0"
              onClick={handleResend}
              disabled={resendIn > 0 || resending}
            >
              {resendIn > 0
                ? `Resend in ${resendIn}s`
                : resending
                  ? 'Resending...'
                  : 'Resend code'}
            </Button>
          </div>

          <p className="flex items-center justify-center gap-1.5 text-center text-xs text-muted-foreground">
            <ShieldCheck className="size-3.5" />
            The code expires in 10 minutes and is valid for up to 5 attempts.
          </p>
        </CardContent>

        <CardFooter className="justify-center text-sm text-muted-foreground">
          <Link to="/login" className="font-medium text-primary hover:underline">
            Back to sign in
          </Link>
        </CardFooter>
      </Card>
    </div>
  )
}
