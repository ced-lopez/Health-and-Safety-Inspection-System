import { useNavigate } from "react-router-dom";
import {
  Bell,
  BellRing,
  CheckCheck,
  LogOut,
  Settings,
  Trash2,
  User,
} from "lucide-react";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";

import { useAuth } from "@/context/AuthContext";
import {
  fetchNotifications,
  markAllNotificationsRead,
  markNotificationRead,
  deleteNotification,
} from "@/services/notificationService";
import { Badge } from "@/components/ui/badge";
import { buttonVariants } from "@/components/ui/button";
import { getNotificationHref } from "@/utils/notificationLinks";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuGroup,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { SidebarTrigger } from "@/components/ui/sidebar";
import { Separator } from "@/components/ui/separator";
import { cn } from "@/lib/utils";

function formatRelativeTime(value) {
  if (!value) return "";

  const date = new Date(value);
  const seconds = Math.floor((Date.now() - date.getTime()) / 1000);

  if (seconds < 60) return "just now";
  if (seconds < 3600) return `${Math.floor(seconds / 60)}m ago`;
  if (seconds < 86400) return `${Math.floor(seconds / 3600)}h ago`;
  if (seconds < 604800) return `${Math.floor(seconds / 86400)}d ago`;

  return date.toLocaleDateString();
}

