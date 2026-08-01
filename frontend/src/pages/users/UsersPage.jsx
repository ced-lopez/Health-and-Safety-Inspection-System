import { useEffect, useMemo, useState } from "react";
import { Copy, KeyRound, RefreshCcw, Save, Search, Sparkles, UserPlus } from "lucide-react";
import { useQuery } from "@tanstack/react-query";
import { toast } from "sonner";

import api from "@/services/api";
import { generateStrongPassword, PASSWORD_REQUIREMENTS } from "@/utils/password";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Skeleton } from "@/components/ui/skeleton";
import { Switch } from "@/components/ui/switch";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";
import {
  Tabs,
  TabsContent,
  TabsList,
  TabsTrigger,
} from "@/components/ui/tabs";

const roleOptions = [
  { value: "administrator", label: "Administrator" },
  { value: "inspector", label: "Inspector" },
  { value: "barangay_staff", label: "Barangay Staff" },
  { value: "resident", label: "Resident" },
];

const OFFICER_ROLES = ["administrator", "barangay_staff", "inspector"];

function getPasswordStrength(value = "") {
  const passed = PASSWORD_REQUIREMENTS.filter((requirement) => requirement.test(value)).length;
  const total = PASSWORD_REQUIREMENTS.length;

  if (!value) return { label: "", color: "" };
  if (passed <= 2) return { label: "Weak", color: "text-red-500" };
  if (passed === 3) return { label: "Fair", color: "text-yellow-500" };
  if (passed === 4) return { label: "Good", color: "text-blue-500" };
  return { label: "Strong", color: "text-green-600", perfect: passed === total };
}

const emptyForm = {
  name: "",
  email: "",
  phone: "",
  password: "",
  role: "inspector",
};

