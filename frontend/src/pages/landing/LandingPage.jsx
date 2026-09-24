import { useEffect, useRef, useState } from "react";
import { Link, Navigate } from "react-router-dom";
import {
  ArrowRight,
  Award,
  Building2,
  CheckCircle2,
  ClipboardCheck,
  Clock,
  FileText,
  Lock,
  Mail,
  MapPin,
  Moon,
  Phone,
  QrCode,
  ShieldCheck,
  Smartphone,
  Sun,
  TrendingUp,
  UserRound,
  Users,
  UtensilsCrossed,
  Zap,
} from "lucide-react";

import brgyLogo from "@/assets/brgy178logo.jpg";
import { useAuth } from "@/context/AuthContext";
import { APP_NAME, APP_SUBTITLE } from "@/utils/constants";
import { getHomePath } from "@/utils/permissions";
import { Button } from "@/components/ui/button";
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";

const steps = [
  {
    icon: UserRound,
    title: "Create your resident account",
    description:
      "Register once with your name, age, address, contact number, and email to get started.",
  },
  {
    icon: ClipboardCheck,
    title: "Submit an inspection request",
    description:
      "Select your inspection category, choose New or Renewal, and upload the required documents.",
  },
  {
    icon: ShieldCheck,
    title: "Get your property inspected",
    description:
      "Barangay staff review your request and assign an inspector to evaluate your establishment.",
  },
  {
    icon: Award,
    title: "Receive your health & safety clearance",
    description:
      "Once compliant, a QR-coded clearance valid for one year is issued to you.",
  },
];

const categories = [
  {
    icon: UtensilsCrossed,
    title: "Food Establishment",
    description:
      "Carinderia, restaurant, canteen, bakery, water refilling station, and more.",
  },
  {
    icon: Building2,
    title: "Piggery",
    description:
      "Animal housing, drainage, waste management, and odor control.",
  },
  {
    icon: QrCode,
    title: "Poultry",
    description:
      "Cage cleanliness, biosecurity, water source, and waste disposal.",
  },
  {
    icon: ShieldCheck,
    title: "Dog Raising / Kennel",
    description:
      "Kennel sanitation, animal welfare, vaccination, and noise control.",
  },
];

const requirements = [
  "Valid government-issued ID",
  "Complete applicant information",
  "Business or facility address",
  "Uploaded supporting documents",
  "Previous clearance for renewals",
];

// ---------------------------------------------------------------------------
// New, additive content for the redesigned sections. Placeholder figures —
// swap in real numbers whenever you have them.
// ---------------------------------------------------------------------------
const stats = [
  { icon: Users, value: "500+", label: "Residents Registered" },
  { icon: ClipboardCheck, value: "1,200+", label: "Inspections Completed" },
  { icon: QrCode, value: "950+", label: "QR Clearances Issued" },
  { icon: Clock, value: "24/7", label: "Online Availability" },
];

const features = [
  {
    icon: FileText,
    title: "Apply Online",
    description:
      "Submit an inspection request anytime, without a trip to the barangay hall.",
  },
  {
    icon: TrendingUp,
    title: "Track Applications",
    description:
      "Follow your request from submission to clearance in the resident portal.",
  },
  {
    icon: QrCode,
    title: "QR-Coded Clearance",
    description:
      "Every clearance carries a scannable QR code for instant verification.",
  },
  {
    icon: Lock,
    title: "Secure Resident Accounts",
    description:
      "Your personal information is protected behind a dedicated resident login.",
  },
  {
    icon: Zap,
    title: "Faster Processing",
    description:
      "Digital routing gets your request to the right inspector without delay.",
  },
  {
    icon: Smartphone,
    title: "Mobile Friendly",
    description:
      "Apply, upload documents, and check your status from any device.",
  },
];

// ---------------------------------------------------------------------------
// Dark mode: toggles a `dark` class on <html>, persists the choice, and
// falls back to the visitor's system preference on first load.
// ---------------------------------------------------------------------------
function useTheme() {
  const [theme, setTheme] = useState(() => {
    if (typeof window === "undefined") return "light";
    const stored = window.localStorage.getItem("theme");
    if (stored === "light" || stored === "dark") return stored;
    return window.matchMedia?.("(prefers-color-scheme: dark)").matches
      ? "dark"
      : "light";
  });

  useEffect(() => {
    document.documentElement.classList.toggle("dark", theme === "dark");
    window.localStorage.setItem("theme", theme);
  }, [theme]);

  return [theme, setTheme];
}