export function AppNavbar() {
  const { user, logout } = useAuth();
  const navigate = useNavigate();
  const queryClient = useQueryClient();

  const isResident = user?.role?.slug === "resident";

  const { data: notificationData, refetch: refetchNotifications } = useQuery({
    queryKey: ["notifications-bell"],
    queryFn: () => fetchNotifications({ per_page: 10 }),
  });

  const rawNotifications =
    notificationData?.data?.notifications ??
    notificationData?.notifications ??
    notificationData?.data ??
    [];
  const notifications = Array.isArray(rawNotifications) ? rawNotifications : [];
  const unreadCount =
    notificationData?.data?.unread_count ??
    notificationData?.unread_count ??
    notifications.filter((n) => !n.read_at).length;

  function handleOpenChange(open) {
    if (open) {
      refetchNotifications();
    }
  }

  function markReadLocally(id) {
    queryClient.setQueryData(["notifications-bell"], (old) => {
      if (!old) return old;

      const now = new Date().toISOString();
      const updateList = (list) =>
        list.map((n) =>
          n.id === id && !n.read_at ? { ...n, read_at: now } : n,
        );

      if (old.data && Array.isArray(old.data.notifications)) {
        const updated = updateList(old.data.notifications);
        return {
          ...old,
          data: {
            ...old.data,
            notifications: updated,
            unread_count: updated.filter((n) => !n.read_at).length,
          },
        };
      }

      const updated = updateList(old.notifications);
      return {
        ...old,
        notifications: updated,
        unread_count: updated.filter((n) => !n.read_at).length,
      };
    });
  }

  function markAllReadLocally() {
    queryClient.setQueryData(["notifications-bell"], (old) => {
      if (!old) return old;

      const source = old.data?.notifications ?? old.notifications;
      if (!Array.isArray(source)) return old;

      const now = new Date().toISOString();
      const updated = source.map((n) => ({ ...n, read_at: n.read_at ?? now }));

      if (old.data && Array.isArray(old.data.notifications)) {
        return {
          ...old,
          data: { ...old.data, notifications: updated, unread_count: 0 },
        };
      }

      return { ...old, notifications: updated, unread_count: 0 };
    });
  }

  async function handleNotificationClick(notification) {
    navigate(getNotificationHref(notification, user?.role?.slug));

    if (!notification.read_at) {
      try {
        markReadLocally(notification.id);
        await markNotificationRead(notification.id);
        await refetchNotifications();
      } catch (err) {
        if (err?.response?.status !== 404) {
          toast.error("Unable to update notification");
        }
      }
    }
  }

  async function handleMarkAllRead() {
    try {
      markAllReadLocally();
      await markAllNotificationsRead();
      await refetchNotifications();
      toast.success("All notifications marked as read");
    } catch {
      toast.error("Unable to mark all as read");
    }
  }

  async function handleDeleteNotification(id) {
    try {
      await deleteNotification(id);
      toast.success("Notification deleted");
    } catch (err) {
      // 404 means the notification was already removed elsewhere — keep the
      // list in sync silently instead of alarming the user.
      if (err?.response?.status !== 404) {
        toast.error("Unable to delete notification");
      }
    } finally {
      refetchNotifications();
    }
  }

  async function handleLogout() {
    try {
      await logout();
      toast.success("Signed out successfully");
      navigate("/login", {
        replace: true,
      });
    } catch {
      toast.error("Unable to sign out");
    }
  }

  return (
    <header className="sticky top-0 z-20 flex h-16 shrink-0 items-center gap-3 border-b border-border bg-surface/95 px-4 backdrop-blur supports-backdrop-filter:bg-surface/80">
      <SidebarTrigger className="-ml-1" />
      <Separator orientation="vertical" className="h-6" />

      <div className="flex flex-1 items-center justify-between gap-4">
        <div>
          <h1 className="text-base font-semibold text-foreground">
            Health & Safety Inspections
          </h1>
          <p className="text-xs text-muted-foreground">
            Barangay 178, North Caloocan City
          </p>
        </div>

        <div className="flex items-center gap-2">
          <DropdownMenu onOpenChange={handleOpenChange}>
            <DropdownMenuTrigger
              aria-label="Notifications"
              className="relative inline-flex size-9 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-muted hover:text-foreground focus:outline-hidden focus:bg-muted"
            >
              <Bell className="size-4" />
              {unreadCount > 0 && (
                <span className="absolute -right-0.5 -top-0.5 flex size-4 items-center justify-center rounded-full bg-destructive text-[0.6rem] font-semibold text-white">
                  {unreadCount > 9 ? "9+" : unreadCount}
                </span>
              )}
            </DropdownMenuTrigger>

            <DropdownMenuContent align="end" className="min-w-80 p-1.5">
              <DropdownMenuGroup>
                <DropdownMenuLabel className="flex items-center justify-between px-2 py-1.5 text-sm font-semibold text-foreground">
                  <span>Notifications</span>
                  {unreadCount > 0 && (
                    <Badge variant="secondary">{unreadCount} unread</Badge>
                  )}
                </DropdownMenuLabel>
              </DropdownMenuGroup>

              {unreadCount > 0 && (
                <DropdownMenuItem
                  closeOnClick={false}
                  onClick={handleMarkAllRead}
                  className="text-xs font-medium text-primary"
                >
                  <CheckCheck className="size-4" />
                  Mark all read
                </DropdownMenuItem>
              )}

              <DropdownMenuSeparator />

              {notifications.length === 0 ? (
                <p className="px-3 py-8 text-center text-sm text-muted-foreground">
                  No notifications yet.
                </p>
              ) : (
                <DropdownMenuGroup>
                  {notifications.map((notification) => {
                    const isUnread = !notification.read_at;
                    return (
                      <DropdownMenuItem
                        key={notification.id}
                        closeOnClick={false}
                        onClick={() => handleNotificationClick(notification)}
                        className={cn(
                          "cursor-pointer items-start gap-2 whitespace-normal py-2",
                          isUnread && "bg-primary/5",
                        )}
                      >
                        <div
                          className={cn(
                            "flex size-8 shrink-0 items-center justify-center rounded-full",
                            isUnread
                              ? "bg-primary/10 text-primary"
                              : "bg-muted text-muted-foreground",
                          )}
                        >
                          <BellRing className="size-4" />
                        </div>
                        <div className="min-w-0 flex-1">
                          <p
                            className={cn(
                              "truncate text-sm",
                              isUnread ? "font-semibold" : "font-medium",
                            )}
                          >
                            {notification.data?.title ??
                              notification.title ??
                              "Notification"}
                          </p>
                          <p className="line-clamp-2 text-xs text-muted-foreground">
                            {notification.data?.message ??
                              notification.message ??
                              ""}
                          </p>
                          <p className="mt-0.5 text-xs text-muted-foreground/70">
                            {formatRelativeTime(notification.created_at)}
                          </p>
                        </div>
                        {isUnread && (
                          <span className="mt-1 size-2 shrink-0 rounded-full bg-destructive" />
                        )}
                        <button
                          type="button"
                          aria-label="Delete notification"
                          onClick={(event) => {
                            event.stopPropagation();
                            handleDeleteNotification(notification.id);
                          }}
                          className="flex size-6 shrink-0 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-muted hover:text-destructive"
                        >
                          <Trash2 className="size-3.5" />
                        </button>
                      </DropdownMenuItem>
                    );
                  })}
                </DropdownMenuGroup>
              )}

              <DropdownMenuSeparator />

              <DropdownMenuItem
                onClick={() =>
                  navigate(
                    isResident ? "/resident/notifications" : "/notifications",
                  )
                }
                className="justify-center text-xs font-medium text-primary"
              >
                See all notifications
              </DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>

          <DropdownMenu>
            <DropdownMenuTrigger>
              <div
                className={buttonVariants({
                  variant: "outline",
                  size: "sm",
                  className: "gap-2",
                })}
                role="button"
                tabIndex={0}
              >
                <User className="size-4" />
                <span className="hidden sm:inline">
                  {user?.name ?? "Account"}
                </span>
              </div>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-56">
              <DropdownMenuGroup>
                <DropdownMenuLabel>
                  <div className="flex flex-col gap-1">
                    <span>{user?.name}</span>
                    <span className="text-xs font-normal text-muted-foreground">
                      {user?.email}
                    </span>
                    {user?.role?.name && (
                      <Badge variant="secondary" className="w-fit">
                        {user.role.name}
                      </Badge>
                    )}
                  </div>
                </DropdownMenuLabel>
              </DropdownMenuGroup>
              <DropdownMenuSeparator />
              <DropdownMenuItem onClick={() => navigate("/settings")}>
                <Settings className="size-4" />
                Settings
              </DropdownMenuItem>
              <DropdownMenuItem
                onClick={handleLogout}
                className="text-destructive focus:text-destructive"
              >
                <LogOut className="size-4" />
                Log Out
              </DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>
        </div>
      </div>
    </header>
  );
}
