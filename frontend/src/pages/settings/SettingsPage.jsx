import { useState } from 'react'
import { useTheme } from 'next-themes'
import {
  Building2,
  Copy,
  Eye,
  EyeOff,
  Moon,
  Palette,
  Save,
  ShieldCheck,
  Sparkles,
  Sun,
  User,
} from 'lucide-react'
import { toast } from 'sonner'

import { useAuth } from '@/context/AuthContext'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Switch } from '@/components/ui/switch'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import api from '@/services/api'
import { generateStrongPassword, PASSWORD_REQUIREMENTS } from '@/utils/password'

function getPasswordStrength(value = '') {
  const passed = PASSWORD_REQUIREMENTS.filter((requirement) => requirement.test(value)).length
  const total = PASSWORD_REQUIREMENTS.length

  if (!value) return { label: '', color: '' }
  if (passed <= 2) return { label: 'Weak', color: 'text-red-500' }
  if (passed === 3) return { label: 'Fair', color: 'text-yellow-500' }
  if (passed === 4) return { label: 'Good', color: 'text-blue-500' }
  return { label: 'Strong', color: 'text-green-600' }
}

export default function SettingsPage() {
  const { user, updateUser } = useAuth()
  const [profile, setProfile] = useState({
    name: user?.name ?? '',
    email: user?.email ?? '',
    phone: user?.phone ?? '',
    address: user?.address ?? '',
  })
  const [saving, setSaving] = useState(false)

  async function handleProfileUpdate(event) {
    event.preventDefault()
    setSaving(true)
    try {
      const response = await api.put('/v1/auth/profile', profile)
      updateUser(response.data)
      toast.success('Profile updated')
    } catch (err) {
      const errors = err.response?.data?.errors
      toast.error(
        errors?.email?.[0] ||
        (err.response?.data?.message ?? 'Unable to update profile'),
      )
    } finally {
      setSaving(false)
    }
  }

  return (
    <div className="space-y-6">
      <div>
        <h2 className="text-2xl font-semibold tracking-tight">Settings</h2>
        <p className="text-sm text-muted-foreground">
          Manage your account information and security
        </p>
      </div>

      <Tabs defaultValue="general">
        <TabsList>
          <TabsTrigger value="general">
            <User className="size-4" />
            General
          </TabsTrigger>
          <TabsTrigger value="security">
            <ShieldCheck className="size-4" />
            Security
          </TabsTrigger>
          <TabsTrigger value="appearance">
            <Palette className="size-4" />
            Appearance
          </TabsTrigger>
          <TabsTrigger value="organization">
            <Building2 className="size-4" />
            Organization
          </TabsTrigger>
        </TabsList>

        <TabsContent value="general">
          <Card>
            <CardHeader>
              <CardTitle>Profile Information</CardTitle>
              <CardDescription>Update your personal details</CardDescription>
            </CardHeader>
            <CardContent>
              <form className="space-y-4" onSubmit={handleProfileUpdate}>
                <div className="grid gap-4 sm:grid-cols-2">
                  <div className="space-y-2">
                    <Label>Full Name</Label>
                    <Input
                      value={profile.name}
                      onChange={(e) => setProfile((p) => ({ ...p, name: e.target.value }))}
                      required
                    />
                  </div>
                  <div className="space-y-2">
                    <Label>Email</Label>
                    <Input
                      type="email"
                      value={profile.email}
                      onChange={(e) => setProfile((p) => ({ ...p, email: e.target.value }))}
                      required
                    />
                  </div>
                  <div className="space-y-2">
                    <Label>Phone Number</Label>
                    <Input
                      value={profile.phone}
                      placeholder="09XX XXX XXXX"
                      onChange={(e) => setProfile((p) => ({ ...p, phone: e.target.value }))}
                    />
                  </div>
                  <div className="space-y-2">
                    <Label>Address</Label>
                    <Input
                      value={profile.address}
                      placeholder="Street, Barangay, City"
                      onChange={(e) => setProfile((p) => ({ ...p, address: e.target.value }))}
                    />
                  </div>
                </div>
                <div className="flex justify-end">
                  <Button type="submit" disabled={saving}>
                    <Save className="size-4" />
                    {saving ? 'Saving...' : 'Save Changes'}
                  </Button>
                </div>
              </form>
            </CardContent>
          </Card>
        </TabsContent>

        <TabsContent value="security">
          <SecurityTab />
        </TabsContent>

        <TabsContent value="appearance">
          <AppearanceTab />
        </TabsContent>

        <TabsContent value="organization">
          <OrganizationTab />
        </TabsContent>
      </Tabs>
    </div>
  )
}

