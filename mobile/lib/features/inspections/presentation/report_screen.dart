import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/constants/app_constants.dart';
import '../../../core/errors/api_exception.dart';
import '../../../core/utils/formatters.dart';
import '../../../widgets/badges/app_badge.dart';
import '../../../widgets/buttons/app_button.dart';
import '../../../widgets/cards/app_card.dart';
import '../../../core/providers/app_providers.dart';
import '../../../widgets/feedback/app_error_state.dart';
import '../models/assignment.dart';
import '../models/report.dart';
import '../providers/assignments_providers.dart';

/// Inspection report screen — `GET /v1/inspection-assignments/{id}/report`
/// and `PUT /{id}/report` (`overall_assessment`, `recommendations`, `notes`).
///
/// Shows the report summary (compliant/non_compliant/needs_correction counts,
/// violations) plus editable assessment fields. Saved via
/// `ReportDataSource.update` which also refreshes the assignment's `notes`.
class ReportScreen extends ConsumerStatefulWidget {
  const ReportScreen({
    super.key,
    required this.assignmentId,
    this.readOnly = false,
  });

  final String assignmentId;
  final bool readOnly;

  @override
  ConsumerState<ReportScreen> createState() => _ReportScreenState();
}

class _ReportScreenState extends ConsumerState<ReportScreen> {
  final TextEditingController _assessment = TextEditingController();
  final TextEditingController _recommendations = TextEditingController();
  final TextEditingController _notes = TextEditingController();

  bool _saving = false;
  bool _initialized = false;

  @override
  void dispose() {
    _assessment.dispose();
    _recommendations.dispose();
    _notes.dispose();
    super.dispose();
  }

  void _ensureInit(InspectionReport report) {
    if (_initialized) return;
    _assessment.text = report.overallAssessment ?? '';
    _recommendations.text = report.recommendations ?? '';
    // Notes are stored on the assignment, not the report; preload from report's
    // assignment notes when available via ref (fallback to empty here — assignment
    // notes are shown in detail screen). Keep _notes empty initially.
    _initialized = true;
  }

