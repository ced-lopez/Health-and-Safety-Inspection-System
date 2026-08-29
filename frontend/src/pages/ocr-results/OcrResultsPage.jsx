import { useEffect, useMemo, useState } from "react";
import { Link } from "react-router-dom";
import {
  ChevronDown,
  Eye,
  Search,
  ShieldCheck,
  RefreshCw,
  ScanText,
} from "lucide-react";
import { useQuery } from "@tanstack/react-query";
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
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Skeleton } from "@/components/ui/skeleton";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";
import {
  fetchOcrResults,
  fetchOcrResultStats,
  reprocessOcrResult,
  verifyOcrResult,
} from "@/services/ocrResultService";

const OCR_STATUS_OPTIONS = [
  { value: "", label: "All OCR statuses" },
  { value: "completed", label: "Completed" },
  { value: "needs_review", label: "Needs Review" },
  { value: "pending", label: "Pending" },
  { value: "processing", label: "Processing" },
  { value: "failed", label: "Failed" },
];

const VERIFICATION_STATUS_OPTIONS = [
  { value: "", label: "All verification" },
  { value: "pending", label: "Pending" },
  { value: "verified", label: "Verified" },
  { value: "rejected", label: "Rejected" },
];

const CONFIDENCE_OPTIONS = [
  { value: "", label: "All confidence" },
  { value: "high", label: "High (≥ 90)" },
  { value: "medium", label: "Medium (75–89)" },
  { value: "low", label: "Low (< 75)" },
];

const CLASSIFICATIONS = [
  "business_permit",
  "dti_registration",
  "barangay_clearance",
  "government_id",
  "cedula",
  "sanitary_permit",
  "fire_safety_inspection_certificate",
  "health_certificate",
];

function formatDate(value) {
  if (!value) return "—";
  return new Date(value).toLocaleString();
}

function confidenceBuckets(stats) {
  const buckets = stats?.summary?.by_confidence ?? {};
  const high = buckets.high ?? 0;
  const medium = buckets.medium ?? 0;
  const low = buckets.low ?? 0;
  const total = high + medium + low;
  const pct = (n) => (total > 0 ? Math.round((n / total) * 100) : 0);

  return {
    high,
    medium,
    low,
    total,
    highPct: pct(high),
    mediumPct: pct(medium),
    lowPct: pct(low),
  };
}

function statusBadge(result) {
  const verification = result.verification_status;
  const ocrStatus = result.ocr_status;
  const documentStatus = result.status;

  if (verification === "verified") {
    return { label: "Verified", variant: "default" };
  }
  if (verification === "rejected") {
    return { label: "Rejected", variant: "destructive" };
  }
  if (documentStatus === "needs_reupload") {
    return { label: "Re-upload", variant: "outline" };
  }
  if (ocrStatus === "needs_review") {
    return { label: "Needs Review", variant: "secondary" };
  }
  if (ocrStatus === "failed") {
    return { label: "Failed", variant: "destructive" };
  }
  return { label: "Pending", variant: "outline" };
}

function groupLabel(result) {
  const applicant = result.applicant ?? "Unknown applicant";
  const business = result.business_name ?? "";

  return business ? `${business} — ${applicant}` : applicant;
}

function collapseVersions(group) {
  // Same file_name (or original_name if file_name is absent) = same document,
  // multiple OCR attempts. Keep the most recently processed row and tag it
  // with how many versions exist so it can link into history.
  const byName = new Map();

  group.forEach((result) => {
    const key = result.file_name ?? result.original_name ?? result.id;
    const existing = byName.get(key);
    if (
      !existing ||
      new Date(result.processed_at) > new Date(existing.processed_at)
    ) {
      byName.set(key, result);
    }
  });

  const counts = new Map();
  group.forEach((result) => {
    const key = result.file_name ?? result.original_name ?? result.id;
    counts.set(key, (counts.get(key) ?? 0) + 1);
  });

  return [...byName.values()].map((result) => ({
    ...result,
    _versionCount:
      counts.get(result.file_name ?? result.original_name ?? result.id) ?? 1,
  }));
}