function AppearanceTab() {
  const { resolvedTheme, setTheme } = useTheme()
  const isDark = resolvedTheme === 'dark'

  return (
    <Card>
      <CardHeader>
        <CardTitle>Appearance</CardTitle>
        <CardDescription>Customize how the app looks on your device</CardDescription>
      </CardHeader>
      <CardContent>
        <div className="flex items-center justify-between gap-4">
          <div className="flex items-start gap-3">
            <div className="flex size-9 items-center justify-center rounded-lg bg-muted text-muted-foreground">
              {isDark ? <Moon className="size-4" /> : <Sun className="size-4" />}
            </div>
            <div className="space-y-0.5">
              <Label htmlFor="dark-mode">Dark Mode</Label>
              <p className="text-sm text-muted-foreground">
                {isDark
                  ? 'Currently using the dark theme'
                  : 'Currently using the light theme'}
              </p>
            </div>
          </div>
          <Switch
            id="dark-mode"
            checked={isDark}
            onCheckedChange={(checked) => setTheme(checked ? 'dark' : 'light')}
            aria-label="Toggle dark mode"
          />
        </div>
      </CardContent>
    </Card>
  )
}

function SecurityTab() {
  const [passwordForm, setPasswordForm] = useState({
    current_password: '',
    password: '',
    password_confirmation: '',
  })
  const [showFields, setShowFields] = useState({})
  const [generatedPassword, setGeneratedPassword] = useState('')
  const [changing, setChanging] = useState(false)

  const strength = getPasswordStrength(passwordForm.password)

  function toggleShow(field) {
    setShowFields((s) => ({ ...s, [field]: !s[field] }))
  }

  function handleGenerate() {
    const generated = generateStrongPassword(16)
    setPasswordForm((p) => ({ ...p, password: generated, password_confirmation: generated }))
    setGeneratedPassword(generated)
  }

  async function handlePasswordChange(event) {
    event.preventDefault()
    setChanging(true)
    try {
      await api.put('/v1/auth/password', passwordForm)
      toast.success('Password changed')
      setPasswordForm({ current_password: '', password: '', password_confirmation: '' })
      setGeneratedPassword('')
    } catch (err) {
      const errors = err.response?.data?.errors
      toast.error(
        errors?.password?.[0] ??
          (err.response?.data?.message ?? 'Unable to change password'),
      )
    } finally {
      setChanging(false)
    }
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle>Change Password</CardTitle>
        <CardDescription>
          Use at least 8 characters with uppercase, lowercase, number, and special character
        </CardDescription>
      </CardHeader>
      <CardContent>
        <form className="space-y-4" onSubmit={handlePasswordChange}>
          <PasswordField
            label="Current Password"
            value={passwordForm.current_password}
            show={showFields.current_password}
            onChange={(v) => setPasswordForm((p) => ({ ...p, current_password: v }))}
            onToggle={() => toggleShow('current_password')}
          />
          <div className="grid gap-4 sm:grid-cols-2">
            <PasswordField
              label="New Password"
              value={passwordForm.password}
              show={showFields.password}
              onChange={(v) => setPasswordForm((p) => ({ ...p, password: v }))}
              onToggle={() => toggleShow('password')}
              minLength={8}
            />
            <PasswordField
              label="Confirm New Password"
              value={passwordForm.password_confirmation}
              show={showFields.password_confirmation}
              onChange={(v) => setPasswordForm((p) => ({ ...p, password_confirmation: v }))}
              onToggle={() => toggleShow('password_confirmation')}
            />
          </div>

          <div className="space-y-2">
            <div className="flex items-center gap-2">
              <Button type="button" variant="outline" size="sm" onClick={handleGenerate}>
                <Sparkles className="size-3" />
                Generate Password
              </Button>
              <span className={`text-xs font-medium ${strength.color}`}>
                {passwordForm.password ? strength.label : ''}
              </span>
            </div>
            {generatedPassword && (
              <div className="flex items-center justify-between gap-2 rounded-lg border border-border bg-muted px-3 py-2">
                <span className="font-mono text-sm break-all">{generatedPassword}</span>
                <Button
                  type="button"
                  variant="ghost"
                  size="icon-sm"
                  onClick={() => {
                    navigator.clipboard.writeText(generatedPassword)
                    toast.success('Password copied')
                  }}
                  aria-label="Copy password"
                >
                  <Copy className="size-3.5" />
                </Button>
              </div>
            )}
          </div>

          <div className="flex justify-end">
            <Button type="submit" disabled={changing}>
              {changing ? 'Changing...' : 'Change Password'}
            </Button>
          </div>
        </form>
      </CardContent>
    </Card>
  )
}

