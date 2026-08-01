import { useMemo, useState } from "react";
import { Link, useLocation, useNavigate } from "react-router-dom";
import {
  AlertTriangle,
  Award,
  BarChart,
  Bell,
  Building2,
  CalendarDays,
  CheckSquare,
  ChevronsUpDown,
  ClipboardCheck,
  FileText,
  History,
  LayoutDashboard,
  LogOut,
  MapPin,
  RefreshCw,
  Search,
  Settings,
  User,
  Users,
} from "lucide-react";

import { useAuth } from "@/context/AuthContext";
import { toast } from "sonner";
import {
  APP_NAME,
  APP_SUBTITLE,
  STAFF_NAV_ITEMS,
  RESIDENT_NAV_ITEMS,
} from "@/utils/constants";
import { Badge } from "@/components/ui/badge";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { cn } from "@/lib/utils";
import {
  Sidebar,
  SidebarContent,
  SidebarFooter,
  SidebarGroup,
  SidebarGroupContent,
  SidebarGroupLabel,
  SidebarHeader,
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
  SidebarRail,
  SidebarSeparator,
} from "@/components/ui/sidebar";
import brgyLogo from "@/assets/brgy178logo.jpg";

const iconMap = {
  AlertTriangle,
  Award,
  BarChart,
  Bell,
  Building2,
  CalendarDays,
  CheckSquare,
  ClipboardCheck,
  FileText,
  History,
  LayoutDashboard,
  RefreshCw,
  Settings,
  User,
  Users,
};

// Fallback grouping used when a nav item doesn't declare its own `group`.
// This lets the sidebar ship with sections immediately even before
// STAFF_NAV_ITEMS / RESIDENT_NAV_ITEMS in constants.js are updated to
// include a `group` field per item.
const DEFAULT_GROUP_MAP = {
  "/dashboard": "Overview",
  "/inspection-requests": "Operations",
  "/establishments": "Operations",
  "/inspections": "Operations",
  "/scheduling": "Operations",
  "/checklists": "Compliance",
  "/documents": "Compliance",
  "/violations": "Compliance",
  "/certifications": "Compliance",
  "/reports": "Insights",
  "/audit-logs": "Insights",
  "/users": "Admin",
};

const GROUP_ORDER = [
  "Overview",
  "Operations",
  "Compliance",
  "Insights",
  "Admin",
];

function groupNavItems(items) {
  const groups = new Map();

  items.forEach((item) => {
    const groupName = item.group ?? DEFAULT_GROUP_MAP[item.href] ?? "Main Menu";
    if (!groups.has(groupName)) groups.set(groupName, []);
    groups.get(groupName).push(item);
  });

  // Sort by known order, then append any unknown groups at the end.
  const orderedNames = [
    ...GROUP_ORDER.filter((name) => groups.has(name)),
    ...[...groups.keys()].filter((name) => !GROUP_ORDER.includes(name)),
  ];

  return orderedNames.map((name) => ({ name, items: groups.get(name) }));
}