function groupStats(group) {
  const scored = group.filter((r) => typeof r.confidence_score === "number");
  const avg = scored.length
    ? Math.round(
        scored.reduce((sum, r) => sum + r.confidence_score, 0) / scored.length,
      )
    : null;
  const needsReview = group.filter((r) => {
    const badge = statusBadge(r);
    return (
      badge.label === "Needs Review" ||
      badge.label === "Failed" ||
      (r.confidence_score ?? 100) < 75
    );
  }).length;

  return { avg, needsReview };
}

const GROUPS_PER_PAGE = 5;

// Fetched in one large batch (not paginated by row) so that grouping by
// business/applicant can happen client-side without a store's documents
// being split across a page boundary.
const RESULTS_BATCH_SIZE = 500;

function groupResults(results, sortBy) {
  const groups = new Map();

  results.forEach((result) => {
    const key = groupLabel(result);
    if (!groups.has(key)) groups.set(key, []);
    groups.get(key).push(result);
  });

  return [...groups.entries()].map(([label, rawGroup]) => {
    const group = collapseVersions(rawGroup);

    group.sort((a, b) => {
      if (sortBy === "processed")
        return new Date(b.processed_at ?? 0) - new Date(a.processed_at ?? 0);
      if (sortBy === "status")
        return statusBadge(a).label.localeCompare(statusBadge(b).label);
      // default: confidence, low to high — nulls/missing scores surface first
      return (a.confidence_score ?? -1) - (b.confidence_score ?? -1);
    });

    return [label, group, groupStats(group)];
  });
}

