import { useEffect, useState } from 'react'
import { Bell, CheckCheck, Eye, Trash2 } from 'lucide-react'
import { useQuery } from '@tanstack/react-query'
import { useNavigate } from 'react-router-dom'
import { toast } from 'sonner'

import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'
import { useAuth } from '@/context/AuthContext'
import { fetchNotifications, markNotificationRead, markAllNotificationsRead, deleteNotification } from '@/services/notificationService'
import { getNotificationHref } from '@/utils/notificationLinks'

function formatDate(value) {
  if (!value) return ''
  return new Date(value).toLocaleString()
}

export default function NotificationsPage() {
  const { user } = useAuth()
  const navigate = useNavigate()
  const [notifications, setNotifications] = useState([])

  const { data, isError, isLoading } = useQuery({
    queryKey: ['notifications'],
    queryFn: () => fetchNotifications({ per_page: 50 }),
  })

  useEffect(() => {
    if (isError) toast.error('Unable to load notifications')
  }, [isError])

  useEffect(() => {
    const raw = data?.data?.notifications ?? data?.notifications ?? data?.data ?? []
    setNotifications(Array.isArray(raw) ? raw : [])
  }, [data])

  async function handleMarkRead(id) {
    try {
      await markNotificationRead(id)
      setNotifications((prev) =>
        prev.map((n) => (n.id === id ? { ...n, read_at: new Date().toISOString() } : n))
      )
    } catch { toast.error('Unable to mark as read') }
  }

  async function handleOpen(notification) {
    navigate(getNotificationHref(notification, user?.role?.slug))

    if (!notification.read_at) {
      try {
        await markNotificationRead(notification.id)
        setNotifications((prev) =>
          prev.map((n) => (n.id === notification.id ? { ...n, read_at: new Date().toISOString() } : n))
        )
      } catch { toast.error('Unable to mark as read') }
    }
  }

  async function handleMarkAllRead() {
    try {
      await markAllNotificationsRead()
      setNotifications((prev) => prev.map((n) => ({ ...n, read_at: new Date().toISOString() })))
      toast.success('All notifications marked as read')
    } catch { toast.error('Unable to mark all as read') }
  }

  async function handleDelete(id) {
    try {
      await deleteNotification(id)
      setNotifications((prev) => prev.filter((n) => n.id !== id))
      toast.success('Notification deleted')
    } catch { toast.error('Unable to delete notification') }
  }

  const unreadCount = notifications.filter((n) => !n.read_at).length

  return (
    <div className="space-y-6">
      <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h2 className="text-2xl font-semibold tracking-tight">Notifications</h2>
          <p className="text-sm text-muted-foreground">Stay updated on your inspections and documents</p>
        </div>
        {unreadCount > 0 && (
          <Button variant="outline" onClick={handleMarkAllRead}>
            <CheckCheck className="size-4" />
            Mark All Read
          </Button>
        )}
      </div>

      <Card>
        <CardHeader>
          <CardTitle>All Notifications {unreadCount > 0 && <Badge variant="secondary" className="ml-2">{unreadCount} unread</Badge>}</CardTitle>
          <CardDescription>Application updates, inspection reports, and violation notices</CardDescription>
        </CardHeader>
        <CardContent>
          {isLoading ? (
            <div className="space-y-3"><Skeleton className="h-16 w-full" /><Skeleton className="h-16 w-full" /></div>
          ) : notifications.length === 0 ? (
            <p className="py-8 text-center text-sm text-muted-foreground">No notifications yet.</p>
          ) : (
            <div className="space-y-2">
              {notifications.map((n) => {
                const isUnread = !n.read_at
                return (
                  <div
                    key={n.id}
                    role="button"
                    tabIndex={0}
                    onClick={() => handleOpen(n)}
                    onKeyDown={(e) => {
                      if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault()
                        handleOpen(n)
                      }
                    }}
                    className={`flex cursor-pointer items-start gap-3 rounded-lg border p-3 text-left transition-colors hover:border-primary/30 hover:bg-primary/5 focus:outline-hidden focus-visible:ring-2 focus-visible:ring-ring ${isUnread ? 'border-primary/20 bg-primary/5' : 'border-border'}`}
                  >
                    <div className={`flex size-8 items-center justify-center rounded-full ${isUnread ? 'bg-primary/10 text-primary' : 'bg-muted text-muted-foreground'}`}>
                      <Bell className="size-4" />
                    </div>
                    <div className="flex-1 min-w-0">
                      <p className={`text-sm ${isUnread ? 'font-semibold' : 'font-medium'}`}>{n.data?.title ?? n.title ?? 'Notification'}</p>
                      <p className="text-xs text-muted-foreground mt-0.5">{n.data?.message ?? n.message ?? ''}</p>
                      <p className="text-xs text-muted-foreground mt-1">{formatDate(n.created_at)}</p>
                    </div>
                    {isUnread && (
                      <Button
                        variant="ghost"
                        size="icon-sm"
                        aria-label="Mark as read"
                        onClick={(e) => {
                          e.stopPropagation()
                          handleMarkRead(n.id)
                        }}
                      >
                        <Eye className="size-4" />
                      </Button>
                    )}
                    <Button
                      variant="ghost"
                      size="icon-sm"
                      aria-label="Delete notification"
                      className="text-muted-foreground hover:text-destructive"
                      onClick={(e) => {
                        e.stopPropagation()
                        handleDelete(n.id)
                      }}
                    >
                      <Trash2 className="size-4" />
                    </Button>
                  </div>
                )
              })}
            </div>
          )}
        </CardContent>
      </Card>
    </div>
  )
}