function OrganizationTab() {
  return (
    <Card>
      <CardHeader>
        <CardTitle>Organization Profile</CardTitle>
        <CardDescription>Barangay-level administrative system positioning within LGU</CardDescription>
      </CardHeader>
      <CardContent className="space-y-4">
        <div className="rounded-lg border border-border bg-muted/20 p-4">
          <p className="text-xs font-semibold tracking-wide text-muted-foreground">Local Government Unit of Caloocan City · Barangay 178 · Health &amp; Safety Inspection System</p>
          <p className="mt-1 text-xs text-muted-foreground">Barangay-Level Administrative System — Implementation Site: Barangay 178, North Caloocan City</p>
        </div>
        <div className="grid gap-3 sm:grid-cols-2">
          <div className="rounded-lg border border-border p-3">
            <p className="text-xs text-muted-foreground">Local Government Unit</p>
            <p className="text-sm font-medium">City Government of Caloocan</p>
          </div>
          <div className="rounded-lg border border-border p-3">
            <p className="text-xs text-muted-foreground">Administrative Unit</p>
            <p className="text-sm font-medium">Barangay 178</p>
          </div>
          <div className="rounded-lg border border-border p-3">
            <p className="text-xs text-muted-foreground">Area</p>
            <p className="text-sm font-medium">North Caloocan City</p>
          </div>
          <div className="rounded-lg border border-border p-3">
            <p className="text-xs text-muted-foreground">System Implementation Site</p>
            <p className="text-sm font-medium">Barangay 178</p>
          </div>
          <div className="rounded-lg border border-border p-3 sm:col-span-2">
            <p className="text-xs text-muted-foreground">System Classification</p>
            <p className="text-sm font-medium">Barangay-Level Administrative System</p>
          </div>
        </div>
        <p className="text-xs text-muted-foreground">All records and reports in this system are scoped to Barangay 178 only — not city-wide.</p>
      </CardContent>
    </Card>
  )
}

function PasswordField({ label, value, show, onChange, onToggle, minLength }) {
  const id = label.toLowerCase().replaceAll(' ', '-')

  return (
    <div className="space-y-2">
      <Label htmlFor={id}>{label}</Label>
      <div className="relative">
        <Input
          id={id}
          type={show ? 'text' : 'password'}
          value={value}
          minLength={minLength}
          autoComplete={id === 'current-password' ? 'current-password' : 'new-password'}
          onChange={(e) => onChange(e.target.value)}
          required
          className="pr-9"
        />
        <Button
          type="button"
          variant="ghost"
          size="icon-sm"
          onClick={onToggle}
          aria-label={show ? `Hide ${label}` : `Show ${label}`}
          className="absolute inset-y-0 right-0.5 my-auto"
        >
          {show ? <EyeOff className="size-4" /> : <Eye className="size-4" />}
        </Button>
      </div>
    </div>
  )
}