export default function OcrResultsPage() {
  const [filters, setFilters] = useState({
    search: "",
    ocr_status: "",
    verification_status: "",
    confidence: "",
    classification: "",
  });
  const [searchInput, setSearchInput] = useState("");
  const [sortBy, setSortBy] = useState("confidence");
  const [expandedGroups, setExpandedGroups] = useState(new Set());
  const [groupPage, setGroupPage] = useState(1);
  const [results, setResults] = useState([]);
  const [actionLoading, setActionLoading] = useState(null);

  const queryParams = useMemo(
    () => ({
      search: filters.search || undefined,
      page: 1,
      per_page: RESULTS_BATCH_SIZE,
      ocr_status: filters.ocr_status || undefined,
      verification_status: filters.verification_status || undefined,
      confidence: filters.confidence || undefined,
      classification: filters.classification || undefined,
    }),
    [filters],
  );

  const { data, isLoading, isError, refetch } = useQuery({
    queryKey: ["ocr-results", queryParams],
    queryFn: () => fetchOcrResults(queryParams),
  });

  const { data: statsData, isLoading: statsLoading } = useQuery({
    queryKey: ["ocr-results-stats"],
    queryFn: fetchOcrResultStats,
  });

  const stats = statsData?.data ?? {};
  const buckets = confidenceBuckets(stats);

  useEffect(() => {
    if (isError) toast.error("Unable to load OCR results");
  }, [isError]);
  useEffect(() => {
    if (!data) return;
    const raw = data.data?.results ?? data.data;
    setResults(Array.isArray(raw) ? raw : []);
  }, [data]);
  useEffect(() => {
    const t = setTimeout(
      () => setFilters((p) => ({ ...p, search: searchInput })),
      200,
    );
    return () => clearTimeout(t);
  }, [searchInput]);

  const allGroups = useMemo(
    () => groupResults(results, sortBy),
    [results, sortBy],
  );
  const totalGroupPages = Math.max(
    1,
    Math.ceil(allGroups.length / GROUPS_PER_PAGE),
  );
  const visibleGroups = allGroups.slice(
    (groupPage - 1) * GROUPS_PER_PAGE,
    groupPage * GROUPS_PER_PAGE,
  );
  const totalDocuments = allGroups.reduce(
    (sum, [, group]) => sum + group.length,
    0,
  );
  const visibleDocuments = visibleGroups.reduce(
    (sum, [, group]) => sum + group.length,
    0,
  );

  // Any change to what's being grouped resets to the first page of stores.
  useEffect(() => {
    setGroupPage(1);
  }, [filters, sortBy, results]);

  const loading = isLoading && results.length === 0;

  const updateFilter = (key, value) =>
    setFilters((p) => ({ ...p, [key]: value }));

  function toggleGroup(label) {
    setExpandedGroups((prev) => {
      const next = new Set(prev);
      next.has(label) ? next.delete(label) : next.add(label);
      return next;
    });
  }

  async function handleVerify(result) {
    setActionLoading(result.id);
    try {
      await verifyOcrResult(result.id);
      toast.success("OCR result verified");
      await refetch();
    } catch {
      toast.error("Unable to verify result");
    } finally {
      setActionLoading(null);
    }
  }

  async function handleReprocess(result) {
    setActionLoading(result.id);
    try {
      await reprocessOcrResult(result.id);
      toast.success("OCR reprocessing complete");
      await refetch();
    } catch (err) {
      toast.error(err.response?.data?.message ?? "Unable to reprocess");
    } finally {
      setActionLoading(null);
    }
  }

  return (
    <div className="space-y-6">
      <div>
        <h2 className="text-2xl font-semibold tracking-tight">OCR Results</h2>
        <p className="text-sm text-muted-foreground">
          Review, correct, and verify extracted document data
        </p>
      </div>

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <Card>
          <CardHeader className="pb-2">
            <CardTitle className="text-sm font-medium text-muted-foreground">
              Total Processed
            </CardTitle>
          </CardHeader>
          <CardContent>
            <p className="text-2xl font-semibold">
              {statsLoading ? "—" : (stats.summary?.total ?? 0)}
            </p>
          </CardContent>
        </Card>
        <Card>
          <CardHeader className="pb-2">
            <CardTitle className="text-sm font-medium text-muted-foreground">
              Needs Review
            </CardTitle>
          </CardHeader>
          <CardContent className="flex items-center gap-2">
            <p className="text-2xl font-semibold">
              {statsLoading ? "—" : (stats.summary?.needs_review ?? 0)}
            </p>
            {(stats.summary?.needs_review ?? 0) > 0 && (
              <Badge variant="secondary">action needed</Badge>
            )}
          </CardContent>
        </Card>
        <Card>
          <CardHeader className="pb-2">
            <CardTitle className="text-sm font-medium text-muted-foreground">
              Verified
            </CardTitle>
          </CardHeader>
          <CardContent className="flex items-center gap-2">
            <p className="text-2xl font-semibold">
              {statsLoading
                ? "—"
                : (stats.summary?.by_verification?.verified ?? 0)}
            </p>
            <ShieldCheck className="size-4 text-muted-foreground" />
          </CardContent>
        </Card>
        <Card>
          <CardHeader className="pb-2">
            <CardTitle className="text-sm font-medium text-muted-foreground">
              Avg. Confidence
            </CardTitle>
          </CardHeader>
          <CardContent>
            <p className="text-2xl font-semibold">
              {statsLoading ? "—" : `${stats.summary?.avg_confidence ?? 0}%`}
            </p>
          </CardContent>
        </Card>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>OCR Quality</CardTitle>
          <CardDescription>
            Distribution of extraction confidence across processed documents
          </CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="flex h-3 w-full overflow-hidden rounded-full bg-muted">
            <div
              className="h-full bg-accent"
              style={{ width: `${buckets.highPct}%` }}
              title={`High: ${buckets.highPct}%`}
            />
            <div
              className="h-full bg-secondary"
              style={{ width: `${buckets.mediumPct}%` }}
              title={`Medium: ${buckets.mediumPct}%`}
            />
            <div
              className="h-full bg-destructive"
              style={{ width: `${buckets.lowPct}%` }}
              title={`Low: ${buckets.lowPct}%`}
            />
          </div>
          <div className="flex flex-wrap gap-4 text-sm">
            <span className="flex items-center gap-1.5">
              <span className="size-2.5 rounded-full bg-accent" /> High{" "}
              {buckets.high}
            </span>
            <span className="flex items-center gap-1.5">
              <span className="size-2.5 rounded-full bg-secondary" /> Medium{" "}
              {buckets.medium}
            </span>
            <span className="flex items-center gap-1.5">
              <span className="size-2.5 rounded-full bg-destructive" /> Low{" "}
              {buckets.low}
            </span>
            <span className="ml-auto text-muted-foreground">
              Avg processing: {stats.summary?.avg_processing_time_ms ?? 0} ms
            </span>
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>Extraction Records</CardTitle>
          <CardDescription>
            Documents processed by the OCR pipeline
          </CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="flex justify-end">
            <select
              className="h-8 rounded-lg border border-input bg-background px-2 text-sm"
              value={sortBy}
              onChange={(e) => setSortBy(e.target.value)}
            >
              <option value="confidence">Sort: Confidence (low to high)</option>
              <option value="processed">Sort: Date processed</option>
              <option value="status">Sort: Status</option>
            </select>
          </div>
          <div className="grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-5">
            <div className="space-y-1.5 lg:col-span-1">
              <Label htmlFor="filter-search" className="invisible">
                Search
              </Label>
              <div className="relative">
                <Search className="absolute left-2.5 top-2.5 size-4 text-muted-foreground" />
                <Input
                  id="filter-search"
                  className="pl-8"
                  placeholder="Search..."
                  value={searchInput}
                  onChange={(e) => setSearchInput(e.target.value)}
                />
              </div>
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="filter-ocr-status">OCR Status</Label>
              <select
                id="filter-ocr-status"
                className="h-8 w-full rounded-lg border border-input bg-background px-2 text-sm"
                value={filters.ocr_status}
                onChange={(e) => updateFilter("ocr_status", e.target.value)}
              >
                {OCR_STATUS_OPTIONS.map((opt) => (
                  <option key={opt.value} value={opt.value}>
                    {opt.label}
                  </option>
                ))}
              </select>
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="filter-verification">Verification</Label>
              <select
                id="filter-verification"
                className="h-8 w-full rounded-lg border border-input bg-background px-2 text-sm"
                value={filters.verification_status}
                onChange={(e) =>
                  updateFilter("verification_status", e.target.value)
                }
              >
                {VERIFICATION_STATUS_OPTIONS.map((opt) => (
                  <option key={opt.value} value={opt.value}>
                    {opt.label}
                  </option>
                ))}
              </select>
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="filter-confidence">Confidence</Label>
              <select
                id="filter-confidence"
                className="h-8 w-full rounded-lg border border-input bg-background px-2 text-sm"
                value={filters.confidence}
                onChange={(e) => updateFilter("confidence", e.target.value)}
              >
                {CONFIDENCE_OPTIONS.map((opt) => (
                  <option key={opt.value} value={opt.value}>
                    {opt.label}
                  </option>
                ))}
              </select>
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="filter-classification">Type</Label>
              <select
                id="filter-classification"
                className="h-8 w-full rounded-lg border border-input bg-background px-2 text-sm"
                value={filters.classification}
                onChange={(e) => updateFilter("classification", e.target.value)}
              >
                <option value="">All types</option>
                {CLASSIFICATIONS.map((value) => (
                  <option key={value} value={value}>
                    {value.replace(/_/g, " ")}
                  </option>
                ))}
              </select>
            </div>
          </div>

          {loading ? (
            <div className="space-y-3">
              <Skeleton className="h-10 w-full" />
              <Skeleton className="h-10 w-full" />
            </div>
          ) : results.length === 0 ? (
            <div className="flex flex-col items-center gap-3 py-10 text-center text-sm text-muted-foreground">
              <ScanText className="size-8" />
              <p>No OCR results match your filters.</p>
            </div>
          ) : (
            <div className="space-y-4">
              {visibleGroups.map(([label, group, stats]) => {
                const isExpanded = expandedGroups.has(label);
                return (
                  <Card key={label} className="overflow-hidden py-0">
                    <button
                      type="button"
                      className="flex w-full flex-wrap items-center justify-between gap-2 border-b bg-muted/40 px-4 py-2.5 text-left"
                      onClick={() => toggleGroup(label)}
                      aria-expanded={isExpanded}
                    >
                      <div>
                        <p className="text-sm font-semibold">{label}</p>
                        <p className="text-xs text-muted-foreground">
                          {group.length} document{group.length === 1 ? "" : "s"}
                          {group[0].request_number
                            ? ` · ${group[0].request_number}`
                            : ""}
                        </p>
                      </div>
                      <div className="flex items-center gap-2">
                        {stats.avg != null && (
                          <Badge
                            variant={
                              stats.avg < 75
                                ? "destructive"
                                : stats.avg < 90
                                  ? "secondary"
                                  : "default"
                            }
                          >
                            Avg {stats.avg}%
                          </Badge>
                        )}
                        {stats.needsReview > 0 && (
                          <Badge variant="secondary">
                            {stats.needsReview} need review
                          </Badge>
                        )}
                        <ChevronDown
                          className={`size-4 text-muted-foreground transition-transform ${isExpanded ? "rotate-180" : ""}`}
                        />
                      </div>
                    </button>
                    {isExpanded && (
                      <div className="overflow-x-auto">
                        <Table>
                          <TableHeader>
                            <TableRow>
                              <TableHead>Document</TableHead>
                              <TableHead>Status</TableHead>
                              <TableHead>Confidence</TableHead>
                              <TableHead>Processed</TableHead>
                              <TableHead className="text-right">
                                Actions
                              </TableHead>
                            </TableRow>
                          </TableHeader>
                          <TableBody>
                            {group.map((result) => {
                              const badge = statusBadge(result);
                              return (
                                <TableRow key={result.id}>
                                  <TableCell className="max-w-[200px]">
                                    <div className="flex items-center gap-1.5">
                                      <p className="truncate font-medium">
                                        {result.original_name ??
                                          result.file_name ??
                                          "Document"}
                                      </p>
                                      {result._versionCount > 1 && (
                                        <Badge
                                          variant="outline"
                                          className="shrink-0 text-[10px]"
                                        >
                                          {result._versionCount}v
                                        </Badge>
                                      )}
                                    </div>
                                    <p className="text-xs text-muted-foreground">
                                      {result.classification?.replace(
                                        /_/g,
                                        " ",
                                      ) ?? "—"}
                                    </p>
                                  </TableCell>
                                  <TableCell>
                                    <Badge variant={badge.variant}>
                                      {badge.label}
                                    </Badge>
                                  </TableCell>
                                  <TableCell>
                                    <span
                                      className={
                                        result.confidence_score < 75
                                          ? "font-medium text-destructive"
                                          : result.confidence_score < 90
                                            ? "font-medium"
                                            : ""
                                      }
                                    >
                                      {result.confidence_score != null
                                        ? `${Math.round(result.confidence_score)}%`
                                        : "—"}
                                    </span>
                                  </TableCell>
                                  <TableCell className="text-sm">
                                    {formatDate(result.processed_at)}
                                  </TableCell>
                                  <TableCell className="text-right">
                                    <div className="flex items-center justify-end gap-1.5">
                                      <Button
                                        variant="outline"
                                        size="sm"
                                        nativeButton={false}
                                        render={
                                          <Link
                                            to={`/ocr-results/${result.id}`}
                                          />
                                        }
                                      >
                                        <Eye className="size-3.5" /> Review
                                      </Button>
                                      {result.verification_status !==
                                        "verified" && (
                                        <Button
                                          variant="outline"
                                          size="sm"
                                          onClick={() => handleVerify(result)}
                                          disabled={actionLoading === result.id}
                                        >
                                          <ShieldCheck className="size-3.5" />{" "}
                                          Verify
                                        </Button>
                                      )}
                                      <Button
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => handleReprocess(result)}
                                        disabled={actionLoading === result.id}
                                        title="Reprocess OCR"
                                      >
                                        <RefreshCw className="size-3.5" />
                                      </Button>
                                    </div>
                                  </TableCell>
                                </TableRow>
                              );
                            })}
                          </TableBody>
                        </Table>
                      </div>
                    )}
                  </Card>
                );
              })}
            </div>
          )}

          <div className="flex flex-wrap items-center justify-between gap-3 pt-2">
            <p className="text-sm text-muted-foreground">
              Showing {visibleGroups.length} of {allGroups.length} stores (
              {visibleDocuments} of {totalDocuments} documents)
            </p>
            <div className="flex items-center gap-2">
              <Button
                variant="outline"
                size="sm"
                disabled={groupPage <= 1}
                onClick={() => setGroupPage((p) => p - 1)}
              >
                Previous
              </Button>
              <span className="text-sm">
                Page {groupPage} of {totalGroupPages}
              </span>
              <Button
                variant="outline"
                size="sm"
                disabled={groupPage >= totalGroupPages}
                onClick={() => setGroupPage((p) => p + 1)}
              >
                Next
              </Button>
            </div>
          </div>
        </CardContent>
      </Card>
    </div>
  );
}
