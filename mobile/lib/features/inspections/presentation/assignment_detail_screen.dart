import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'package:go_router/go_router.dart';

import '../../../core/constants/app_constants.dart';
import '../../../core/errors/api_exception.dart';
import '../../../core/providers/app_providers.dart';
import '../../../core/utils/formatters.dart';
import '../../../router/app_routes.dart';
import '../../../widgets/badges/app_badge.dart';
import '../../../widgets/buttons/app_button.dart';
import '../../../widgets/cards/app_card.dart';
import '../../../widgets/feedback/app_error_state.dart';
import '../models/assignment.dart';
import '../providers/assignments_providers.dart';

/// Detail view for a single inspection assignment.
///
/// Fetches `GET /v1/inspection-assignments/{id}` and exposes the
/// inspector lifecycle: `assigned|downloaded → in_progress → submitted`.
///
/// Checklist/report/violations are wired in Phase 2–4; this screen
/// intentionally surfaces placeholders for those sections so the flow
/// is discoverable even before those phases land.
class AssignmentDetailScreen extends ConsumerStatefulWidget {
  const AssignmentDetailScreen({super.key, required this.assignmentId});

  final String assignmentId;

  @override
  ConsumerState<AssignmentDetailScreen> createState() =>
      _AssignmentDetailScreenState();
}

