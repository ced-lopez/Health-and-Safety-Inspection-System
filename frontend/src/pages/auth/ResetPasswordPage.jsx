import { useState } from "react";
import { Link, useNavigate, useSearchParams } from "react-router-dom";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { z } from "zod";
import { Eye, EyeOff } from "lucide-react";

import brgyLogo from "@/assets/brgy178logo.jpg";
import { resetPassword } from "@/services/authService";
import { PASSWORD_REQUIREMENTS } from "@/utils/password";
import { Button } from "@/components/ui/button";
import {
  Card,
  CardContent,
  CardDescription,
  CardFooter,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import {
  Form,
  FormControl,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from "@/components/ui/form";
import { Input } from "@/components/ui/input";

const resetPasswordSchema = z
  .object({
    password: z.string().min(1, "Password is required"),
    passwordConfirmation: z.string(),
  })
  .superRefine((data, context) => {
    if (data.password !== data.passwordConfirmation) {
      context.addIssue({
        code: "custom",
        path: ["passwordConfirmation"],
        message: "Passwords do not match",
      });
    }

    for (const requirement of PASSWORD_REQUIREMENTS) {
      if (!requirement.test(data.password)) {
        context.addIssue({
          code: "custom",
          path: ["password"],
          message: requirement.label,
        });
        break;
      }
    }
  });

export default function ResetPasswordPage() {
  const navigate = useNavigate();
  const [searchParams] = useSearchParams();
  const token = searchParams.get("token") || "";
  const email = searchParams.get("email") || "";
  const hasValidLinkShape = Boolean(token && email);
  const [submitting, setSubmitting] = useState(false);
  const [showPassword, setShowPassword] = useState(false);
  const [showConfirmation, setShowConfirmation] = useState(false);
  const [resetError, setResetError] = useState(
    hasValidLinkShape ? "" : "This password reset link is invalid or incomplete.",
  );
  const form = useForm({
    resolver: zodResolver(resetPasswordSchema),
    defaultValues: { password: "", passwordConfirmation: "" },
  });

  async function onSubmit(values) {
    if (!hasValidLinkShape) {
      return;
    }

    setSubmitting(true);
    setResetError("");

    try {
      await resetPassword({
        email,
        token,
        password: values.password,
        password_confirmation: values.passwordConfirmation,
      });
      navigate("/login", { replace: true, state: { passwordReset: true } });
    } catch (error) {
      setResetError(
        error.response?.data?.message ||
          "Unable to reset your password. Please request a new reset link.",
      );
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <div className="flex min-h-svh items-center justify-center bg-background p-4">
      <Card className="w-full max-w-md">
        <CardHeader className="space-y-3 text-center">
          <div className="mx-auto flex size-20 items-center justify-center overflow-hidden rounded-full">
            <img src={brgyLogo} alt="Barangay 178 Logo" className="size-full object-cover" />
          </div>
          <CardTitle className="text-2xl">Reset Password</CardTitle>
          <CardDescription>Choose a new password for your account.</CardDescription>
        </CardHeader>

        <CardContent>
          <Form {...form}>
            <form onSubmit={form.handleSubmit(onSubmit)} className="space-y-4">
              <FormField
                control={form.control}
                name="password"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>New Password</FormLabel>
                    <FormControl>
                      <div className="relative">
                        <Input
                          type={showPassword ? "text" : "password"}
                          autoComplete="new-password"
                          className="pr-9"
                          {...field}
                        />
                        <Button
                          type="button"
                          variant="ghost"
                          size="icon-sm"
                          onClick={() => setShowPassword((value) => !value)}
                          aria-label={showPassword ? "Hide password" : "Show password"}
                          className="absolute inset-y-0 right-0.5 my-auto"
                        >
                          {showPassword ? <EyeOff className="size-4" /> : <Eye className="size-4" />}
                        </Button>
                      </div>
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />

              <FormField
                control={form.control}
                name="passwordConfirmation"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>Confirm New Password</FormLabel>
                    <FormControl>
                      <div className="relative">
                        <Input
                          type={showConfirmation ? "text" : "password"}
                          autoComplete="new-password"
                          className="pr-9"
                          {...field}
                        />
                        <Button
                          type="button"
                          variant="ghost"
                          size="icon-sm"
                          onClick={() => setShowConfirmation((value) => !value)}
                          aria-label={showConfirmation ? "Hide password" : "Show password"}
                          className="absolute inset-y-0 right-0.5 my-auto"
                        >
                          {showConfirmation ? <EyeOff className="size-4" /> : <Eye className="size-4" />}
                        </Button>
                      </div>
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />

              {resetError && (
                <p className="text-sm text-destructive" role="alert">
                  {resetError}
                </p>
              )}

              <Button
                type="submit"
                className="w-full"
                disabled={submitting || !hasValidLinkShape}
              >
                {submitting ? "Resetting..." : "Reset Password"}
              </Button>
            </form>
          </Form>
        </CardContent>

        <CardFooter className="justify-center text-sm text-muted-foreground">
          <Link to="/forgot-password" className="font-medium text-primary hover:underline">
            Request a new reset link
          </Link>
        </CardFooter>
      </Card>
    </div>
  );
}