export function AppSidebar({ unassignedCount } = {}) {
  const location = useLocation();
  const navigate = useNavigate();
  const { canAccess, user, logout } = useAuth();
  const [query, setQuery] = useState("");

  const isResident = user?.role?.slug === "resident";
  const navItems = isResident ? RESIDENT_NAV_ITEMS : STAFF_NAV_ITEMS;
  const visibleItems = navItems
    .filter((item) => canAccess(item.module))
    .map((item) =>
      // Scheduling Calendar is where approved inspections get an inspector
      // assigned, so surface how many are still waiting for assignment.
      item.href === "/scheduling-calendar" &&
      typeof unassignedCount === "number"
        ? { ...item, badge: unassignedCount }
        : item,
    );

  const filteredItems = useMemo(() => {
    if (!query.trim()) return visibleItems;
    const q = query.trim().toLowerCase();
    return visibleItems.filter((item) => item.title.toLowerCase().includes(q));
  }, [visibleItems, query]);

  const groupedItems = useMemo(
    () => groupNavItems(filteredItems),
    [filteredItems],
  );

  const initials = (user?.name ?? "User")
    .split(" ")
    .map((part) => part[0])
    .join("")
    .slice(0, 2)
    .toUpperCase();

  async function handleLogout() {
    try {
      await logout();
      navigate(user?.role?.slug === "resident" ? "/login" : "/admin/login", {
        replace: true,
      });
    } catch {
      toast.error("Unable to sign out");
    }
  }

  return (
    <Sidebar collapsible="icon" className="border-sidebar-border/80">
      <SidebarHeader className="h-16 justify-center px-3 py-2 group-data-[collapsible=icon]:px-2">
        <div className="flex h-full items-center gap-3 rounded-lg border border-sidebar-border/70 bg-sidebar-accent/35 px-2.5 group-data-[collapsible=icon]:justify-center group-data-[collapsible=icon]:border-transparent group-data-[collapsible=icon]:bg-transparent group-data-[collapsible=icon]:p-0">
          <div className="flex size-8 shrink-0 items-center justify-center overflow-hidden rounded-full group-data-[collapsible=icon]:size-8">
            <img
              src={brgyLogo}
              alt="Barangay 178 Logo"
              className="size-full object-cover"
            />
          </div>
          <div className="min-w-0 group-data-[collapsible=icon]:hidden">
            {/* Single line keeps header height locked to the navbar's h-16;
                full name still available via title tooltip on hover */}
            <p
              title={APP_NAME}
              className="truncate font-display text-sm font-bold leading-tight text-sidebar-foreground"
            >
              {APP_NAME}
            </p>
            <p className="mt-0.5 flex items-center gap-1 truncate text-xs text-sidebar-foreground/60">
              <MapPin className="size-3 shrink-0" />
              <span className="truncate">{APP_SUBTITLE}</span>
            </p>
          </div>
        </div>
      </SidebarHeader>

      <SidebarSeparator className="mx-3 group-data-[collapsible=icon]:mx-2" />

      {/* Quick-jump search */}
      <div className="px-3 pt-3 group-data-[collapsible=icon]:hidden">
        <div className="relative">
          <Search className="pointer-events-none absolute left-2.5 top-1/2 size-3.5 -translate-y-1/2 text-sidebar-foreground/40" />
          <input
            type="text"
            value={query}
            onChange={(e) => setQuery(e.target.value)}
            placeholder="Search menu..."
            className="h-8 w-full rounded-md border border-sidebar-border/70 bg-sidebar-accent/25 pl-8 pr-2 text-xs text-sidebar-foreground placeholder:text-sidebar-foreground/40 outline-none focus:border-sidebar-primary/50"
          />
        </div>
      </div>

      <SidebarContent className="px-2 py-3">
        {groupedItems.map((group) => (
          <SidebarGroup key={group.name} className="gap-1 p-0 mb-2">
            <SidebarGroupLabel className="px-2 text-[0.7rem] font-bold uppercase tracking-wider text-sidebar-foreground/45">
              {group.name}
            </SidebarGroupLabel>
            <SidebarGroupContent>
              <SidebarMenu className="gap-1">
                {group.items.map((item) => {
                  const Icon = iconMap[item.icon];
                  const isActive =
                    location.pathname === item.href ||
                    location.pathname.startsWith(`${item.href}/`);

                  return (
                    <SidebarMenuItem key={item.href}>
                      <SidebarMenuButton
                        render={<Link to={item.href} />}
                        isActive={isActive}
                        tooltip={item.title}
                        className={cn(
                          "relative h-10 rounded-lg px-3 text-sidebar-foreground/75 transition-colors",
                          "hover:bg-sidebar-accent hover:text-sidebar-accent-foreground",
                          "data-active:bg-sidebar-primary data-active:text-sidebar-primary-foreground data-active:shadow-sm",
                          // Left accent bar on the active item, in addition to the fill
                          "data-active:before:absolute data-active:before:left-0 data-active:before:top-1/2 data-active:before:h-5 data-active:before:w-1 data-active:before:-translate-y-1/2 data-active:before:rounded-full data-active:before:bg-sidebar-primary-foreground/70 data-active:before:content-['']",
                          "group-data-[collapsible=icon]:size-9 group-data-[collapsible=icon]:justify-center",
                        )}
                      >
                        <Icon className="size-4 shrink-0" />
                        <span className="flex-1 truncate font-medium">
                          {item.title}
                        </span>
                        {typeof item.badge === "number" && item.badge > 0 && (
                          <Badge
                            variant="secondary"
                            className="ml-auto h-5 min-w-5 shrink-0 justify-center rounded-full px-1.5 text-[0.68rem] group-data-[collapsible=icon]:hidden"
                          >
                            {item.badge > 99 ? "99+" : item.badge}
                          </Badge>
                        )}
                      </SidebarMenuButton>
                    </SidebarMenuItem>
                  );
                })}
              </SidebarMenu>
            </SidebarGroupContent>
          </SidebarGroup>
        ))}

        {groupedItems.length === 0 && (
          <p className="px-3 py-6 text-center text-xs text-sidebar-foreground/50">
            No menu items match "{query}"
          </p>
        )}
      </SidebarContent>

      <SidebarFooter className="px-3 py-3">
        <DropdownMenu>
          <DropdownMenuTrigger className="w-full">
            <div className="w-full cursor-pointer rounded-lg border border-sidebar-border/70 bg-sidebar-accent/35 p-2.5 text-left transition-colors hover:bg-sidebar-accent/55 group-data-[collapsible=icon]:border-transparent group-data-[collapsible=icon]:bg-transparent group-data-[collapsible=icon]:p-0">
              <div className="flex items-center gap-2.5 group-data-[collapsible=icon]:justify-center">
                <div className="flex size-9 shrink-0 items-center justify-center rounded-full bg-sidebar-primary/10 text-xs font-bold text-sidebar-primary ring-1 ring-sidebar-primary/20">
                  {initials}
                </div>
                <div className="min-w-0 flex-1 group-data-[collapsible=icon]:hidden">
                  <p className="truncate text-sm font-semibold text-sidebar-foreground">
                    {user?.name ?? "Signed in"}
                  </p>
                  <div className="mt-1 flex items-center gap-2">
                    <Badge
                      variant="secondary"
                      className="h-5 max-w-full truncate rounded-md px-1.5 text-[0.68rem]"
                    >
                      {user?.role?.name ?? "Health & Safety"}
                    </Badge>
                  </div>
                </div>
                <ChevronsUpDown className="size-3.5 shrink-0 text-sidebar-foreground/40 group-data-[collapsible=icon]:hidden" />
              </div>
            </div>
          </DropdownMenuTrigger>
          <DropdownMenuContent side="top" align="start" className="w-56">
            <DropdownMenuItem
              onClick={() => navigate("/settings")}
              className="flex items-center gap-2"
            >
              <Settings className="size-4" />
              Settings
            </DropdownMenuItem>
            <DropdownMenuSeparator />
            <DropdownMenuItem
              onClick={handleLogout}
              className="flex items-center gap-2 text-destructive focus:text-destructive"
            >
              <LogOut className="size-4" />
              Log Out
            </DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>
      </SidebarFooter>

      <SidebarRail />
    </Sidebar>
  );
}
