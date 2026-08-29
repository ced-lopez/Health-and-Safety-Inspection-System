import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/constants/app_constants.dart';
import '../../../core/errors/api_exception.dart';
import '../../../core/utils/formatters.dart';
import '../../../widgets/badges/app_badge.dart';
import '../../../widgets/cards/app_card.dart';
import '../../../widgets/feedback/app_error_state.dart';
import '../models/violation.dart';
import '../providers/violations_providers.dart';

/// Detail for a single violation — `GET /v1/violations/{id}`
/// with evidence and status/severity badges.
class ViolationDetailScreen extends ConsumerWidget {
  const ViolationDetailScreen({super.key, required this.violationId});

  final String violationId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final ColorScheme scheme = Theme.of(context).colorScheme;
    final AsyncValue<Violation> detail = ref.watch(
      violationDetailProvider(violationId),
    );

    return Scaffold(
      backgroundColor: scheme.surface,
      appBar: AppBar(title: const Text('Violation detail'), centerTitle: false),
      body: detail.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (error, _) => AppErrorState(
          message: error is ApiException
              ? error.message
              : 'Could not load violation.',
          onRetry: () => ref.invalidate(violationDetailProvider(violationId)),
        ),
        data: (v) => RefreshIndicator(
          onRefresh: () async =>
              ref.invalidate(violationDetailProvider(violationId)),
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
                            width: 44,
                            height: 44,
                            decoration: BoxDecoration(
                              color: scheme.errorContainer,
                              borderRadius: BorderRadius.circular(AppRadius.md),
                            ),
                            child: Icon(
                              Icons.gavel_rounded,
                              color: scheme.onErrorContainer,
                            ),
                          ),
                          const SizedBox(width: AppSpacing.md),
                          Expanded(
                            child: Text(
                              v.title,
                              style: Theme.of(context).textTheme.titleMedium
                                  ?.copyWith(fontWeight: FontWeight.w700),
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: AppSpacing.md),
                      Text(
                        v.description,
                        style: Theme.of(context).textTheme.bodyMedium,
                      ),
                      const SizedBox(height: AppSpacing.md),
                      Wrap(
                        spacing: AppSpacing.sm,
                        runSpacing: AppSpacing.sm,
                        children: [
                          _severityBadge(v.severity),
                          _statusBadge(v.status),
                        ],
                      ),
                      const SizedBox(height: AppSpacing.md),
                      _InfoRow(
                        icon: Icons.business_outlined,
                        label:
                            v.establishmentName ??
                            'Establishment #${v.establishmentId ?? v.inspectionId}',
                      ),
                      if (v.correctionDeadline != null) ...[
                        const SizedBox(height: AppSpacing.xs),
                        _InfoRow(
                          icon: Icons.event_outlined,
                          label:
                              'Deadline: ${Formatters.date(v.correctionDeadline)}',
                        ),
                      ],
                      if (v.createdAt != null) ...[
                        const SizedBox(height: AppSpacing.xs),
                        _InfoRow(
                          icon: Icons.schedule_rounded,
                          label: 'Filed: ${Formatters.dateTime(v.createdAt)}',
                        ),
                      ],
                    ],
                  ),
                ),
                const SizedBox(height: AppSpacing.lg),
                Text(
                  'Evidence',
                  style: Theme.of(
                    context,
                  ).textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w700),
                ),
                const SizedBox(height: AppSpacing.md),
                if (v.evidence.isEmpty)
                  AppCard(
                    child: Text(
                      'No evidence files yet. Add photos from the form.',
                      style: Theme.of(context).textTheme.bodySmall?.copyWith(
                        color: scheme.onSurfaceVariant,
                        fontStyle: FontStyle.italic,
                      ),
                    ),
                  )
                else
                  ...v.evidence.map(
                    (e) => Padding(
                      padding: const EdgeInsets.only(bottom: AppSpacing.sm),
                      child: AppCard(
                        child: Row(
                          children: [
                            Icon(
                              Icons.image_outlined,
                              color: scheme.onSurfaceVariant,
                            ),
                            const SizedBox(width: AppSpacing.sm),
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text(
                                    e.fileName ?? e.filePath.split('/').last,
                                    style: Theme.of(context)
                                        .textTheme
                                        .bodyMedium
                                        ?.copyWith(fontWeight: FontWeight.w600),
                                    maxLines: 1,
                                    overflow: TextOverflow.ellipsis,
                                  ),
                                  if (e.evidenceType != null)
                                    Text(
                                      e.evidenceType!,
                                      style: Theme.of(context)
                                          .textTheme
                                          .labelSmall
                                          ?.copyWith(
                                            color: scheme.onSurfaceVariant,
                                          ),
                                    ),
                                ],
                              ),
                            ),
                            if (e.createdAt != null)
                              Text(
                                Formatters.date(e.createdAt),
                                style: Theme.of(context).textTheme.labelSmall
                                    ?.copyWith(color: scheme.onSurfaceVariant),
                              ),
                          ],
                        ),
                      ),
                    ),
                  ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  AppBadge _severityBadge(String s) => switch (s) {
    'major' => AppBadge(
      label: Formatters.humanize(s),
      variant: AppBadgeVariant.danger,
      icon: Icons.priority_high_rounded,
      compact: true,
    ),
    'moderate' => AppBadge(
      label: Formatters.humanize(s),
      variant: AppBadgeVariant.warning,
      icon: Icons.warning_amber_rounded,
      compact: true,
    ),
    _ => AppBadge(
      label: Formatters.humanize(s),
      variant: AppBadgeVariant.neutral,
      icon: Icons.info_outline_rounded,
      compact: true,
    ),
  };

  AppBadge _statusBadge(String s) => switch (s) {
    'resolved' => AppBadge(
      label: Formatters.humanize(s),
      variant: AppBadgeVariant.success,
      icon: Icons.check_circle_outline_rounded,
      compact: true,
    ),
    'under_review' => AppBadge(
      label: Formatters.humanize(s),
      variant: AppBadgeVariant.warning,
      icon: Icons.rate_review_outlined,
      compact: true,
    ),
    _ => AppBadge(
      label: Formatters.humanize(s),
      variant: AppBadgeVariant.outline,
      icon: Icons.gavel_outlined,
      compact: true,
    ),
  };
}

class _InfoRow extends StatelessWidget {
  const _InfoRow({required this.icon, required this.label});

  final IconData icon;
  final String label;

  @override
  Widget build(BuildContext context) {
    final ColorScheme scheme = Theme.of(context).colorScheme;
    return Row(
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