  @override
  Widget build(BuildContext context) {
    final ColorScheme scheme = Theme.of(context).colorScheme;
    final AsyncValue<InspectionReport> reportAsync = ref.watch(
      reportProvider(widget.assignmentId),
    );
    final AsyncValue<InspectionAssignment> detailAsync = ref.watch(
      assignmentDetailProvider(widget.assignmentId),
    );

    return Scaffold(
      backgroundColor: scheme.surface,
      appBar: AppBar(
        title: const Text('Inspection report'),
        centerTitle: false,
      ),
      body: reportAsync.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (error, _) => AppErrorState(
          message: error is ApiException
              ? error.message
              : 'Could not load report.',
          onRetry: () => ref.invalidate(reportProvider(widget.assignmentId)),
        ),
        data: (report) {
          _ensureInit(report);

          final Map<String, dynamic>? summary = report.summary;
          final bool readOnly = widget.readOnly;

          // Pull assignment notes for the notes field. Hydrate once without setState.
          final String? assignmentNotes = detailAsync.valueOrNull?.notes;
          if (_notes.text.isEmpty &&
              assignmentNotes != null &&
              assignmentNotes.isNotEmpty) {
            // Direct controller assignment is safe during build (no setState needed).
            _notes.text = assignmentNotes;
          }

          return RefreshIndicator(
            onRefresh: () async {
              ref.invalidate(reportProvider(widget.assignmentId));
              ref.invalidate(assignmentDetailProvider(widget.assignmentId));
              await Future<void>.delayed(const Duration(milliseconds: 350));
            },
            child: SingleChildScrollView(
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.fromLTRB(
                AppSpacing.lg,
                AppSpacing.md,
                AppSpacing.lg,
                AppSpacing.xxl,
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  AppCard(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          children: [
                            Container(
                              width: 36,
                              height: 36,
                              decoration: BoxDecoration(
                                color: scheme.primaryContainer,
                                borderRadius: BorderRadius.circular(
                                  AppRadius.md,
                                ),
                              ),
                              child: Icon(
                                Icons.description_outlined,
                                size: 18,
                                color: scheme.onPrimaryContainer,
                              ),
                            ),
                            const SizedBox(width: AppSpacing.md),
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text(
                                    report.establishmentName ??
                                        'Inspection #${report.id}',
                                    style: Theme.of(context)
                                        .textTheme
                                        .titleSmall
                                        ?.copyWith(fontWeight: FontWeight.w700),
                                  ),
                                  const SizedBox(height: 2),
                                  Text(
                                    '${report.status} · ${report.inspectionDate != null ? Formatters.date(report.inspectionDate) : '—'}',
                                    style: Theme.of(context).textTheme.bodySmall
                                        ?.copyWith(
                                          color: scheme.onSurfaceVariant,
                                        ),
                                  ),
                                ],
                              ),
                            ),
                            AppBadge(
                              label: report.inspectorName ?? '—',
                              variant: AppBadgeVariant.outline,
                              compact: true,
                            ),
                          ],
                        ),
                        if (summary != null) ...[
                          const SizedBox(height: AppSpacing.md),
                          Wrap(
                            spacing: AppSpacing.sm,
                            runSpacing: AppSpacing.sm,
                            children: [
                              _SummaryChip(
                                label: '${summary['total_items'] ?? 0} items',
                                icon: Icons.list_alt_rounded,
                                variant: AppBadgeVariant.neutral,
                              ),
                              _SummaryChip(
                                label: '${summary['compliant'] ?? 0} compliant',
                                icon: Icons.check_circle_outline_rounded,
                                variant: AppBadgeVariant.success,
                              ),
                              _SummaryChip(
                                label:
                                    '${summary['non_compliant'] ?? 0} non-compliant',
                                icon: Icons.cancel_outlined,
                                variant: AppBadgeVariant.danger,
                              ),
                              _SummaryChip(
                                label:
                                    '${summary['needs_correction'] ?? 0} needs correction',
                                icon: Icons.warning_amber_rounded,
                                variant: AppBadgeVariant.warning,
                              ),
                              _SummaryChip(
                                label:
                                    '${summary['violations'] ?? 0} violations',
                                icon: Icons.gavel_outlined,
                                variant: AppBadgeVariant.outline,
                              ),
                            ],
                          ),
                        ],
                        if (report.generatedAt != null) ...[
                          const SizedBox(height: AppSpacing.xs),
                          Text(
                            'Generated: ${Formatters.dateTime(report.generatedAt)}',
                            style: Theme.of(context).textTheme.labelSmall
                                ?.copyWith(color: scheme.onSurfaceVariant),
                          ),
                        ],
                      ],
                    ),
                  ),
                  const SizedBox(height: AppSpacing.lg),
                  AppCard(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'Overall assessment',
                          style: Theme.of(context).textTheme.titleSmall
                              ?.copyWith(fontWeight: FontWeight.w700),
                        ),
                        const SizedBox(height: AppSpacing.sm),
                        if (readOnly)
                          Text(
                            report.overallAssessment?.isNotEmpty == true
                                ? report.overallAssessment!
                                : 'No assessment yet.',
                            style: Theme.of(context).textTheme.bodyMedium
                                ?.copyWith(
                                  fontStyle:
                                      report.overallAssessment?.isNotEmpty ==
                                          true
                                      ? FontStyle.normal
                                      : FontStyle.italic,
                                  color:
                                      report.overallAssessment?.isNotEmpty ==
                                          true
                                      ? null
                                      : scheme.onSurfaceVariant,
                                ),
                          )
                        else
                          TextField(
                            controller: _assessment,
                            maxLines: 5,
                            minLines: 3,
                            decoration: InputDecoration(
                              hintText: 'Summarize the inspection outcome…',
                              border: OutlineInputBorder(
                                borderRadius: BorderRadius.circular(
                                  AppRadius.md,
                                ),
                              ),
                              contentPadding: const EdgeInsets.all(
                                AppSpacing.md,
                              ),
                            ),
                          ),
                      ],
                    ),
                  ),
                  const SizedBox(height: AppSpacing.lg),
                  AppCard(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'Recommendations',
                          style: Theme.of(context).textTheme.titleSmall
                              ?.copyWith(fontWeight: FontWeight.w700),
                        ),
                        const SizedBox(height: AppSpacing.sm),
                        if (readOnly)
                          Text(
                            report.recommendations?.isNotEmpty == true
                                ? report.recommendations!
                                : 'No recommendations yet.',
                            style: Theme.of(context).textTheme.bodyMedium
                                ?.copyWith(
                                  fontStyle:
                                      report.recommendations?.isNotEmpty == true
                                      ? FontStyle.normal
                                      : FontStyle.italic,
                                  color:
                                      report.recommendations?.isNotEmpty == true
                                      ? null
                                      : scheme.onSurfaceVariant,
                                ),
                          )
                        else
                          TextField(
                            controller: _recommendations,
                            maxLines: 5,
                            minLines: 3,
                            decoration: InputDecoration(
                              hintText:
                                  'Corrective actions, re-inspection notes…',
                              border: OutlineInputBorder(
                                borderRadius: BorderRadius.circular(
                                  AppRadius.md,
                                ),
                              ),
                              contentPadding: const EdgeInsets.all(
                                AppSpacing.md,
                              ),
                            ),
                          ),
                      ],
                    ),
                  ),
                  const SizedBox(height: AppSpacing.lg),
                  AppCard(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'Assignment notes',
                          style: Theme.of(context).textTheme.titleSmall
                              ?.copyWith(fontWeight: FontWeight.w700),
                        ),
                        const SizedBox(height: AppSpacing.xs),
                        Text(
                          'Saved to the assignment (also visible on the detail screen).',
                          style: Theme.of(context).textTheme.bodySmall
                              ?.copyWith(color: scheme.onSurfaceVariant),
                        ),
                        const SizedBox(height: AppSpacing.md),
                        if (readOnly)
                          Text(
                            _notes.text.isNotEmpty
                                ? _notes.text
                                : (assignmentNotes?.isNotEmpty == true
                                      ? assignmentNotes!
                                      : 'No notes.'),
                            style: Theme.of(context).textTheme.bodyMedium
                                ?.copyWith(
                                  fontStyle:
                                      (_notes.text.isNotEmpty ||
                                          (assignmentNotes?.isNotEmpty ??
                                              false))
                                      ? FontStyle.normal
                                      : FontStyle.italic,
                                  color:
                                      (_notes.text.isNotEmpty ||
                                          (assignmentNotes?.isNotEmpty ??
                                              false))
                                      ? null
                                      : scheme.onSurfaceVariant,
                                ),
                          )
                        else
                          TextField(
                            controller: _notes,
                            maxLines: 3,
                            minLines: 2,
                            decoration: InputDecoration(
                              hintText:
                                  assignmentNotes ??
                                  'Remarks for this inspection…',
                              border: OutlineInputBorder(
                                borderRadius: BorderRadius.circular(
                                  AppRadius.md,
                                ),
                              ),
                              contentPadding: const EdgeInsets.all(
                                AppSpacing.md,
                              ),
                            ),
                          ),
                      ],
                    ),
                  ),
                  if (!readOnly) ...[
                    const SizedBox(height: AppSpacing.xl),
                    AppButton(
                      onPressed: _saving ? () {} : () => _save(report),
                      label: 'Save report',
                      icon: Icons.save_outlined,
                      loading: _saving,
                    ),
                  ],
                  const SizedBox(height: AppSpacing.lg),
                  if (report.results.isNotEmpty) ...[
                    Text(
                      'Results',
                      style: Theme.of(context).textTheme.titleSmall?.copyWith(
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                    const SizedBox(height: AppSpacing.md),
                    ...report.results.map(
                      (r) => Padding(
                        padding: const EdgeInsets.only(bottom: AppSpacing.sm),
                        child: AppCard(
                          padding: const EdgeInsets.all(AppSpacing.md),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Row(
                                children: [
                                  Expanded(
                                    child: Text(
                                      r.itemTitle ??
                                          'Item #${r.checklistItemId}',
                                      style: Theme.of(context)
                                          .textTheme
                                          .bodyMedium
                                          ?.copyWith(
                                            fontWeight: FontWeight.w600,
                                          ),
                                    ),
                                  ),
                                  const SizedBox(width: AppSpacing.sm),
                                  AppBadge(
                                    label: Formatters.humanize(
                                      r.complianceStatus,
                                    ),
                                    variant: switch (r.complianceStatus) {
                                      'compliant' => AppBadgeVariant.success,
                                      'non_compliant' => AppBadgeVariant.danger,
                                      'needs_correction' =>
                                        AppBadgeVariant.warning,
                                      _ => AppBadgeVariant.neutral,
                                    },
                                    compact: true,
                                  ),
                                ],
                              ),
                              if (r.remarks?.isNotEmpty == true) ...[
                                const SizedBox(height: 4),
                                Text(
                                  r.remarks!,
                                  style: Theme.of(context).textTheme.bodySmall
                                      ?.copyWith(
                                        color: scheme.onSurfaceVariant,
                                      ),
                                ),
                              ],
                              if (r.checklistName != null) ...[
                                const SizedBox(height: 4),
                                Text(
                                  r.checklistName!,
                                  style: Theme.of(context).textTheme.labelSmall
                                      ?.copyWith(
                                        color: scheme.onSurfaceVariant,
                                      ),
                                ),
                              ],
                            ],
                          ),
                        ),
                      ),
                    ),
                  ],
                  if (report.violations.isNotEmpty) ...[
                    const SizedBox(height: AppSpacing.md),
                    Text(
                      'Violations',
                      style: Theme.of(context).textTheme.titleSmall?.copyWith(
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                    const SizedBox(height: AppSpacing.md),
                    ...report.violations.map(
                      (v) => Padding(
                        padding: const EdgeInsets.only(bottom: AppSpacing.sm),
                        child: AppCard(
                          padding: const EdgeInsets.all(AppSpacing.md),
                          child: Row(
                            children: [
                              Icon(
                                Icons.gavel_outlined,
                                size: 18,
                                color: scheme.error,
                              ),
                              const SizedBox(width: AppSpacing.sm),
                              Expanded(
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Text(
                                      v.title,
                                      style: Theme.of(context)
                                          .textTheme
                                          .bodyMedium
                                          ?.copyWith(
                                            fontWeight: FontWeight.w600,
                                          ),
                                    ),
                                    if (v.description != null)
                                      Text(
                                        v.description!,
                                        style: Theme.of(context)
                                            .textTheme
                                            .bodySmall
                                            ?.copyWith(
                                              color: scheme.onSurfaceVariant,
                                            ),
                                        maxLines: 2,
                                        overflow: TextOverflow.ellipsis,
                                      ),
                                  ],
                                ),
                              ),
                              AppBadge(
                                label: Formatters.humanize(v.severity),
                                variant: v.severity == 'major'
                                    ? AppBadgeVariant.danger
                                    : v.severity == 'moderate'
                                    ? AppBadgeVariant.warning
                                    : AppBadgeVariant.neutral,
                                compact: true,
                              ),
                            ],
                          ),
                        ),
                      ),
                    ),
                  ],
                ],
              ),
            ),
          );
        },
      ),
    );
  }

  Future<void> _save(InspectionReport report) async {
    final String assessment = _assessment.text.trim();
    final String recommendations = _recommendations.text.trim();
    final String notes = _notes.text.trim();

    setState(() => _saving = true);
    try {
      final ds = ref.read(reportDataSourceProvider);
      await ds.update(
        assignmentId: widget.assignmentId,
        overallAssessment: assessment.isEmpty ? null : assessment,
        recommendations: recommendations.isEmpty ? null : recommendations,
        notes: notes.isEmpty ? null : notes,
      );
      if (!mounted) return;
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(const SnackBar(content: Text('Report saved.')));
      ref.invalidate(reportProvider(widget.assignmentId));
      ref.invalidate(assignmentDetailProvider(widget.assignmentId));
      ref.invalidate(pendingSyncCountProvider);
    } on ApiException catch (e) {
      if (e.isNetwork) {
        await ref
            .read(syncServiceProvider)
            .enqueueReportUpdate(
              assignmentId: widget.assignmentId,
              overallAssessment: assessment.isEmpty ? null : assessment,
              recommendations: recommendations.isEmpty ? null : recommendations,
              notes: notes.isEmpty ? null : notes,
            );
        ref.invalidate(pendingSyncCountProvider);
        if (!mounted) return;
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('You are offline — report will sync when online.'),
          ),
        );
      } else {
        if (!mounted) return;
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(e.message)));
      }
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }
}

class _SummaryChip extends StatelessWidget {
  const _SummaryChip({
    required this.label,
    required this.icon,
    required this.variant,
  });

  final String label;
  final IconData icon;
  final AppBadgeVariant variant;

  @override
  Widget build(BuildContext context) =>
      AppBadge(label: label, icon: icon, variant: variant, compact: true);
}
