import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/constants/app_constants.dart';
import '../../../core/errors/api_exception.dart';
import '../../../core/utils/formatters.dart';
import '../../../router/app_routes.dart';
import '../../../widgets/badges/app_badge.dart';
import '../../../widgets/cards/app_card.dart';
import '../../../widgets/feedback/app_empty_state.dart';
import '../../../widgets/feedback/app_error_state.dart';
import '../../../widgets/feedback/app_loading.dart';
import '../../../widgets/inputs/app_text_field.dart';
import '../models/violation.dart';
import '../providers/violations_providers.dart';

/// Violations list for inspector — `GET /v1/violations` with
/// status/severity filters, search, and pagination.
///
/// Tapping a card opens the detail; FAB opens the violation form.
/// Evidence upload is handled in the form/detail flow (Phase 3).
class ViolationsScreen extends ConsumerStatefulWidget {
  const ViolationsScreen({super.key});

  @override
  ConsumerState<ViolationsScreen> createState() => _ViolationsScreenState();
}

class _ViolationsScreenState extends ConsumerState<ViolationsScreen> {
  final TextEditingController _search = TextEditingController();
  String _query = '';

  @override
  void dispose() {
    _search.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final ColorScheme scheme = Theme.of(context).colorScheme;
    final ViolationsParams params = ref.watch(violationsParamsProvider);
    final AsyncValue<ViolationsPage> pageAsync = ref.watch(
      violationsPageProvider,
    );

    return Scaffold(
      backgroundColor: scheme.surface,
      appBar: AppBar(title: const Text('Violations')),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () => context.push('${AppRoutes.violations}/new'),
        icon: const Icon(Icons.add_rounded),
        label: const Text('Record violation'),
      ),
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(
            AppSpacing.lg,
            AppSpacing.sm,
            AppSpacing.lg,
            AppSpacing.xl,
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              AppTextField(
                controller: _search,
                hint: 'Search violation or establishment',
                icon: Icons.search_rounded,
                onChanged: (v) {
                  final String q = v.trim().toLowerCase();
                  if (_query == q) return;
                  setState(() => _query = q);
                  ref.read(violationsParamsProvider.notifier).state = params
                      .copyWith(search: q.isEmpty ? null : q, page: 1);
                },
              ),
              const SizedBox(height: AppSpacing.md),
              _FilterRow(
                label: 'Status',
                selected: params.status,
                options: const [
                  (value: null, label: 'All'),
                  (value: 'open', label: 'Open'),
                  (value: 'under_review', label: 'Under review'),
                  (value: 'resolved', label: 'Resolved'),
                ],
                onChanged: (v) =>
                    ref.read(violationsParamsProvider.notifier).state = params
                        .copyWith(status: v, page: 1),
              ),
              const SizedBox(height: AppSpacing.sm),
              _FilterRow(
                label: 'Severity',
                selected: params.severity,
                options: const [
                  (value: null, label: 'All'),
                  (value: 'minor', label: 'Minor'),
                  (value: 'moderate', label: 'Moderate'),
                  (value: 'major', label: 'Major'),
                ],
                onChanged: (v) =>
                    ref.read(violationsParamsProvider.notifier).state = params
                        .copyWith(severity: v, page: 1),
              ),
              const SizedBox(height: AppSpacing.md),
              Expanded(
                child: pageAsync.when(
                  loading: () => const _SkeletonList(),
                  error: (error, _) => AppErrorState(
                    message: error is ApiException
                        ? error.message
                        : 'Could not load violations.',
                    onRetry: () => ref.invalidate(violationsPageProvider),
                  ),
                  data: (page) {
                    final List<Violation> items = page.violations;
                    if (items.isEmpty) {
                      return RefreshIndicator(
                        onRefresh: () async =>
                            ref.invalidate(violationsPageProvider),
                        child: ListView(
                          physics: const AlwaysScrollableScrollPhysics(),
                          children: [
                            SizedBox(
                              height: MediaQuery.of(context).size.height * 0.45,
                              child: const AppEmptyState(
                                icon: Icons.gavel_outlined,
                                title: 'No violations recorded',
                                message:
                                    'Violations found during an inspection will be captured '
                                    'here with severity and photo evidence.',
                              ),
                            ),
                          ],
                        ),
                      );
                    }
                    final bool hasMore = page.currentPage < page.lastPage;
                    return RefreshIndicator(
                      onRefresh: () async =>
                          ref.invalidate(violationsPageProvider),
                      child: ListView.builder(
                        physics: const AlwaysScrollableScrollPhysics(),
                        padding: const EdgeInsets.only(bottom: 80),
                        itemCount: items.length + 1 + (hasMore ? 1 : 0),
                        itemBuilder: (context, index) {
                          if (index == 0) {
                            return Padding(
                              padding: const EdgeInsets.only(
                                bottom: AppSpacing.sm,
                              ),
                              child: Text(
                                '${items.length} of ${page.total} violations',
                                style: Theme.of(context).textTheme.bodySmall
                                    ?.copyWith(color: scheme.onSurfaceVariant),
                              ),
                            );
                          }
                          if (hasMore && index == items.length + 1) {
                            return Padding(
                              padding: const EdgeInsets.only(
                                top: AppSpacing.md,
                              ),
                              child: OutlinedButton(
                                onPressed: () {
                                  ref
                                      .read(violationsParamsProvider.notifier)
                                      .state = params.copyWith(
                                    page: params.page + 1,
                                  );
                                },
                                child: const Text('Load more'),
                              ),
                            );
                          }
                          final Violation v = items[index - 1];
                          return Padding(
                            padding: const EdgeInsets.only(
                              bottom: AppSpacing.md,
                            ),
                            child: _ViolationCard(violation: v),
                          );
                        },
                      ),
                    );
                  },
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

// We wrap Violations tab with a Scaffold that provides FAB.
// To keep AppScreen intact, we add FAB via an extension: ViolationsScreen is used
// inside StatefulShell, so we inject FAB by reading context and pushing via GoRouter.
class _FilterRow extends StatelessWidget {
  const _FilterRow({
    required this.label,
    required this.selected,
    required this.options,
    required this.onChanged,
  });

  final String label;
  final String? selected;
  final List<({String? value, String label})> options;
  final ValueChanged<String?> onChanged;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 36,
      child: ListView.separated(
        scrollDirection: Axis.horizontal,
        itemCount: options.length,
        separatorBuilder: (_, _) => const SizedBox(width: AppSpacing.sm),
        itemBuilder: (context, index) {
          final o = options[index];
          final bool active = selected == o.value;
          return _FilterPill(
            label: o.label,
            active: active,
            onTap: () => onChanged(o.value),
          );
        },
      ),
    );
  }
}

class _FilterPill extends StatelessWidget {
  const _FilterPill({
    required this.label,
    required this.active,
    required this.onTap,
  });

  final String label;
  final bool active;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final ColorScheme scheme = Theme.of(context).colorScheme;
    return Material(
      color: active ? scheme.primary : scheme.surfaceContainerHigh,
      borderRadius: BorderRadius.circular(AppRadius.pill),
      child: InkWell(
        borderRadius: BorderRadius.circular(AppRadius.pill),
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: AppSpacing.lg),
          child: Center(
            child: Text(
              label,
              style: Theme.of(context).textTheme.labelMedium?.copyWith(
                color: active ? scheme.onPrimary : scheme.onSurfaceVariant,
                fontWeight: FontWeight.w600,
              ),
            ),
          ),
        ),
      ),
    );
  }
}