export default function UsersPage() {
  const [users, setUsers] = useState([]);
  const [savingUserIds, setSavingUserIds] = useState({});

  const [filters, setFilters] = useState({ search: "" });
  const [searchInput, setSearchInput] = useState("");

  const [dialogOpen, setDialogOpen] = useState(false);
  const [form, setForm] = useState(emptyForm);
  const [errors, setErrors] = useState({});
  const [submitting, setSubmitting] = useState(false);

  const [passwordTarget, setPasswordTarget] = useState(null);
  const [passwordOpen, setPasswordOpen] = useState(false);
  const [passwordForm, setPasswordForm] = useState({ password: "", confirm: "" });
  const [passwordErrors, setPasswordErrors] = useState({});
  const [passwordSubmitting, setPasswordSubmitting] = useState(false);

  const [generatedPassword, setGeneratedPassword] = useState("");

  const strength = getPasswordStrength(form.password);

  const usersQuery = useQuery({
    queryKey: ["admin-users"],
    queryFn: async () => {
      const response = await api.get("/v1/admin/users");
      return response.data.data?.users ?? response.data.users ?? [];
    },
  });

  useEffect(() => {
    const timeout = window.setTimeout(() => {
      setFilters((current) => ({ ...current, search: searchInput }));
    }, 150);

    return () => window.clearTimeout(timeout);
  }, [searchInput]);

  useEffect(() => {
    if (usersQuery.isError) {
      toast.error("Unable to load users");
      console.error(usersQuery.error);
    }
  }, [usersQuery.error, usersQuery.isError]);

  useEffect(() => {
    if (usersQuery.data) {
      setUsers(usersQuery.data);
    }
  }, [usersQuery.data]);

  const loading = usersQuery.isLoading && users.length === 0;

  const filteredUsers = useMemo(() => {
    const term = filters.search.trim().toLowerCase();

    return users.filter((u) => {
      const matchesSearch =
        !term ||
        [u.name, u.email, u.phone]
          .filter(Boolean)
          .some((value) => value.toLowerCase().includes(term));

      return matchesSearch;
    });
  }, [users, filters]);

  const officers = useMemo(
    () => filteredUsers.filter((u) => OFFICER_ROLES.includes(u.role?.slug)),
    [filteredUsers],
  );
  const residents = useMemo(
    () => filteredUsers.filter((u) => u.role?.slug === "resident"),
    [filteredUsers],
  );

  function updateForm(key, value) {
    setForm((current) => ({ ...current, [key]: value }));
    setErrors((current) => ({ ...current, [key]: undefined }));
  }

  function openCreateDialog() {
    setForm(emptyForm);
    setErrors({});
    setDialogOpen(true);
  }

  async function handleSubmit(event) {
    event.preventDefault();
    setSubmitting(true);
    setErrors({});

    try {
      await api.post("/v1/admin/users", {
        name: form.name,
        email: form.email,
        phone: form.phone || null,
        password: form.password,
        role: form.role,
      });

      toast.success("User created successfully");
      setDialogOpen(false);
      setForm(emptyForm);
      await usersQuery.refetch();
    } catch (error) {
      const validationErrors = error.response?.data?.errors;

      if (validationErrors) {
        setErrors(validationErrors);
        toast.error("Please review the highlighted fields");
      } else {
        toast.error(error.response?.data?.message || "Unable to create user");
      }

      console.error(error);
    } finally {
      setSubmitting(false);
    }
  }

  function updateUserDraft(id, patch) {
    setUsers((current) =>
      current.map((u) => (u.id === id ? { ...u, ...patch } : u)),
    );
  }

  async function handleSaveUser(userId) {
    const target = users.find((u) => u.id === userId);
    if (!target) return;

    setSavingUserIds((current) => ({ ...current, [userId]: true }));
    try {
      await api.put(`/v1/admin/users/${userId}`, {
        name: target.name,
        email: target.email,
        phone: target.phone ?? null,
        role: target.role?.slug ?? "inspector",
        is_active: target.is_active,
      });

      toast.success("User updated");
      await usersQuery.refetch();
    } catch (error) {
      toast.error(error.response?.data?.message || "Unable to update user");
      console.error(error);
    } finally {
      setSavingUserIds((current) => ({ ...current, [userId]: false }));
    }
  }

  function openPasswordDialog(user) {
    setPasswordTarget(user);
    setPasswordForm({ password: "", confirm: "" });
    setPasswordErrors({});
    setGeneratedPassword("");
    setPasswordOpen(true);
  }

  function handleGenerateForCreate() {
    const generated = generateStrongPassword(16);
    setForm((current) => ({ ...current, password: generated }));
    setErrors((current) => ({ ...current, password: undefined }));
    setGeneratedPassword(generated);
  }

  function handleGenerateForPassword() {
    const generated = generateStrongPassword(16);
    setPasswordForm({ password: generated, confirm: generated });
    setPasswordErrors({});
    setGeneratedPassword(generated);
  }

  function updatePasswordForm(key, value) {
    setPasswordForm((current) => ({ ...current, [key]: value }));
    setPasswordErrors((current) => ({ ...current, [key]: undefined }));
  }

  async function handleSetPassword(event) {
    event.preventDefault();
    if (!passwordTarget) return;

    if (passwordForm.password !== passwordForm.confirm) {
      setPasswordErrors({ confirm: ["Passwords do not match."] });
      return;
    }

    setPasswordSubmitting(true);
    setPasswordErrors({});

    try {
      await api.put(`/v1/admin/users/${passwordTarget.id}`, {
        role: passwordTarget.role?.slug ?? "inspector",
        is_active: passwordTarget.is_active,
        password: passwordForm.password,
      });

      toast.success("Password updated");
      setPasswordOpen(false);
      await usersQuery.refetch();
    } catch (error) {
      const validationErrors = error.response?.data?.errors;
      if (validationErrors) {
        setPasswordErrors(validationErrors);
      } else {
        toast.error(error.response?.data?.message || "Unable to update password");
      }
      console.error(error);
    } finally {
      setPasswordSubmitting(false);
    }
  }

  function renderUsersTable(list) {
    if (loading) {
      return (
        <div className="space-y-3">
          <Skeleton className="h-10 w-full" />
          <Skeleton className="h-10 w-full" />
          <Skeleton className="h-10 w-full" />
        </div>
      );
    }

    if (list.length === 0) {
      return (
        <p className="py-8 text-center text-sm text-muted-foreground">
          No users found.
        </p>
      );
    }

    return (
      <div className="overflow-x-auto">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>User</TableHead>
              <TableHead>Role</TableHead>
              <TableHead className="w-32">Status</TableHead>
              <TableHead className="w-24 text-right">Actions</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {list.map((u) => (
              <TableRow key={u.id}>
                <TableCell>
                  <div className="font-medium">{u.name}</div>
                  <Input
                    className="mt-1 h-7 text-xs"
                    value={u.email}
                    onChange={(event) =>
                      updateUserDraft(u.id, { email: event.target.value })
                    }
                    aria-label="Edit email"
                  />
                  {u.phone ? (
                    <div className="mt-1 text-xs text-muted-foreground">
                      {u.phone}
                    </div>
                  ) : null}
                </TableCell>

                <TableCell>
                  <select
                    className="h-8 rounded-lg border border-input bg-background px-2.5 text-sm"
                    value={u.role?.slug ?? "inspector"}
                    onChange={(e) =>
                      updateUserDraft(u.id, {
                        role: {
                          ...(u.role ?? {}),
                          slug: e.target.value,
                          name: "",
                        },
                      })
                    }
                  >
                    {roleOptions.map((role) => (
                      <option key={role.value} value={role.value}>
                        {role.label}
                      </option>
                    ))}
                  </select>
                </TableCell>

                <TableCell>
                  <div className="flex items-center gap-2.5">
                    <Switch
                      checked={!!u.is_active}
                      onCheckedChange={(checked) =>
                        updateUserDraft(u.id, { is_active: checked })
                      }
                      aria-label={
                        u.is_active ? "Deactivate user" : "Activate user"
                      }
                    />
                    <Badge variant={u.is_active ? "default" : "outline"}>
                      {u.is_active ? "Active" : "Inactive"}
                    </Badge>
                  </div>
                </TableCell>

                <TableCell>
                  <div className="flex justify-end gap-2">
                    <Button
                      variant="outline"
                      size="icon-sm"
                      onClick={() => openPasswordDialog(u)}
                      aria-label="Change password"
                    >
                      <KeyRound className="size-4" />
                    </Button>
                    <Button
                      variant="outline"
                      size="icon-sm"
                      disabled={!!savingUserIds[u.id]}
                      onClick={() => handleSaveUser(u.id)}
                      aria-label="Save user"
                    >
                      <Save className="size-4" />
                    </Button>
                  </div>
                </TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <div className="flex items-center gap-2">
            <h2 className="text-2xl font-semibold tracking-tight">Users</h2>
            {usersQuery.isFetching && !loading && (
              <span className="text-xs text-muted-foreground">Refreshing...</span>
            )}
          </div>
          <p className="text-sm text-muted-foreground">
            Admin-only creation and editing of inspector / health officer
            accounts
          </p>
        </div>
        <div className="flex gap-2">
          <Button
            variant="outline"
            onClick={() => usersQuery.refetch()}
            disabled={loading}
          >
            <RefreshCcw className="size-4" />
            Refresh
          </Button>
          <Button onClick={openCreateDialog}>
            <UserPlus className="size-4" />
            Add User
          </Button>
        </div>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>User Directory</CardTitle>
          <CardDescription>
            Manage accounts, roles, and active status
          </CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="relative">
            <Search className="absolute left-2.5 top-2 size-4 text-muted-foreground" />
            <Input
              className="pl-8"
              placeholder="Search name, email, or phone"
              value={searchInput}
              onChange={(event) => setSearchInput(event.target.value)}
            />
          </div>

          <Tabs defaultValue="officers">
            <TabsList>
              <TabsTrigger value="officers">
                Officers ({officers.length})
              </TabsTrigger>
              <TabsTrigger value="residents">
                Residents ({residents.length})
              </TabsTrigger>
            </TabsList>

            <TabsContent value="officers">
              {renderUsersTable(officers)}
            </TabsContent>
            <TabsContent value="residents">
              {renderUsersTable(residents)}
            </TabsContent>
          </Tabs>
        </CardContent>
      </Card>

      <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
        <DialogContent className="sm:max-w-2xl">
          <DialogHeader>
            <DialogTitle>Add User</DialogTitle>
            <DialogDescription>
              Credentials are set by the administrator. The new account will be
              active immediately.
            </DialogDescription>
          </DialogHeader>

          <form className="space-y-4" onSubmit={handleSubmit}>
            <div className="grid gap-4 sm:grid-cols-2">
              <Field
                label="Full name"
                value={form.name}
                error={errors.name}
                placeholder="Juan Dela Cruz"
                onChange={(value) => updateForm("name", value)}
              />
              <Field
                label="Email"
                type="email"
                value={form.email}
                error={errors.email}
                placeholder="personnel@barangay178.gov"
                onChange={(value) => updateForm("email", value)}
              />
              <Field
                label="Phone"
                value={form.phone}
                error={errors.phone}
                required={false}
                placeholder="09XX XXX XXXX"
                onChange={(value) => updateForm("phone", value)}
              />
              <SelectField
                label="Role"
                value={form.role}
                error={errors.role}
                onChange={(value) => updateForm("role", value)}
              >
                {roleOptions.map((role) => (
                  <option key={role.value} value={role.value}>
                    {role.label}
                  </option>
                ))}
              </SelectField>
              <Field
                label="Temporary password"
                type="password"
                value={form.password}
                error={errors.password}
                placeholder="Minimum 8 characters"
                onChange={(value) => updateForm("password", value)}
              />
            </div>

            <div className="space-y-2">
              <div className="flex items-center gap-2">
                <Button
                  type="button"
                  variant="outline"
                  size="sm"
                  onClick={handleGenerateForCreate}
                >
                  <Sparkles className="size-3" />
                  Generate Password
                </Button>
                <span
                  className={`text-xs font-medium ${strength.color}`}
                >
                  {form.password ? strength.label : ""}
                </span>
              </div>
              {generatedPassword && (
                <div className="flex items-center justify-between gap-2 rounded-lg border border-border bg-muted px-3 py-2">
                  <span className="font-mono text-sm break-all">
                    {generatedPassword}
                  </span>
                  <Button
                    type="button"
                    variant="ghost"
                    size="icon-sm"
                    onClick={() => {
                      navigator.clipboard.writeText(generatedPassword);
                      toast.success("Password copied");
                    }}
                    aria-label="Copy password"
                  >
                    <Copy className="size-3.5" />
                  </Button>
                </div>
              )}
            </div>

            <DialogFooter>
              <Button
                type="button"
                variant="outline"
                onClick={() => setDialogOpen(false)}
              >
                Cancel
              </Button>
              <Button type="submit" disabled={submitting}>
                {submitting ? "Creating..." : "Create User"}
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>

      <Dialog open={passwordOpen} onOpenChange={setPasswordOpen}>
        <DialogContent className="sm:max-w-md">
          <DialogHeader>
            <DialogTitle>Change Password</DialogTitle>
            <DialogDescription>
              {passwordTarget?.name} · {passwordTarget?.email}
            </DialogDescription>
          </DialogHeader>
          <form className="space-y-4" onSubmit={handleSetPassword}>
            <div className="space-y-2">
              <Field
                label="New password"
                type="password"
                value={passwordForm.password}
                error={passwordErrors.password}
                placeholder="Minimum 8 characters"
                onChange={(value) => updatePasswordForm("password", value)}
              />
              <Field
                label="Confirm password"
                type="password"
                value={passwordForm.confirm}
                error={passwordErrors.confirm}
                placeholder="Re-enter new password"
                onChange={(value) => updatePasswordForm("confirm", value)}
              />
            </div>

            <div className="space-y-2">
              <div className="flex items-center gap-2">
                <Button
                  type="button"
                  variant="outline"
                  size="sm"
                  onClick={handleGenerateForPassword}
                >
                  <Sparkles className="size-3" />
                  Generate Password
                </Button>
                <span className={`text-xs font-medium ${getPasswordStrength(passwordForm.password).color}`}>
                  {passwordForm.password ? getPasswordStrength(passwordForm.password).label : ""}
                </span>
              </div>
              {generatedPassword && (
                <div className="flex items-center justify-between gap-2 rounded-lg border border-border bg-muted px-3 py-2">
                  <span className="font-mono text-sm break-all">
                    {generatedPassword}
                  </span>
                  <Button
                    type="button"
                    variant="ghost"
                    size="icon-sm"
                    onClick={() => {
                      navigator.clipboard.writeText(generatedPassword);
                      toast.success("Password copied");
                    }}
                    aria-label="Copy password"
                  >
                    <Copy className="size-3.5" />
                  </Button>
                </div>
              )}
            </div>

            <DialogFooter>
              <Button
                type="button"
                variant="outline"
                onClick={() => setPasswordOpen(false)}
              >
                Cancel
              </Button>
              <Button type="submit" disabled={passwordSubmitting}>
                {passwordSubmitting ? "Saving..." : "Save Password"}
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>
    </div>
  );
}

function Field({
  label,
  value,
  onChange,
  error,
  type = "text",
  placeholder,
  required = true,
}) {
  const id = label.toLowerCase().replaceAll(" ", "-");

  return (
    <div className="space-y-2">
      <Label htmlFor={id}>{label}</Label>
      <Input
        id={id}
        type={type}
        value={value}
        placeholder={placeholder}
        autoComplete={type === "password" ? "new-password" : undefined}
        required={required}
        onChange={(event) => onChange(event.target.value)}
      />
      {error && <p className="text-xs text-destructive">{error[0]}</p>}
    </div>
  );
}

function SelectField({
  label,
  value,
  onChange,
  error,
  children,
  required = true,
}) {
  const id = label.toLowerCase().replaceAll(" ", "-");

  return (
    <div className="space-y-2">
      <Label htmlFor={id}>{label}</Label>
      <select
        id={id}
        className="h-8 w-full rounded-lg border border-input bg-background px-2.5 text-sm"
        value={value}
        required={required}
        onChange={(event) => onChange(event.target.value)}
      >
        {children}
      </select>
      {error && <p className="text-xs text-destructive">{error[0]}</p>}
    </div>
  );
}
