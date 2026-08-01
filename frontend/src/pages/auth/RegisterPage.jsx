import { useMemo, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { Check, Eye, EyeOff, Mail, MessageSquareText, Wand2 } from 'lucide-react'
import { toast } from 'sonner'

import { useAuth } from '@/context/AuthContext'
import brgyLogo from "@/assets/brgy178logo.jpg";
import { generateStrongPassword, PASSWORD_REQUIREMENTS } from '@/utils/password'
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
import { cn } from '@/lib/utils'

const registerSchema = z
  .object({
    name: z.string().min(2, 'Name must be at least 2 characters'),
    email: z.email('Please enter a valid email address'),
    phone: z.string().optional(),
    password: z.string().min(1, 'Password is required'),
    confirmPassword: z.string(),
  })
  .superRefine((data, ctx) => {
    if (data.password !== data.confirmPassword) {
      ctx.addIssue({
        code: 'custom',
        path: ['confirmPassword'],
        message: 'Passwords do not match',
      })
    }

    for (const requirement of PASSWORD_REQUIREMENTS) {
      if (!requirement.test(data.password)) {
        ctx.addIssue({
          code: 'custom',
          path: ['password'],
          message: requirement.label,
        })
        break
      }
    }
  })

const deliveryChannels = [
  { value: 'email', label: 'Email', icon: Mail, description: 'Send the code to your email', disabled: false },
  { value: 'sms', label: 'SMS', icon: MessageSquareText, description: 'Send the code to your phone', disabled: true },
]

export default function RegisterPage() {
  const { register: registerUser } = useAuth()
  const navigate = useNavigate()
  const [submitting, setSubmitting] = useState(false)
  const [deliveryChannel, setDeliveryChannel] = useState('email')
  const [showPassword, setShowPassword] = useState(false)
  const [showConfirmPassword, setShowConfirmPassword] = useState(false)

  const form = useForm({
    resolver: zodResolver(registerSchema),
    defaultValues: {
      name: '',
      email: '',
      phone: '',
      password: '',
      confirmPassword: '',
    },
  })

  const password = form.watch('password') ?? ''
  const passwordStrength = useMemo(() => {
    const passed = PASSWORD_REQUIREMENTS.filter((requirement) => requirement.test(password))
    return passed.length / PASSWORD_REQUIREMENTS.length
  }, [password])

  const strengthLabel =
    passwordStrength === 1
      ? 'Strong'
      : passwordStrength >= 0.6
        ? 'Good'
        : passwordStrength >= 0.4
          ? 'Fair'
          : passwordStrength > 0
            ? 'Weak'
            : 'Too weak'

  function handleGeneratePassword() {
    const generated = generateStrongPassword(16)
    form.setValue('password', generated, { shouldValidate: true })
    form.setValue('confirmPassword', generated, { shouldValidate: true })
    toast.success('Strong password generated')
  }

  async function onSubmit(values) {
    setSubmitting(true)

    try {
      await registerUser({
        name: values.name,
        email: values.email,
        phone: values.phone,
        password: values.password,
        password_confirmation: values.confirmPassword,
        verification_channel: deliveryChannel,
      })
      toast.success('Account created. Check your inbox for the verification code.')
      navigate(`/register/verify?email=${encodeURIComponent(values.email)}`, { replace: true })
    } catch (error) {
      const errors = error.response?.data?.errors
      const message =
        error.response?.data?.message ||
        errors?.email?.[0] ||
        errors?.password?.[0] ||
        'Unable to register. Please try again.'
      toast.error(message)
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <div className="flex min-h-svh items-center justify-center bg-background p-4">
      <Card className="w-full max-w-md">
        <CardHeader className="space-y-3 text-center">
          <div className="mx-auto flex size-20 items-center justify-center overflow-hidden rounded-full">
            <img
              src={brgyLogo}
              alt="Barangay 178 Logo"
              className="size-full object-cover"
            />
          </div>
          <CardTitle className="text-2xl">Create Account</CardTitle>
          <CardDescription>
            Register to request inspections and track your clearances
          </CardDescription>
        </CardHeader>

        <CardContent>
          <Form {...form}>
            <form onSubmit={form.handleSubmit(onSubmit)} className="space-y-4">
              <FormField
                control={form.control}
                name="name"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>Full Name</FormLabel>
                    <FormControl>
                      <Input placeholder="Juan Dela Cruz" {...field} />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />

              <FormField
                control={form.control}
                name="email"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>Email</FormLabel>
                    <FormControl>
                      <Input
                        type="email"
                        placeholder="resident@email.com"
                        autoComplete="email"
                        {...field}
                      />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />

              <FormField
                control={form.control}
                name="phone"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>Phone (optional)</FormLabel>
                    <FormControl>
                      <Input placeholder="09XX XXX XXXX" {...field} />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />

              <FormField
                control={form.control}
                name="password"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>Password</FormLabel>
                    <FormControl>
                      <div className="flex gap-2">
                        <div className="relative flex-1">
                          <Input
                            type={showPassword ? "text" : "password"}
                            placeholder="••••••••"
                            autoComplete="new-password"
                            className="pr-9"
                            {...field}
                          />
                          <Button
                            type="button"
                            variant="ghost"
                            size="icon-sm"
                            onClick={() => setShowPassword((value) => !value)}
                            aria-label={
                              showPassword ? "Hide password" : "Show password"
                            }
                            className="absolute inset-y-0 right-0.5 my-auto"
                          >
                            {showPassword ? (
                              <EyeOff className="size-4" />
                            ) : (
                              <Eye className="size-4" />
                            )}
                          </Button>
                        </div>
                        <Button
                          type="button"
                          variant="outline"
                          onClick={handleGeneratePassword}
                          aria-label="Auto-generate a strong password"
                          title="Auto-generate a strong password"
                        >
                          <Wand2 className="size-4" />
                          Generate
                        </Button>
                      </div>
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />

              <div className="space-y-2 rounded-lg border border-border bg-muted/40 p-3">
                <div className="flex items-center justify-between">
                  <p className="text-xs font-medium text-muted-foreground">
                    Password strength
                  </p>
                  <p className="text-xs font-medium text-accent">
                    {strengthLabel}
                  </p>
                </div>
                <div className="h-1.5 w-full overflow-hidden rounded-full bg-border">
                  <div
                    className="h-full rounded-full bg-accent transition-all"
                    style={{ width: `${passwordStrength * 100}%` }}
                  />
                </div>
                <ul className="space-y-1 pt-1">
                  {PASSWORD_REQUIREMENTS.map((requirement) => {
                    const passed = requirement.test(password);
                    return (
                      <li
                        key={requirement.key}
                        className={cn(
                          "flex items-center gap-2 text-xs",
                          passed ? "text-foreground" : "text-muted-foreground",
                        )}
                      >
                        <Check
                          className={cn(
                            "size-3.5",
                            passed ? "text-accent" : "text-muted-foreground/50",
                          )}
                        />
                        {requirement.label}
                      </li>
                    );
                  })}
                </ul>
              </div>

              <FormField
                control={form.control}
                name="confirmPassword"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>Confirm Password</FormLabel>
                    <FormControl>
                      <div className="relative">
                        <Input
                          type={showConfirmPassword ? "text" : "password"}
                          placeholder="••••••••"
                          autoComplete="new-password"
                          className="pr-9"
                          {...field}
                        />
                        <Button
                          type="button"
                          variant="ghost"
                          size="icon-sm"
                          onClick={() =>
                            setShowConfirmPassword((value) => !value)
                          }
                          aria-label={
                            showConfirmPassword
                              ? "Hide password"
                              : "Show password"
                          }
                          className="absolute inset-y-0 right-0.5 my-auto"
                        >
                          {showConfirmPassword ? (
                            <EyeOff className="size-4" />
                          ) : (
                            <Eye className="size-4" />
                          )}
                        </Button>
                      </div>
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />

              <div className="space-y-2">
                <FormLabel>Verification method</FormLabel>
                <div className="grid grid-cols-2 gap-2">
                  {deliveryChannels.map((channel) => {
                    const Icon = channel.icon;
                    const active = deliveryChannel === channel.value;
                    return (
                      <button
                        key={channel.value}
                        type="button"
                        disabled={channel.disabled}
                        onClick={() => setDeliveryChannel(channel.value)}
                        className={cn(
                          "flex flex-col items-start gap-1 rounded-lg border p-3 text-left transition-colors",
                          channel.disabled
                            ? "cursor-not-allowed border-border bg-muted/40 opacity-60"
                            : active
                              ? "border-accent bg-secondary text-secondary-foreground"
                              : "border-border bg-background hover:bg-muted",
                        )}
                      >
                        <Icon
                          className={cn(
                            "size-4",
                            active ? "text-accent" : "text-muted-foreground",
                          )}
                        />
                        <span className="flex items-center gap-1.5 text-sm font-medium">
                          {channel.label}
                          {channel.disabled && (
                            <span className="rounded-full bg-muted px-1.5 py-0.5 text-[0.6rem] font-semibold uppercase tracking-wide text-muted-foreground">
                              Coming soon
                            </span>
                          )}
                        </span>
                        <span className="text-xs text-muted-foreground">
                          {channel.description}
                        </span>
                      </button>
                    );
                  })}
                </div>
              </div>

              <Button type="submit" className="w-full" disabled={submitting}>
                {submitting ? "Creating account..." : "Register"}
              </Button>
            </form>
          </Form>
        </CardContent>

        <CardFooter className="justify-center text-sm text-muted-foreground">
          Already have an account?{" "}
          <Link
            to="/login"
            className="ml-1 font-medium text-primary hover:underline"
          >
            Sign In
          </Link>
        </CardFooter>
      </Card>
    </div>
  );
}