class _ViolationCard extends StatelessWidget {
  const _ViolationCard({required this.violation});

  final Violation violation;

  @override
  Widget build(BuildContext context) {
    final ColorScheme scheme = Theme.of(context).colorScheme;
    final AppBadge severityBadge = _severityBadge(violation.severity);
    final AppBadge statusBadge = _statusBadge(violation.status);

    return AppCard(
      onTap: () => context.push('${AppRoutes.violations}/${violation.id}'),
      padding: const EdgeInsets.all(AppSpacing.lg),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: 40,
                height: 40,
                decoration: BoxDecoration(
                  color: scheme.errorContainer,
                  borderRadius: BorderRadius.circular(AppRadius.md),
                ),
                child: Icon(
                  Icons.gavel_rounded,
                  size: 20,
                  color: scheme.onErrorContainer,
                ),
              ),
              const SizedBox(width: AppSpacing.md),
              Expanded(
                child: Text(
                  violation.title,
                  style: Theme.of(context).textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.w700,
                  ),
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                ),
              ),
              const SizedBox(width: AppSpacing.sm),
              severityBadge,
            ],
          ),
          const SizedBox(height: AppSpacing.sm),
          Text(
            violation.description,
            style: Theme.of(
              context,
            ).textTheme.bodySmall?.copyWith(color: scheme.onSurfaceVariant),
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
          ),
          const SizedBox(height: AppSpacing.md),
          Wrap(
            spacing: AppSpacing.sm,
            runSpacing: AppSpacing.sm,
            children: [statusBadge],
          ),
          const SizedBox(height: AppSpacing.sm),
          Row(
            children: [
              Icon(
                Icons.business_outlined,
                size: 14,
                color: scheme.onSurfaceVariant,
              ),
              const SizedBox(width: AppSpacing.xs),
              Expanded(
                child: Text(
                  violation.establishmentName ??
                      'Establishment #${violation.establishmentId ?? violation.inspectionId}',
                  style: Theme.of(context).textTheme.bodySmall?.copyWith(
                    color: scheme.onSurfaceVariant,
                  ),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
              ),
              if (violation.correctionDeadline != null) ...[
                const SizedBox(width: AppSpacing.sm),
                Icon(
                  Icons.event_outlined,
                  size: 14,
                  color: scheme.onSurfaceVariant,
                ),
                const SizedBox(width: 4),
                Text(
                  Formatters.date(violation.correctionDeadline),
                  style: Theme.of(context).textTheme.labelSmall?.copyWith(
                    color: scheme.onSurfaceVariant,
                  ),
                ),
              ],
            ],
          ),
          if (violation.evidenceCount > 0) ...[
            const SizedBox(height: AppSpacing.xs),
            Row(
              children: [
                Icon(
                  Icons.photo_library_outlined,
                  size: 14,
                  color: scheme.onSurfaceVariant,
                ),
                const SizedBox(width: 4),
                Text(
                  '${violation.evidenceCount} evidence file(s)',
                  style: Theme.of(context).textTheme.labelSmall?.copyWith(
                    color: scheme.onSurfaceVariant,
                  ),
                ),
              ],
            ),
          ],
        ],
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

class _SkeletonList extends StatelessWidget {
  const _SkeletonList();

  @override
  Widget build(BuildContext context) {
    return ListView.builder(
      physics: const NeverScrollableScrollPhysics(),
      itemCount: 4,
      itemBuilder: (context, i) => Padding(
        padding: const EdgeInsets.only(bottom: AppSpacing.md),
        child: AppCard(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  const AppSkeletonBox(
                    width: 40,
                    height: 40,
                    radius: AppRadius.md,
                  ),
                  const SizedBox(width: AppSpacing.md),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const AppSkeletonBox(height: 14),
                        const SizedBox(height: AppSpacing.sm),
                        AppSkeletonBox(width: 160, height: 10),
                      ],
                    ),
                  ),
                ],
              ),
              const SizedBox(height: AppSpacing.lg),
              const AppSkeletonBox(height: 12),
              const SizedBox(height: AppSpacing.sm),
              const AppSkeletonBox(width: 200, height: 10),
            ],
          ),
        ),
      ),
    );
  }
}