class _AssignmentDetailScreenState
    extends ConsumerState<AssignmentDetailScreen> {
  bool _starting = false;
  bool _submitting = false;
  final TextEditingController _notesCtrl = TextEditingController();

  @override
  void dispose() {
    _notesCtrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final ColorScheme scheme = Theme.of(context).colorScheme;
    final AsyncValue<InspectionAssignment> detail = ref.watch(
      assignmentDetailProvider(widget.assignmentId),
    );

    return Scaffold(
      backgroundColor: scheme.surface,
      appBar: AppBar(
        title: const Text('Inspection detail'),
        centerTitle: false,
      ),
      body: detail.when(
        loading: () => const Center(child: AppSpinner(size: 28)),
        error: (error, _) => AppErrorState(
          message: error is ApiException
              ? error.message
              : 'Could not load inspection. Pull to retry.',
          onRetry: () =>
              ref.invalidate(assignmentDetailProvider(widget.assignmentId)),
        ),
        data: (assignment) {
          final InspectionRequestSummary? req = assignment.inspectionRequest;
          final bool canStart =
              assignment.status == 'assigned' ||
              assignment.status == 'downloaded';
          final bool inProgress = assignment.status == 'in_progress';
          final bool submitted = assignment.status == 'submitted';
          final bool cancelled = assignment.status == 'cancelled';

          return RefreshIndicator(
            onRefresh: () async {
              ref.invalidate(assignmentDetailProvider(widget.assignmentId));
              // Give the provider a frame to re-fetch.
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
                  // Header card — business/applicant, request #, status.
                  AppCard(
                    padding: const EdgeInsets.all(AppSpacing.lg),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Container(
                              width: 44,
                              height: 44,
                              decoration: BoxDecoration(
                                color: scheme.primaryContainer,
                                borderRadius: BorderRadius.circular(
                                  AppRadius.md,
                                ),
                              ),
                              child: Icon(
                                Icons.storefront_rounded,
                                color: scheme.onPrimaryContainer,
                              ),
                            ),
                            const SizedBox(width: AppSpacing.md),
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text(
                                    req?.displayName ?? 'Unnamed inspection',
                                    style: Theme.of(context)
                                        .textTheme
                                        .titleMedium
                                        ?.copyWith(fontWeight: FontWeight.w700),
                                  ),
                                  const SizedBox(height: 2),
                                  Text(
                                    req?.requestNumber ?? '#${assignment.id}',
                                    style: Theme.of(context).textTheme.bodySmall
                                        ?.copyWith(
                                          color: scheme.onSurfaceVariant,
                                        ),
                                  ),
                                  const SizedBox(height: AppSpacing.xs),
                                  Text(
                                    req?.addressLabel ?? 'Address not on file',
                                    style: Theme.of(
                                      context,
                                    ).textTheme.bodySmall,
                                  ),
                                ],
                              ),
                            ),
                            const SizedBox(width: AppSpacing.sm),
                            _StatusBadge(status: assignment.status),
                          ],
                        ),
                        const SizedBox(height: AppSpacing.md),
                        Wrap(
                          spacing: AppSpacing.sm,
                          runSpacing: AppSpacing.sm,
                          children: [
                            if (req?.inspectionCategory != null)
                              AppBadge(
                                label: req!.inspectionCategory!.name,
                                variant: AppBadgeVariant.primary,
                                icon: Icons.fact_check_outlined,
                                compact: true,
                              ),
                            if (req?.applicationType != null)
                              AppBadge(
                                label: req!.applicationType!.name,
                                variant: AppBadgeVariant.outline,
                                compact: true,
                              ),
                          ],
                        ),
                        const SizedBox(height: AppSpacing.md),
                        _MetaRow(
                          icon: Icons.event_outlined,
                          label: assignment.scheduleLabel,
                        ),
                        if (assignment.assignedAt != null) ...[
                          const SizedBox(height: AppSpacing.xs),
                          _MetaRow(
                            icon: Icons.assignment_ind_outlined,
                            label:
                                'Assigned ${Formatters.date(assignment.assignedAt!.toIso8601String())}',
                          ),
                        ],
                        if (assignment.submittedAt != null) ...[
                          const SizedBox(height: AppSpacing.xs),
                          _MetaRow(
                            icon: Icons.check_circle_outline_rounded,
                            label:
                                'Submitted ${Formatters.date(assignment.submittedAt!.toIso8601String())}',
                          ),
                        ],
                      ],
                    ),
                  ),
                  const SizedBox(height: AppSpacing.lg),

                  // Applicant / contact block.
                  AppCard(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'Applicant',
                          style: Theme.of(context).textTheme.titleSmall
                              ?.copyWith(fontWeight: FontWeight.w700),
                        ),
                        const SizedBox(height: AppSpacing.md),
                        _InfoRow(
                          label: 'Name',
                          value: req?.applicantName ?? '—',
                        ),
                        const SizedBox(height: AppSpacing.sm),
                        _InfoRow(
                          label: 'Address',
                          value:
                              req?.applicantAddress ?? req?.addressLabel ?? '—',
                        ),
                        const SizedBox(height: AppSpacing.sm),
                        _InfoRow(
                          label: 'Contact',
                          value: req?.contactNumber ?? '—',
                        ),
                        if (req?.businessName?.isNotEmpty == true) ...[
                          const SizedBox(height: AppSpacing.sm),
                          _InfoRow(
                            label: 'Business',
                            value: req!.businessName!,
                          ),
                        ],
                        if (req?.establishment != null) ...[
                          const SizedBox(height: AppSpacing.md),
                          Divider(color: scheme.outlineVariant),
                          const SizedBox(height: AppSpacing.md),
                          Text(
                            'Establishment',
                            style: Theme.of(context).textTheme.titleSmall
                                ?.copyWith(fontWeight: FontWeight.w700),
                          ),
                          const SizedBox(height: AppSpacing.md),
                          _InfoRow(
                            label: 'Name',
                            value: req!.establishment!.name,
                          ),
                          const SizedBox(height: AppSpacing.sm),
                          _InfoRow(
                            label: 'Type',
                            value: req.establishment!.businessType ?? '—',
                          ),
                          const SizedBox(height: AppSpacing.sm),
                          _InfoRow(
                            label: 'Barangay',
                            value: req.establishment!.barangay ?? '—',
                          ),
                        ],
                      ],
                    ),
                  ),
                  const SizedBox(height: AppSpacing.lg),

                  // Notes (editable before submit; read-only after).
                  AppCard(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'Notes',
                          style: Theme.of(context).textTheme.titleSmall
                              ?.copyWith(fontWeight: FontWeight.w700),
                        ),
                        const SizedBox(height: AppSpacing.sm),
                        if (submitted || cancelled)
                          Text(
                            assignment.notes?.isNotEmpty == true
                                ? assignment.notes!
                                : 'No notes.',
                            style: Theme.of(context).textTheme.bodyMedium,
                          )
                        else
                          TextField(
                            controller: _notesCtrl,
                            maxLines: 3,
                            minLines: 3,
                            decoration: InputDecoration(
                              hintText:
                                  assignment.notes ??
                                  'Add remarks for this inspection…',
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
                    onTap: inProgress || submitted
                        ? () => context.push(
                            '${AppRoutes.inspectionDetailPath(widget.assignmentId)}/checklist',
                            extra: {'readOnly': !inProgress},
                          )
                        : null,
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          children: [
                            Icon(
                              Icons.fact_check_outlined,
                              size: 18,
                              color: scheme.onSurfaceVariant,
                            ),
                            const SizedBox(width: AppSpacing.sm),
                            Text(
                              'Checklist',
                              style: Theme.of(context).textTheme.titleSmall
                                  ?.copyWith(fontWeight: FontWeight.w700),
                            ),
                            const Spacer(),
                            AppBadge(
                              label: submitted
                                  ? 'View'
                                  : inProgress
                                  ? 'Ready'
                                  : 'Locked',
                              variant: submitted || inProgress
                                  ? AppBadgeVariant.success
                                  : AppBadgeVariant.neutral,
                              compact: true,
                            ),
                            if (inProgress || submitted) ...[
                              const SizedBox(width: AppSpacing.sm),
                              Icon(
                                Icons.chevron_right_rounded,
                                size: 18,
                                color: scheme.onSurfaceVariant,
                              ),
                            ],
                          ],
                        ),
                        const SizedBox(height: AppSpacing.sm),
                        Text(
                          submitted
                              ? 'View the saved checklist and evidence. (Phase 2)'
                              : inProgress
                              ? 'Complete the compliance checklist and attach photo evidence before submitting.'
                              : 'Checklist becomes available once you start the inspection. (Phase 2)',
                          style: Theme.of(context).textTheme.bodySmall
                              ?.copyWith(color: scheme.onSurfaceVariant),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: AppSpacing.md),
                  AppCard(
                    onTap: () => context.go(AppRoutes.violations),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          children: [
                            Icon(
                              Icons.gavel_outlined,
                              size: 18,
                              color: scheme.onSurfaceVariant,
                            ),
                            const SizedBox(width: AppSpacing.sm),
                            Text(
                              'Violations',
                              style: Theme.of(context).textTheme.titleSmall
                                  ?.copyWith(fontWeight: FontWeight.w700),
                            ),
                            const Spacer(),
                            Icon(
                              Icons.chevron_right_rounded,
                              size: 18,
                              color: scheme.onSurfaceVariant,
                            ),
                          ],
                        ),
                        const SizedBox(height: AppSpacing.sm),
                        Text(
                          'Record violations from the checklist or the Violations tab. Tap to view all violations.',
                          style: Theme.of(context).textTheme.bodySmall
                              ?.copyWith(color: scheme.onSurfaceVariant),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: AppSpacing.md),
                  AppCard(
                    onTap: inProgress || submitted
                        ? () => context.push(
                            '${AppRoutes.inspectionDetailPath(widget.assignmentId)}/report',
                            extra: {'readOnly': !inProgress},
                          )
                        : null,
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          children: [
                            Icon(
                              Icons.description_outlined,
                              size: 18,
                              color: scheme.onSurfaceVariant,
                            ),
                            const SizedBox(width: AppSpacing.sm),
                            Text(
                              'Report',
                              style: Theme.of(context).textTheme.titleSmall
                                  ?.copyWith(fontWeight: FontWeight.w700),
                            ),
                            const Spacer(),
                            if (inProgress || submitted)
                              Icon(
                                Icons.chevron_right_rounded,
                                size: 18,
                                color: scheme.onSurfaceVariant,
                              ),
                          ],
                        ),
                        const SizedBox(height: AppSpacing.sm),
                        Text(
                          submitted
                              ? 'View the saved report and summary. Tap to open.'
                              : inProgress
                              ? 'Capture overall assessment and recommendations. Tap to open.'
                              : 'Report becomes available once you start the inspection.',
                          style: Theme.of(context).textTheme.bodySmall
                              ?.copyWith(color: scheme.onSurfaceVariant),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: AppSpacing.xl),

                  // Actions — status-gated.
                  if (canStart)
                    AppButton(
                      onPressed: _starting
                          ? () {}
                          : () => _start(context, assignment),
                      label: 'Start inspection',
                      icon: Icons.play_arrow_rounded,
                      loading: _starting,
                    )
                  else if (inProgress)
                    Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        AppButton(
                          onPressed: _submitting
                              ? () {}
                              : () => _submit(context, assignment),
                          label: 'Submit inspection',
                          icon: Icons.send_rounded,
                          loading: _submitting,
                        ),
                        const SizedBox(height: AppSpacing.sm),
                        Text(
                          'Submitting marks the inspection as completed and notifies the resident.',
                          textAlign: TextAlign.center,
                          style: Theme.of(context).textTheme.bodySmall
                              ?.copyWith(color: scheme.onSurfaceVariant),
                        ),
                      ],
                    )
                  else if (submitted)
                    AppCard(
                      color: scheme.secondaryContainer,
                      borderColor: scheme.secondaryContainer,
                      child: Row(
                        children: [
                          Icon(
                            Icons.check_circle_rounded,
                            color: scheme.onSecondaryContainer,
                          ),
                          const SizedBox(width: AppSpacing.sm),
                          Expanded(
                            child: Text(
                              'This inspection has been submitted.',
                              style: Theme.of(context).textTheme.bodyMedium
                                  ?.copyWith(
                                    color: scheme.onSecondaryContainer,
                                    fontWeight: FontWeight.w600,
                                  ),
                            ),
                          ),
                        ],
                      ),
                    )
                  else if (cancelled)
                    AppCard(
                      color: scheme.errorContainer,
                      borderColor: scheme.errorContainer,
                      child: Row(
                        children: [
                          Icon(
                            Icons.cancel_outlined,
                            color: scheme.onErrorContainer,
                          ),
                          const SizedBox(width: AppSpacing.sm),
                          Expanded(
                            child: Text(
                              'This assignment was cancelled.',
                              style: Theme.of(context).textTheme.bodyMedium
                                  ?.copyWith(color: scheme.onErrorContainer),
                            ),
                          ),
                        ],
                      ),
                    ),
                ],
              ),
            ),
          );
        },
      ),
    );
  }

  Future<void> _start(
    BuildContext context,
    InspectionAssignment assignment,
  ) async {
    setState(() => _starting = true);
    try {
      final repo = ref.read(assignmentsRepositoryProvider);
      final updated = await repo.start(widget.assignmentId);
      if (!context.mounted) return;
      ref.invalidate(assignmentDetailProvider(widget.assignmentId));
      ref.invalidate(assignmentsControllerProvider);
      ref.invalidate(pendingSyncCountProvider);
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Inspection started — ${updated.statusLabel}'),
          ),
        );
      }
    } on ApiException catch (e) {
      if (e.isNetwork) {
        await ref.read(syncServiceProvider).enqueueStart(widget.assignmentId);
        ref.invalidate(pendingSyncCountProvider);
        if (!context.mounted) return;
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('You are offline — start will sync when online.'),
          ),
        );
      } else {
        if (!context.mounted) return;
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(e.message)));
      }
    } finally {
      if (mounted) setState(() => _starting = false);
    }
  }

  Future<void> _submit(
    BuildContext context,
    InspectionAssignment assignment,
  ) async {
    final bool? confirmed = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Submit inspection?'),
        content: const Text(
          'This will mark the inspection as completed and notify the resident. '
          'Make sure the checklist and evidence are complete (Phase 2).',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(ctx).pop(false),
            child: const Text('Cancel'),
          ),
          FilledButton(
            onPressed: () => Navigator.of(ctx).pop(true),
            child: const Text('Submit'),
          ),
        ],
      ),
    );
    if (confirmed != true) return;
    setState(() => _submitting = true);
    try {
      final repo = ref.read(assignmentsRepositoryProvider);
      final notes = _notesCtrl.text.trim().isEmpty
          ? null
          : _notesCtrl.text.trim();
      final updated = await repo.submit(widget.assignmentId, notes: notes);
      if (!context.mounted) return;
      ref.invalidate(assignmentDetailProvider(widget.assignmentId));
      ref.invalidate(assignmentsControllerProvider);
      ref.invalidate(pendingSyncCountProvider);
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Inspection submitted — ${updated.statusLabel}'),
          ),
        );
      }
    } on ApiException catch (e) {
      if (e.isNetwork) {
        final String? notes = _notesCtrl.text.trim().isEmpty
            ? null
            : _notesCtrl.text.trim();
        await ref
            .read(syncServiceProvider)
            .enqueueSubmit(widget.assignmentId, notes: notes);
        ref.invalidate(pendingSyncCountProvider);
        if (!context.mounted) return;
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('You are offline — submit will sync when online.'),
          ),
        );
      } else {
        if (!context.mounted) return;
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(e.message)));
      }
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }
}

