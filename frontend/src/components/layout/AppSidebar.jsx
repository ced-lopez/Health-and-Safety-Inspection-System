import { Link, useLocation } from 'react-router-dom'
import {
  AlertTriangle,
  Award,
  Building2,
  ClipboardCheck,
  LayoutDashboard,
  MapPin,
  ShieldCheck,
} from 'lucide-react'

import { useAuth } from '@/context/AuthContext'
import { APP_NAME, APP_SUBTITLE, NAV_ITEMS } from '@/utils/constants'
import { Badge } from '@/components/ui/badge'
import { cn } from '@/lib/utils'
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
} from '@/components/ui/sidebar'

const iconMap = {
  LayoutDashboard,
  Building2,
  ClipboardCheck,
  AlertTriangle,
  Award,
}

export function AppSidebar() {
  const location = useLocation()
  const { canAccess, user } = useAuth()

  const visibleItems = NAV_ITEMS.filter((item) => canAccess(item.module))
  const initials = (user?.name ?? 'User')
    .split(' ')
    .map((part) => part[0])
    .join('')
    .slice(0, 2)
    .toUpperCase()

  return (
    <Sidebar collapsible="icon" className="border-sidebar-border/80">
      <SidebarHeader className="px-3 pb-3 pt-4">
        <div className="flex items-center gap-3 rounded-lg border border-sidebar-border/70 bg-sidebar-accent/35 p-2.5 group-data-[collapsible=icon]:justify-center group-data-[collapsible=icon]:border-transparent group-data-[collapsible=icon]:bg-transparent group-data-[collapsible=icon]:p-0">
          <div className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-sidebar-primary text-sidebar-primary-foreground shadow-sm">
            <ShieldCheck className="size-5" />
          </div>
          <div className="min-w-0 group-data-[collapsible=icon]:hidden">
            <p className="truncate font-display text-sm font-bold leading-tight text-sidebar-foreground">
              {APP_NAME}
            </p>
            <p className="mt-0.5 flex items-center gap-1 truncate text-xs text-sidebar-foreground/60">
              <MapPin className="size-3" />
              <span className="truncate">{APP_SUBTITLE}</span>
            </p>
          </div>
        </div>
      </SidebarHeader>

      <SidebarSeparator className="mx-3 group-data-[collapsible=icon]:mx-2" />

      <SidebarContent className="px-2 py-3">
        <SidebarGroup className="gap-1 p-0">
          <SidebarGroupLabel className="px-2 text-[0.7rem] font-bold uppercase tracking-wider text-sidebar-foreground/45">
            Main Menu
          </SidebarGroupLabel>
          <SidebarGroupContent>
            <SidebarMenu className="gap-1">
              {visibleItems.map((item) => {
                const Icon = iconMap[item.icon]
                const isActive =
                  location.pathname === item.href ||
                  location.pathname.startsWith(`${item.href}/`)

                return (
                  <SidebarMenuItem key={item.href}>
                    <SidebarMenuButton
                      render={<Link to={item.href} />}
                      isActive={isActive}
                      tooltip={item.title}
                      className={cn(
                        'h-10 rounded-lg px-3 text-sidebar-foreground/75 transition-colors',
                        'hover:bg-sidebar-accent hover:text-sidebar-accent-foreground',
                        'data-active:bg-sidebar-primary data-active:text-sidebar-primary-foreground data-active:shadow-sm',
                        'group-data-[collapsible=icon]:size-9 group-data-[collapsible=icon]:justify-center',
                      )}
                    >
                      <Icon className="size-4" />
                      <span className="font-medium">{item.title}</span>
                    </SidebarMenuButton>
                  </SidebarMenuItem>
                )
              })}
            </SidebarMenu>
          </SidebarGroupContent>
        </SidebarGroup>
      </SidebarContent>

      <SidebarFooter className="px-3 py-3">
        <div className="rounded-lg border border-sidebar-border/70 bg-sidebar-accent/35 p-2.5 group-data-[collapsible=icon]:border-transparent group-data-[collapsible=icon]:bg-transparent group-data-[collapsible=icon]:p-0">
          <div className="flex items-center gap-2.5 group-data-[collapsible=icon]:justify-center">
            <div className="flex size-9 shrink-0 items-center justify-center rounded-full bg-sidebar-primary/10 text-xs font-bold text-sidebar-primary ring-1 ring-sidebar-primary/20">
              {initials}
            </div>
            <div className="min-w-0 flex-1 group-data-[collapsible=icon]:hidden">
              <p className="truncate text-sm font-semibold text-sidebar-foreground">
                {user?.name ?? 'Signed in'}
              </p>
              <div className="mt-1 flex items-center gap-2">
                <Badge
                  variant="secondary"
                  className="h-5 max-w-full truncate rounded-md px-1.5 text-[0.68rem]"
                >
                  {user?.role?.name ?? 'Health & Safety'}
                </Badge>
              </div>
            </div>
          </div>
        </div>
      </SidebarFooter>

      <SidebarRail />
    </Sidebar>
  )
}
