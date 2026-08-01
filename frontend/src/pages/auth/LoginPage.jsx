import { useState } from "react";
import { useLocation, useNavigate } from "react-router-dom";
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
      const response = await login({ ...values, portal: "staff" });

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
      const roleSlug = response?.data?.user?.role?.slug;
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
          <div className="mx-auto flex size-20 items-center justify-center overflow-hidden rounded-full">
            <img
              src={brgyLogo}
              alt="Barangay 178 Logo"
              className="size-full object-cover"
            />
          </div>
          <CardTitle className="text-2xl">Sign In</CardTitle>
          <CardDescription>
            For administrators, barangay staff, and inspectors
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
                        placeholder="admin@barangay178.gov.ph"
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

              <Button type="submit" className="w-full" disabled={submitting}>
                {submitting ? "Signing in..." : "Sign In"}
              </Button>
            </form>
          </Form>
        </CardContent>

        <CardFooter className="flex justify-center text-sm text-muted-foreground">
          <p>Contact your administrator to request access.</p>
        </CardFooter>
      </Card>
    </div>
  );
}