class _StatusBadge extends StatelessWidget {
  const _StatusBadge({required this.status});

  final String status;

  @override
  Widget build(BuildContext context) {
    final (AppBadgeVariant variant, IconData icon) = switch (status) {
      'in_progress' => (
        AppBadgeVariant.warning,
        Icons.play_circle_outline_rounded,
      ),
      'submitted' => (
        AppBadgeVariant.success,
        Icons.check_circle_outline_rounded,
      ),
      'downloaded' => (AppBadgeVariant.neutral, Icons.download_done_rounded),
      'cancelled' => (AppBadgeVariant.danger, Icons.cancel_outlined),
      _ => (AppBadgeVariant.outline, Icons.assignment_outlined),
    };
    return AppBadge(
      label: Formatters.humanize(status),
      variant: variant,
      icon: icon,
      compact: true,
    );
  }
}

class _MetaRow extends StatelessWidget {
  const _MetaRow({required this.icon, required this.label});

  final IconData icon;
  final String label;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Icon(icon, size: 16, color: scheme.onSurfaceVariant),
        const SizedBox(width: AppSpacing.xs),
        Expanded(
          child: Text(
            label,
            style: Theme.of(
              context,
            ).textTheme.bodySmall?.copyWith(color: scheme.onSurfaceVariant),
          ),
        ),
      ],
    );
  }
}

class _InfoRow extends StatelessWidget {
  const _InfoRow({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        SizedBox(
          width: 90,
          child: Text(
            label,
            style: Theme.of(context).textTheme.bodySmall?.copyWith(
              color: scheme.onSurfaceVariant,
              fontWeight: FontWeight.w600,
            ),
          ),
        ),
        Expanded(
          child: Text(value, style: Theme.of(context).textTheme.bodySmall),
        ),
      ],
    );
  }
}
