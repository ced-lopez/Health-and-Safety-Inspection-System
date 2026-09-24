import { useEffect, useState } from "react";
import { Link, useLocation, useNavigate } from "react-router-dom";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { z } from "zod";
import { Eye, EyeOff } from "lucide-react";

import { toast } from "sonner";
import brgyLogo from "@/assets/brgy178logo.jpg";
import { useAuth } from "@/context/AuthContext";
import { getHomePath } from "@/utils/permissions";
import { Button } from "@/components/ui/button";
import {
  Card,
  CardContent,
  CardDescription,
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

const loginSchema = z.object({
  email: z.email("Please enter a valid email address"),
  password: z.string().min(8, "Password must be at least 8 characters"),
});

export default function LoginPage() {
  const { login } = useAuth();
  const navigate = useNavigate();
  const location = useLocation();
  const [submitting, setSubmitting] = useState(false);
  const [showPassword, setShowPassword] = useState(false);

  useEffect(() => {
    if (location.state?.passwordReset) {
      toast.success(
        "Password reset successfully. Please sign in with your new password.",
      );
      navigate(location.pathname, { replace: true, state: null });
    }
  }, [location.pathname, location.state, navigate]);

  const form = useForm({
    resolver: zodResolver(loginSchema),
    defaultValues: {
      email: "",
      password: "",
    },
  });

  async function onSubmit(values) {
    setSubmitting(true);

    try {
      const response = await login(values);

      if (response.data?.verification_required) {
        toast.info(
          response.data?.message || "Please verify your email to continue.",
        );
        navigate(`/register/verify?email=${encodeURIComponent(values.email)}`, {
          replace: true,
        });
        return;
      }

      toast.success("Welcome back!");
      const roleSlug = response?.data?.role || response?.data?.user?.role?.slug;
      const redirectTo =
        location.state?.from?.pathname || getHomePath(roleSlug);
      navigate(redirectTo, { replace: true });
    } catch (error) {
      const message =
        error.response?.data?.message ||
        error.response?.data?.errors?.email?.[0] ||
        "Unable to sign in. Please try again.";

      if (message.includes("verify your email")) {
        toast.info(message);
        navigate(`/register/verify?email=${encodeURIComponent(values.email)}`, {
          replace: true,
        });
        return;
      }

      toast.error(message);
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <div className="flex min-h-svh items-center justify-center bg-background p-4">
      <Card className="w-full max-w-md">
        <CardHeader className="space-y-3 text-center">
          <div className="space-y-1">
            <p className="text-[0.65rem] font-semibold tracking-[0.18em] text-muted-foreground">
              CALOOCAN CITY LOCAL GOVERNMENT
            </p>
            <p className="text-xs font-bold tracking-wide text-foreground">
              BARANGAY 178
            </p>
            <p className="text-[0.7rem] text-muted-foreground">
              Health and Safety Inspection System
            </p>
          </div>
          <div className="mx-auto flex size-20 items-center justify-center overflow-hidden rounded-full">
            <img
              src={brgyLogo}
              alt="Barangay 178 Logo"
              className="size-full object-cover"
            />
          </div>
          <CardTitle className="text-2xl">Sign In</CardTitle>
          <CardDescription>
            Sign in to access your Health and Safety Inspection System account
          </CardDescription>
        </CardHeader>

        <CardContent>
          <Form {...form}>
            <form onSubmit={form.handleSubmit(onSubmit)} className="space-y-4">
              <FormField
                control={form.control}
                name="email"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>Email</FormLabel>
                    <FormControl>
                      <Input
                        type="email"
                        placeholder="you@gmail.com"
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
                name="password"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>Password</FormLabel>
                    <FormControl>
                      <div className="relative">
                        <Input
                          type={showPassword ? "text" : "password"}
                          placeholder="••••••••"
                          autoComplete="current-password"
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
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />

              <div className="flex justify-end">
                <Link
                  to="/forgot-password"
                  className="text-sm font-medium text-primary hover:underline"
                >
                  Forgot password?
                </Link>
              </div>

              <Button type="submit" className="w-full" disabled={submitting}>
                {submitting ? "Signing in..." : "Sign In"}
              </Button>
            </form>
          </Form>
        </CardContent>
      </Card>
    </div>
  );
}