// ---------------------------------------------------------------------------
// Small helper: fades a section in as it scrolls into view. Respects
// prefers-reduced-motion by skipping the animation entirely.
// ---------------------------------------------------------------------------
function Reveal({ children, className = "" }) {
  const ref = useRef(null);
  const [visible, setVisible] = useState(false);

  useEffect(() => {
    const node = ref.current;
    if (!node) return;

    const prefersReducedMotion =
      typeof window !== "undefined" &&
      window.matchMedia?.("(prefers-reduced-motion: reduce)").matches;

    if (prefersReducedMotion) {
      setVisible(true);
      return;
    }

    const observer = new IntersectionObserver(
      ([entry]) => {
        if (entry.isIntersecting) {
          setVisible(true);
          observer.disconnect();
        }
      },
      { threshold: 0.15 },
    );
    observer.observe(node);
    return () => observer.disconnect();
  }, []);

  return (
    <div
      ref={ref}
      className={`transition-all duration-700 ease-out ${
        visible ? "translate-y-0 opacity-100" : "translate-y-6 opacity-0"
      } ${className}`}
    >
      {children}
    </div>
  );
}

export default function LandingPage() {
  const { isAuthenticated, loading, user } = useAuth();
  const [theme, setTheme] = useTheme();

  if (loading) {
    return (
      <div className="flex min-h-svh items-center justify-center">
        <p className="text-sm text-muted-foreground">Loading...</p>
      </div>
    );
  }

  if (isAuthenticated) {
    return <Navigate to={getHomePath(user?.role?.slug)} replace />;
  }

  return (
    <div className="flex min-h-svh flex-col bg-background">
      <header className="sticky top-0 z-20 border-b border-border/70 bg-background/80 backdrop-blur-md">
        <div className="mx-auto flex h-16 w-full max-w-[1800px] items-center justify-between px-6 sm:px-8 lg:px-12 xl:px-20">
          <Link
            to="/"
            onClick={(event) => {
              event.preventDefault();
              window.scrollTo({ top: 0, behavior: "smooth" });
            }}
            className="flex items-center gap-2.5 rounded-md transition-opacity hover:opacity-80"
          >
            <img
              src={brgyLogo}
              alt="Barangay 178 Logo"
              className="size-9 rounded-full object-cover"
            />
            <div className="leading-tight">
              <p className="font-display text-sm font-bold">{APP_NAME}</p>
              <p className="flex items-center gap-1 text-xs text-muted-foreground">
                <MapPin className="size-3" />
                {APP_SUBTITLE}
              </p>
            </div>
          </Link>

          <nav className="hidden items-center gap-8 text-sm font-medium text-muted-foreground md:flex">
            <a
              href="#how-to-apply"
              className="transition-colors hover:text-foreground"
            >
              How to Apply
            </a>
            <a
              href="#categories"
              className="transition-colors hover:text-foreground"
            >
              Categories
            </a>
            <a
              href="#requirements"
              className="transition-colors hover:text-foreground"
            >
              Requirements
            </a>
          </nav>

          <div className="flex items-center gap-2">
            <button
              type="button"
              onClick={() => setTheme(theme === "dark" ? "light" : "dark")}
              aria-label={
                theme === "dark"
                  ? "Switch to light mode"
                  : "Switch to dark mode"
              }
              className="flex size-9 shrink-0 items-center justify-center rounded-lg border border-border/70 text-muted-foreground transition-colors hover:bg-secondary hover:text-foreground"
            >
              {theme === "dark" ? (
                <Sun className="size-4" />
              ) : (
                <Moon className="size-4" />
              )}
            </button>
            <Button
              size="sm"
              nativeButton={false}
              render={<Link to="/login" />}
            >
              Login
            </Button>
          </div>
        </div>
      </header>

      <main className="flex-1">
        {/* ------------------------------------------------------------- */}
        {/* Hero                                                          */}
        {/* ------------------------------------------------------------- */}
        <section className="relative overflow-hidden">
          <div
            aria-hidden="true"
            className="pointer-events-none absolute inset-0 bg-linear-to-b from-primary/[0.07] via-background to-background"
          />
          <div
            aria-hidden="true"
            className="pointer-events-none absolute -top-24 right-0 size-112 rounded-full bg-primary/15 blur-3xl"
          />
          <div
            aria-hidden="true"
            className="pointer-events-none absolute -bottom-32 left-0 size-80 rounded-full bg-accent/10 blur-3xl"
          />

          <div className="relative mx-auto grid w-full max-w-[1800px] items-center gap-14 px-6 py-20 sm:px-8 md:py-28 lg:grid-cols-2 lg:gap-10 lg:px-12 xl:px-20">
            <Reveal className="space-y-7">
              <div className="inline-flex items-center gap-2 rounded-full border border-primary/30 bg-primary/10 px-3 py-1 text-xs font-medium text-primary">
                <ShieldCheck className="size-3.5" />
                Online inspection &amp; clearance applications
              </div>
              <h1 className="text-4xl font-extrabold leading-[1.1] tracking-tight md:text-5xl lg:text-[3.25rem]">
                Get your Barangay Health &amp; Safety{" "}
                <span className="text-primary">Clearance</span> online
              </h1>
              <p className="max-w-xl text-base leading-relaxed text-muted-foreground md:text-lg">
                Residents of Barangay 178 can now apply for inspections and
                clearances without visiting the barangay hall. Register, submit
                your request, and track everything from the resident portal.
              </p>
              <div className="flex flex-wrap gap-3 pt-1">
                <Button
                  size="lg"
                  className="h-11 px-6 text-base shadow-sm transition-transform duration-200 hover:-translate-y-0.5"
                  nativeButton={false}
                  render={<Link to="/login" />}
                >
                  Apply Now as a Resident
                  <ArrowRight className="size-4" />
                </Button>
                <Button
                  variant="outline"
                  size="lg"
                  className="h-11 px-6 text-base transition-transform duration-200 hover:-translate-y-0.5"
                  nativeButton={false}
                  render={<Link to="/register" />}
                >
                  Create an Account
                </Button>
              </div>
              <p className="text-sm text-muted-foreground">
                Already a resident?{" "}
                <Link
                  to="/login"
                  className="font-medium text-primary underline-offset-4 hover:underline"
                >
                  Login
                </Link>
              </p>
            </Reveal>

            {/* Hero visual: a stylized preview of the digital clearance, */}
            {/* the one artifact this whole system produces.              */}
            <Reveal className="relative mx-auto w-full max-w-md pt-7 pb-7 sm:max-w-lg sm:pt-8 sm:pb-8 lg:mx-0 lg:max-w-xl">
              <div className="absolute -top-1 left-4 z-20 hidden items-center gap-2.5 rounded-xl border border-border/70 bg-card px-3.5 py-2.5 shadow-lg sm:flex sm:left-8">
                <div className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                  <ClipboardCheck className="size-4" />
                </div>
                <div className="leading-tight">
                  <p className="text-sm font-bold">1,200+</p>
                  <p className="text-[11px] text-muted-foreground">
                    Inspections done
                  </p>
                </div>
              </div>

              <Card className="relative z-10 w-full overflow-hidden border-border/70 py-0 shadow-xl">
                <CardContent className="space-y-5 p-5 sm:p-7">
                  <div className="flex items-start justify-between gap-3">
                    <div className="flex items-center gap-2.5">
                      <img
                        src={brgyLogo}
                        alt="Barangay 178 Logo"
                        className="size-10 shrink-0 rounded-full object-cover"
                      />
                      <div className="leading-tight">
                        <p className="text-sm font-bold sm:text-base">
                          Health &amp; Safety Clearance
                        </p>
                        <p className="text-xs text-muted-foreground">
                          Barangay 178, North Caloocan
                        </p>
                      </div>
                    </div>
                    <span className="inline-flex shrink-0 items-center gap-1 rounded-full bg-accent/10 px-2.5 py-1 text-[11px] font-semibold text-accent">
                      <CheckCircle2 className="size-3" />
                      Approved
                    </span>
                  </div>

                  <div className="grid grid-cols-2 gap-3 rounded-lg border border-border/70 bg-secondary/40 p-4 text-sm sm:p-5">
                    <div>
                      <p className="text-[11px] uppercase tracking-wide text-muted-foreground">
                        Establishment
                      </p>
                      <p className="font-medium">Sample Carinderia</p>
                    </div>
                    <div>
                      <p className="text-[11px] uppercase tracking-wide text-muted-foreground">
                        Category
                      </p>
                      <p className="font-medium">Food Establishment</p>
                    </div>
                    <div>
                      <p className="text-[11px] uppercase tracking-wide text-muted-foreground">
                        Valid Until
                      </p>
                      <p className="font-medium">June 2027</p>
                    </div>
                    <div>
                      <p className="text-[11px] uppercase tracking-wide text-muted-foreground">
                        Status
                      </p>
                      <p className="font-medium text-accent">Active</p>
                    </div>
                  </div>

                  <div className="flex items-center justify-between gap-3 rounded-lg border border-dashed border-border/70 p-4 sm:p-5">
                    <div>
                      <p className="text-sm font-medium">Scan to verify</p>
                      <p className="text-xs text-muted-foreground">
                        Instantly check clearance validity
                      </p>
                    </div>
                    <div className="flex size-14 shrink-0 items-center justify-center rounded-lg bg-foreground/5 text-foreground">
                      <QrCode className="size-8" strokeWidth={1.5} />
                    </div>
                  </div>
                </CardContent>
              </Card>

              <div className="absolute -bottom-1 right-4 z-20 hidden items-center gap-2 rounded-xl border border-border/70 bg-card px-3.5 py-2.5 shadow-lg sm:flex sm:right-8">
                <div className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-accent/10 text-accent">
                  <ShieldCheck className="size-4" />
                </div>
                <div className="leading-tight">
                  <p className="text-sm font-bold">QR Verified</p>
                  <p className="text-[11px] text-muted-foreground">
                    Tamper-proof clearance
                  </p>
                </div>
              </div>
            </Reveal>
          </div>
        </section>

        {/* ------------------------------------------------------------- */}
        {/* Process / How the application works                          */}
        {/* ------------------------------------------------------------- */}
        <section
          id="how-to-apply"
          className="border-y border-border/70 bg-surface"
        >
          <div className="mx-auto w-full max-w-[1800px] px-6 sm:px-8 lg:px-12 xl:px-20 py-16 md:py-20">
            <Reveal className="mx-auto mb-14 max-w-2xl space-y-2 text-center">
              <h2 className="text-2xl font-bold tracking-tight md:text-3xl">
                How the application works
              </h2>
              <p className="text-muted-foreground">
                A simple, four-step process from account creation to clearance.
              </p>
            </Reveal>

            <div className="relative grid gap-10 sm:grid-cols-2 lg:grid-cols-4 lg:gap-6">
              <div
                aria-hidden="true"
                className="absolute left-0 right-0 top-6 hidden h-px bg-border lg:block"
              />
              {steps.map((step, idx) => {
                const Icon = step.icon;
                return (
                  <Reveal
                    key={step.title}
                    className="relative flex flex-col items-center text-center"
                  >
                    <div className="relative z-10 flex size-12 shrink-0 items-center justify-center rounded-full border border-primary/30 bg-primary/10 text-primary shadow-sm">
                      <Icon className="size-5" />
                    </div>
                    <span className="mt-4 text-xs font-semibold uppercase tracking-wide text-primary">
                      Step {idx + 1}
                    </span>
                    <p className="mt-1.5 text-sm font-semibold">{step.title}</p>
                    <p className="mt-1.5 text-sm leading-relaxed text-muted-foreground">
                      {step.description}
                    </p>
                  </Reveal>
                );
              })}
            </div>
          </div>
        </section>

        {/* ------------------------------------------------------------- */}
        {/* Stats                                                         */}
        {/* ------------------------------------------------------------- */}
        <section className="mx-auto w-full max-w-[1800px] px-6 sm:px-8 lg:px-12 xl:px-20 py-16">
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            {stats.map((stat, idx) => {
              const Icon = stat.icon;
              const isAccent = idx % 2 === 0;
              return (
                <Reveal key={stat.label}>
                  <Card className="h-full border-border/70 text-center transition-all duration-300 hover:-translate-y-1 hover:shadow-lg">
                    <CardContent className="flex flex-col items-center gap-2 py-8">
                      <div
                        className={`flex size-11 items-center justify-center rounded-xl ${
                          isAccent
                            ? "bg-accent/10 text-accent"
                            : "bg-primary/10 text-primary"
                        }`}
                      >
                        <Icon className="size-5" />
                      </div>
                      <p className="text-2xl font-extrabold tracking-tight">
                        {stat.value}
                      </p>
                      <p className="text-sm text-muted-foreground">
                        {stat.label}
                      </p>
                    </CardContent>
                  </Card>
                </Reveal>
              );
            })}
          </div>
        </section>

        {/* ------------------------------------------------------------- */}
        {/* Why use our system                                            */}
        {/* ------------------------------------------------------------- */}

        {/* ------------------------------------------------------------- */}
        {/* Categories                                                    */}
        {/* ------------------------------------------------------------- */}
        <section
          id="categories"
          className="mx-auto w-full max-w-[1800px] px-6 sm:px-8 lg:px-12 xl:px-20 py-16 md:py-20"
        >
          <Reveal className="mb-10 max-w-2xl space-y-2">
            <h2 className="text-2xl font-bold tracking-tight md:text-3xl">
              Supported inspection categories
            </h2>
            <p className="text-muted-foreground">
              Choose the category that matches your establishment or facility.
            </p>
          </Reveal>
          <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
            {categories.map((category) => {
              const Icon = category.icon;
              return (
                <Reveal key={category.title}>
                  <Card className="flex h-full flex-col border-border/70 transition-all duration-300 hover:-translate-y-1 hover:border-primary/40 hover:shadow-lg">
                    <CardHeader className="flex-1">
                      <div className="mb-3 flex size-11 items-center justify-center rounded-xl bg-primary/10 text-primary">
                        <Icon className="size-5" />
                      </div>
                      <CardTitle className="text-base">
                        {category.title}
                      </CardTitle>
                      <CardDescription className="leading-relaxed">
                        {category.description}
                      </CardDescription>
                    </CardHeader>
                  </Card>
                </Reveal>
              );
            })}
          </div>
        </section>

        {/* ------------------------------------------------------------- */}
        {/* Requirements                                                  */}
        {/* ------------------------------------------------------------- */}
        <section
          id="requirements"
          className="border-y border-border/70 bg-surface"
        >
          <div className="mx-auto w-full max-w-[1800px] px-6 sm:px-8 lg:px-12 xl:px-20 py-16 md:py-20">
            <div className="grid items-center gap-10 md:grid-cols-2">
              <Reveal className="space-y-5">
                <h2 className="text-2xl font-bold tracking-tight md:text-3xl">
                  What you need to apply
                </h2>
                <p className="text-muted-foreground">
                  General requirements apply to all applicants. Additional
                  documents are requested based on your inspection category and
                  application type.
                </p>
                <ul className="space-y-3">
                  {requirements.map((requirement) => (
                    <li
                      key={requirement}
                      className="flex items-start gap-3 rounded-lg border border-border/70 bg-background px-4 py-3 text-sm"
                    >
                      <CheckCircle2 className="mt-0.5 size-4 shrink-0 text-accent" />
                      {requirement}
                    </li>
                  ))}
                </ul>
              </Reveal>

              <Reveal>
                <Card className="border-accent/30 bg-secondary/60">
                  <CardHeader>
                    <CardTitle className="flex items-center gap-2 text-base">
                      <ShieldCheck className="size-4 text-accent" />
                      Ready to get started?
                    </CardTitle>
                    <CardDescription>
                      Applying takes only a few minutes from the resident
                      portal.
                    </CardDescription>
                  </CardHeader>
                  <CardContent className="space-y-3">
                    <Button
                      className="h-10 w-full text-base"
                      nativeButton={false}
                      render={<Link to="/login" />}
                    >
                      Go to Resident Login
                      <ArrowRight className="size-4" />
                    </Button>
                    <Button
                      variant="outline"
                      className="h-10 w-full"
                      nativeButton={false}
                      render={<Link to="/register" />}
                    >
                      Register a New Account
                    </Button>
                  </CardContent>
                </Card>
              </Reveal>
            </div>
          </div>
        </section>

        {/* ------------------------------------------------------------- */}
        {/* Final CTA                                                     */}
        {/* ------------------------------------------------------------- */}
        <section className="mx-auto w-full max-w-[1800px] px-6 sm:px-8 lg:px-12 xl:px-20 py-16 md:py-20">
          <Reveal>
            <div className="relative overflow-hidden rounded-3xl bg-primary px-6 py-14 text-center shadow-xl sm:px-12 md:py-16">
              <div
                aria-hidden="true"
                className="pointer-events-none absolute -right-10 -top-10 size-64 rounded-full bg-white/10"
              />
              <div
                aria-hidden="true"
                className="pointer-events-none absolute -bottom-16 -left-10 size-72 rounded-full bg-white/10"
              />
              <div className="relative mx-auto max-w-2xl space-y-4">
                <h2 className="text-2xl font-extrabold tracking-tight text-primary-foreground md:text-3xl">
                  Ready to apply?
                </h2>
                <p className="text-primary-foreground/80 md:text-lg">
                  Create your resident account today and get your health &amp;
                  safety clearance without leaving home.
                </p>
                <div className="flex flex-wrap justify-center gap-3 pt-2">
                  <Button
                    size="lg"
                    className="h-11 bg-background px-6 text-base text-foreground shadow-sm transition-transform duration-200 hover:-translate-y-0.5 hover:bg-background/90"
                    nativeButton={false}
                    render={<Link to="/login" />}
                  >
                    Apply Now
                    <ArrowRight className="size-4" />
                  </Button>
                  <Button
                    size="lg"
                    variant="outline"
                    className="h-11 border-primary-foreground/30 bg-transparent px-6 text-base text-primary-foreground transition-transform duration-200 hover:-translate-y-0.5 hover:bg-white/10"
                    nativeButton={false}
                    render={<Link to="/register" />}
                  >
                    Create Account
                  </Button>
                </div>
              </div>
            </div>
          </Reveal>
        </section>
      </main>

      <footer className="border-t border-border/70 bg-surface">
        <div className="mx-auto w-full max-w-[1800px] px-6 py-14 sm:px-8 lg:px-12 xl:px-20">
          <div className="grid gap-10 sm:grid-cols-2 lg:grid-cols-4">
            <div className="space-y-3 sm:col-span-2 lg:col-span-1">
              <div className="flex items-center gap-2.5">
                <img
                  src={brgyLogo}
                  alt="Barangay 178 Logo"
                  className="size-9 rounded-full object-cover"
                />
                <p className="font-display text-sm font-bold">{APP_NAME}</p>
              </div>
              <p className="text-sm leading-relaxed text-muted-foreground">
                {APP_SUBTITLE}. Online inspection and clearance applications for
                residents and establishments.
              </p>
            </div>

            <div className="space-y-3">
              <p className="text-sm font-semibold">Quick Links</p>
              <ul className="space-y-2 text-sm text-muted-foreground">
                <li>
                  <a
                    href="#how-to-apply"
                    className="transition-colors hover:text-foreground"
                  >
                    How to Apply
                  </a>
                </li>
                <li>
                  <a
                    href="#categories"
                    className="transition-colors hover:text-foreground"
                  >
                    Categories
                  </a>
                </li>
                <li>
                  <a
                    href="#requirements"
                    className="transition-colors hover:text-foreground"
                  >
                    Requirements
                  </a>
                </li>
              </ul>
            </div>

            <div className="space-y-3">
              <p className="text-sm font-semibold">Contact</p>
              <ul className="space-y-2 text-sm text-muted-foreground">
                <li className="flex items-center gap-2">
                  <MapPin className="size-4 shrink-0 text-primary" />
                  Barangay 178, North Caloocan City
                </li>
                <li className="flex items-center gap-2">
                  <Phone className="size-4 shrink-0 text-primary" />
                  (+63) 9810488946
                </li>
                <li className="flex items-center gap-2">
                  <Mail className="size-4 shrink-0 text-primary" />
                  barangay178.dev@gmail.com
                </li>
              </ul>
            </div>

            <div className="space-y-3">
              <p className="text-sm font-semibold">Office Hours</p>
              <ul className="space-y-2 text-sm text-muted-foreground">
                <li className="flex items-center gap-2">
                  <Clock className="size-4 shrink-0 text-primary" />
                  Mon – Fri, 8:00 AM – 5:00 PM
                </li>
                <li className="pl-6 text-muted-foreground/80">
                  Closed on weekends and holidays
                </li>
              </ul>
            </div>
          </div>

          <div className="mt-10 flex flex-col items-center justify-between gap-3 border-t border-border/70 pt-6 text-center text-xs text-muted-foreground sm:flex-row sm:text-left">
            <p>
              © {new Date().getFullYear()} {APP_NAME}. All rights reserved.
            </p>
            <p>
              For assistance, visit the barangay hall or contact your barangay
              office.
            </p>
          </div>
        </div>
      </footer>
    </div>
  );
}
