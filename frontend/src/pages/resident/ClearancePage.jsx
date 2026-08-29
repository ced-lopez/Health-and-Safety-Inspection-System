import { useEffect, useState } from "react";
import {
  Award,
  Download,
  Eye,
  Link2,
  Loader2,
  RefreshCw,
  Search,
  Store,
} from "lucide-react";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";

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
import { Skeleton } from "@/components/ui/skeleton";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";
import api from "@/services/api";

const statusLabels = {
  active: "Active",
  expired: "Expired",
  revoked: "Revoked",
  pending: "Pending",
};
const statusVariant = (s) =>
  s === "active" ? "default" : s === "expired" ? "destructive" : "secondary";

const ownershipLabels = {
  unclaimed: "Unclaimed",
  pending: "Pending Review",
  linked: "Linked",
};

function formatDate(value) {
  if (!value) return "N/A";
  return new Date(value + "T00:00:00").toLocaleDateString(undefined, {
    month: "short",
    day: "numeric",
    year: "numeric",
  });
}

export default function ClearancePage() {
  const queryClient = useQueryClient();
  const [clearances, setClearances] = useState([]);
  const [preview, setPreview] = useState(null);
  const [claimOpen, setClaimOpen] = useState(false);
  const [unclaimedSearch, setUnclaimedSearch] = useState("");
  const [claimingId, setClaimingId] = useState(null);
  const [downloadingId, setDownloadingId] = useState(null);

  const { data, isError, isLoading } = useQuery({
    queryKey: ["my-clearances"],
    queryFn: async () => {
      const response = await api.get("/v1/my/clearances", {
        params: { document_kind: "clearance", per_page: 50 },
      });
      return response.data;
    },
  });

  const { data: myEstablishments, isLoading: loadingEstablishments } = useQuery(
    {
      queryKey: ["my-establishments"],
      queryFn: async () => {
        const response = await api.get("/v1/my/establishments");
        return response.data?.data ?? [];
      },
    },
  );

  const { data: unclaimedList, isFetching: fetchingUnclaimed } = useQuery({
    queryKey: ["unclaimed-establishments", unclaimedSearch],
    queryFn: async () => {
      const response = await api.get("/v1/my/establishments/unclaimed", {
        params: { search: unclaimedSearch || undefined },
      });
      return response.data?.data ?? [];
    },
    enabled: claimOpen,
  });

  useEffect(() => {
    if (isError) toast.error("Unable to load clearances");
  }, [isError]);

  useEffect(() => {
    if (data) setClearances(data.data?.documents ?? data.data ?? []);
  }, [data]);

  async function handleDownload(clearance) {
    setDownloadingId(clearance.id);
    try {
      const response = await api.get(`/v1/my/clearances/${clearance.id}/pdf`, {
        responseType: "blob",
      });
      const contentType = response.headers["content-type"] || "";
      if (contentType.includes("application/json")) {
        throw new Error("Not allowed to download this document");
      }
      const url = URL.createObjectURL(response.data);
      const link = document.createElement("a");
      link.href = url;
      link.download = `clearance-${clearance.number ?? clearance.id}.pdf`;
      document.body.appendChild(link);
      link.click();
      link.remove();
      URL.revokeObjectURL(url);
    } catch (error) {
      toast.error(error.message || "Unable to download the clearance");
    } finally {
      setDownloadingId(null);
    }
  }

  async function handleClaim(establishment) {
    setClaimingId(establishment.id);
    try {
      await api.post(`/v1/my/establishments/${establishment.id}/claim`);
      toast.success(
        "Claim submitted. A barangay staff will review your request.",
      );
      queryClient.invalidateQueries({ queryKey: ["my-establishments"] });
      queryClient.invalidateQueries({ queryKey: ["unclaimed-establishments"] });
    } catch (error) {
      toast.error(error.response?.data?.message || "Unable to submit claim");
    } finally {
      setClaimingId(null);
    }
  }

  const linked = (myEstablishments ?? []).filter(
    (e) => e.ownership_status === "linked",
  );

  return (
    <div className="space-y-6">
      <div>
        <h2 className="text-2xl font-semibold tracking-tight">My Clearances</h2>
        <p className="text-sm text-muted-foreground">
          View and download your Health & Safety Clearances
        </p>
      </div>

      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <Store className="size-4" />
            Establishment Link
          </CardTitle>
          <CardDescription>
            Your business profile must be linked before clearances appear below
          </CardDescription>
        </CardHeader>
        <CardContent>
          {loadingEstablishments ? (
            <div className="space-y-2">
              <Skeleton className="h-8 w-full" />
              <Skeleton className="h-8 w-full" />
            </div>
          ) : (myEstablishments ?? []).length === 0 ? (
            <div className="flex flex-col items-start gap-3 py-2">
              <p className="text-sm text-muted-foreground">
                No establishment linked to your account yet.
              </p>
              <Button onClick={() => setClaimOpen(true)}>
                <Link2 className="size-4" />
                Link Establishment
              </Button>
            </div>
          ) : (
            <div className="space-y-3">
              {(myEstablishments ?? []).map((establishment) => (
                <div
                  key={establishment.id}
                  className="flex items-center justify-between gap-4 rounded-lg border border-border p-3"
                >
                  <div className="min-w-0">
                    <p className="font-medium truncate">{establishment.name}</p>
                    <p className="text-xs text-muted-foreground truncate">
                      {establishment.owner_name}
                    </p>
                  </div>
                  <Badge
                    variant={
                      establishment.ownership_status === "linked"
                        ? "default"
                        : "secondary"
                    }
                  >
                    {ownershipLabels[establishment.ownership_status] ??
                      establishment.ownership_status}
                  </Badge>
                </div>
              ))}
              {linked.length === 0 && (
                <Button
                  variant="outline"
                  size="sm"
                  onClick={() => setClaimOpen(true)}
                >
                  <Link2 className="size-4" />
                  Link another establishment
                </Button>
              )}
            </div>
          )}
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>Clearance Records</CardTitle>
          <CardDescription>
            Active and expired clearances issued to you
          </CardDescription>
        </CardHeader>
        <CardContent>
          {isLoading ? (
            <div className="space-y-3">
              <Skeleton className="h-10 w-full" />
              <Skeleton className="h-10 w-full" />
            </div>
          ) : clearances.length === 0 ? (
            <p className="py-8 text-center text-sm text-muted-foreground">
              No clearances found.
            </p>
          ) : (
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Clearance No.</TableHead>
                  <TableHead>Establishment</TableHead>
                  <TableHead>Issue Date</TableHead>
                  <TableHead>Expiry Date</TableHead>
                  <TableHead>Status</TableHead>
                  <TableHead className="text-right">Actions</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {clearances.map((c) => (
                  <TableRow key={c.id}>
                    <TableCell className="font-medium">
                      {c.number ?? "N/A"}
                    </TableCell>
                    <TableCell>{c.establishment?.name ?? "N/A"}</TableCell>
                    <TableCell>{formatDate(c.issue_date)}</TableCell>
                    <TableCell>{formatDate(c.expiration_date)}</TableCell>
                    <TableCell>
                      <Badge variant={statusVariant(c.status)}>
                        {statusLabels[c.status] ?? c.status}
                      </Badge>
                    </TableCell>
                    <TableCell className="text-right">
                      <div className="flex justify-end gap-2">
                        <Button
                          variant="outline"
                          size="icon-sm"
                          onClick={() => setPreview(c)}
                        >
                          <Eye className="size-4" />
                        </Button>
                        <Button
                          variant="outline"
                          size="icon-sm"
                          onClick={() => handleDownload(c)}
                          disabled={downloadingId === c.id}
                        >
                          {downloadingId === c.id ? (
                            <Loader2 className="size-4 animate-spin" />
                          ) : (
                            <Download className="size-4" />
                          )}
                        </Button>
                        {c.status === "expired" && (
                          <Button
                            variant="outline"
                            size="sm"
                            onClick={() =>
                              toast.info("Renewal feature coming soon")
                            }
                          >
                            <RefreshCw className="size-4" />
                            Renew
                          </Button>
                        )}
                      </div>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          )}
        </CardContent>
      </Card>

      <Dialog
        open={!!preview}
        onOpenChange={(open) => !open && setPreview(null)}
      >
        <DialogContent className="sm:max-w-xl">
          {preview && (
            <>
              <DialogHeader>
                <DialogTitle>Health & Safety Clearance</DialogTitle>
                <DialogDescription>{preview.number}</DialogDescription>
              </DialogHeader>
              <div className="rounded-lg border border-border p-5 space-y-4">
                <div className="text-center">
                  <Award className="size-12 mx-auto text-primary" />
                  <p className="text-sm text-muted-foreground mt-2">
                    Barangay 178, North Caloocan City
                  </p>
                  <h3 className="text-xl font-semibold mt-1">
                    Health & Safety Clearance
                  </h3>
                </div>
                <div className="grid gap-3 text-sm sm:grid-cols-2">
                  <div>
                    <p className="text-xs text-muted-foreground">
                      Clearance No.
                    </p>
                    <p className="font-medium">{preview.number}</p>
                  </div>
                  <div>
                    <p className="text-xs text-muted-foreground">Status</p>
                    <p className="font-medium">
                      <Badge variant={statusVariant(preview.status)}>
                        {statusLabels[preview.status]}
                      </Badge>
                    </p>
                  </div>
                  <div>
                    <p className="text-xs text-muted-foreground">
                      Establishment
                    </p>
                    <p className="font-medium">
                      {preview.establishment?.name ?? "N/A"}
                    </p>
                  </div>
                  <div>
                    <p className="text-xs text-muted-foreground">Issue Date</p>
                    <p className="font-medium">
                      {formatDate(preview.issue_date)}
                    </p>
                  </div>
                  <div>
                    <p className="text-xs text-muted-foreground">Expiration</p>
                    <p className="font-medium">
                      {formatDate(preview.expiration_date)}
                    </p>
                  </div>
                </div>
                {preview.qr_code?.code && (
                  <div className="rounded-lg bg-muted p-3 text-center text-sm">
                    <p className="font-medium">QR Code</p>
                    <p className="mt-1 font-mono text-xs">
                      {preview.qr_code.code}
                    </p>
                  </div>
                )}
              </div>
              <DialogFooter>
                <Button
                  variant="outline"
                  onClick={() => handleDownload(preview)}
                >
                  <Download className="size-4" />
                  Download PDF
                </Button>
              </DialogFooter>
            </>
          )}
        </DialogContent>
      </Dialog>

      <Dialog open={claimOpen} onOpenChange={setClaimOpen}>
        <DialogContent className="sm:max-w-xl">
          <DialogHeader>
            <DialogTitle>Link Establishment</DialogTitle>
            <DialogDescription>
              Select your establishment from the list below. A barangay staff
              will review and approve your claim.
            </DialogDescription>
          </DialogHeader>
          <div className="space-y-4">
            <style>{`
              .claim-list-scroll {
                scrollbar-width: thin;
                scrollbar-color: hsl(var(--border)) transparent;
              }
              .claim-list-scroll::-webkit-scrollbar {
                width: 6px;
                height: 6px;
              }
              .claim-list-scroll::-webkit-scrollbar-track {
                background: transparent;
              }
              .claim-list-scroll::-webkit-scrollbar-thumb {
                background-color: hsl(var(--border));
                border-radius: 9999px;
              }
              .claim-list-scroll::-webkit-scrollbar-button {
                display: none;
                width: 0;
                height: 0;
              }
            `}</style>
            <div className="overflow-hidden rounded-xl border border-border bg-popover">
              <div className="border-b border-border/60 px-3 py-2">
                <div className="relative">
                  <Search className="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                  <Input
                    className="pl-9"
                    placeholder="Search establishment name..."
                    value={unclaimedSearch}
                    onChange={(e) => setUnclaimedSearch(e.target.value)}
                  />
                </div>
              </div>
              {/* overflow-hidden + rounded-b-xl clips the scrollbar to the card's
                  corner; claim-list-scroll forces a slim, theme-colored scrollbar
                  so it never falls back to the browser's bulky native one */}
              <div className="max-h-72 space-y-2 overflow-y-auto rounded-b-xl p-3 claim-list-scroll">
                {fetchingUnclaimed ? (
                  <div className="space-y-2">
                    <Skeleton className="h-12 w-full" />
                    <Skeleton className="h-12 w-full" />
                  </div>
                ) : (unclaimedList ?? []).length === 0 ? (
                  <p className="py-6 text-center text-sm text-muted-foreground">
                    No unclaimed establishments found.
                  </p>
                ) : (
                  (unclaimedList ?? []).map((establishment) => (
                    <div
                      key={establishment.id}
                      className="flex items-center justify-between gap-4 rounded-lg border border-border p-3"
                    >
                      <div className="min-w-0">
                        <p className="font-medium truncate">
                          {establishment.name}
                        </p>
                        <p className="text-xs text-muted-foreground truncate">
                          {establishment.owner_name} · {establishment.address}
                        </p>
                      </div>
                      <Button
                        variant="outline"
                        size="sm"
                        className="shrink-0"
                        onClick={() => handleClaim(establishment)}
                        disabled={claimingId === establishment.id}
                      >
                        {claimingId === establishment.id ? (
                          <Loader2 className="size-4 animate-spin" />
                        ) : (
                          <Link2 className="size-4" />
                        )}
                        Claim
                      </Button>
                    </div>
                  ))
                )}
              </div>
            </div>
          </div>
        </DialogContent>
      </Dialog>
    </div>
  );
}
