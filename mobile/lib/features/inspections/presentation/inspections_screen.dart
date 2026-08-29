import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/constants/app_constants.dart';
import '../../../core/providers/app_providers.dart';
import '../../../core/utils/formatters.dart';
import '../../../widgets/badges/app_badge.dart';
import '../../../widgets/cards/app_card.dart';
import '../../../widgets/feedback/app_empty_state.dart';
import '../../../widgets/feedback/app_error_state.dart';
import '../../../widgets/feedback/app_loading.dart';
import '../../../widgets/inputs/app_text_field.dart';
import '../../../widgets/layout/app_screen.dart';
import 'package:go_router/go_router.dart';
import '../../../router/app_routes.dart';
import '../models/assignment.dart';
import '../providers/assignments_providers.dart';

/// Assigned inspections list backed by `GET /v1/inspection-assignments` with an
/// offline cache fallback, search, and status filtering.
class InspectionsScreen extends ConsumerStatefulWidget {
  const InspectionsScreen({super.key});

  @override
  ConsumerState<InspectionsScreen> createState() => _InspectionsScreenState();
}

class _InspectionsScreenState extends ConsumerState<InspectionsScreen> {
  final TextEditingController _search = TextEditingController();
  String _query = '';
  String? _status;

  @override
  void dispose() {
    _search.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final AsyncValue<List<InspectionAssignment>> async = ref.watch(
      assignmentsControllerProvider,
    );
    final bool offline = ref.watch(onlineProvider).valueOrNull == false;
    final AsyncValue<int> pendingAsync = ref.watch(pendingSyncCountProvider);

    final List<InspectionAssignment> visible = async.valueOrNull == null
        ? const []
        : _applySearch(async.valueOrNull!, _query);

    return AppScreen(
      title: 'Inspections',
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          if (offline) const _OfflineBanner(),
          pendingAsync.when(
            data: (count) => count > 0
                ? _PendingSyncBanner(
                    count: count,
                    onTap: () => context.push('/sync-queue'),
                  )
                : const SizedBox.shrink(),
            loading: () => const SizedBox.shrink(),
            error: (_, _) => const SizedBox.shrink(),
          ),
          AppTextField(
            controller: _search,
            hint: 'Search business, applicant, or request #',
            icon: Icons.search_rounded,
            onChanged: (value) =>
                setState(() => _query = value.trim().toLowerCase()),
          ),
          const SizedBox(height: AppSpacing.md),
          _StatusFilterRow(
            selected: _status,
            onChanged: (String? status) {
              setState(() => _status = status);
              ref
                  .read(assignmentsControllerProvider.notifier)
                  .setStatus(status);
            },
          ),
          const SizedBox(height: AppSpacing.md),
          Expanded(
            child: async.when(
              loading: () => async.valueOrNull == null
                  ? const _SkeletonList()
                  : _list(visible, offline),
              error: (error, _) => async.valueOrNull == null
                  ? AppErrorState(
                      message: 'Could not load inspections.',
                      onRetry: () => ref
                          .read(assignmentsControllerProvider.notifier)
                          .refresh(),
                    )
                  : _list(visible, offline),
              data: (_) => _list(visible, offline),
            ),
          ),
        ],
      ),
    );
  }

  Widget _list(List<InspectionAssignment> items, bool offline) {
    final int total =
        ref.read(assignmentsControllerProvider).valueOrNull?.length ?? 0;

    return RefreshIndicator(
      onRefresh: () =>
          ref.read(assignmentsControllerProvider.notifier).refresh(),
      child: items.isEmpty
          ? ListView(
              physics: const AlwaysScrollableScrollPhysics(),
              children: [
                SizedBox(
                  height: MediaQuery.of(context).size.height * 0.45,
                  child: AppEmptyState(
                    icon: Icons.assignment_outlined,
                    title: _query.isEmpty && !offline
                        ? 'No inspections yet'
                        : 'No inspections match',
                    message: _query.isEmpty && !offline
                        ? 'Assigned inspections will be listed here so you can '
                              'work through them even without a connection.'
                        : 'Try a different search or status filter.',
                  ),
                ),
              ],
            )
          : ListView.builder(
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.only(bottom: AppSpacing.lg),
              itemCount: items.length + 1,
              itemBuilder: (context, index) {
                if (index == 0) {
                  return Padding(
                    padding: const EdgeInsets.only(bottom: AppSpacing.sm),
                    child: Text(
                      '${items.length} of $total inspections',
                      style: Theme.of(context).textTheme.bodySmall?.copyWith(
                        color: Theme.of(context).colorScheme.onSurfaceVariant,
                      ),
                    ),
                  );
                }
                return Padding(
                  padding: const EdgeInsets.only(bottom: AppSpacing.md),
                  child: _AssignmentCard(assignment: items[index - 1]),
                );
              },
            ),
    );
  }

  List<InspectionAssignment> _applySearch(
    List<InspectionAssignment> items,
    String query,
  ) {
    if (query.isEmpty) return items;
    return items.where((a) {
      final InspectionRequestSummary? r = a.inspectionRequest;
      return r?.displayName.toLowerCase().contains(query) == true ||
          r?.requestNumber.toLowerCase().contains(query) == true ||
          r?.addressLabel.toLowerCase().contains(query) == true;
    }).toList();
  }
}

/// Horizontal, single-select status filter using the app's pill styling.
class _StatusFilterRow extends StatelessWidget {
  const _StatusFilterRow({required this.selected, required this.onChanged});

  final String? selected;
  final ValueChanged<String?> onChanged;

  static const List<({String? value, String label})> _options = [
    (value: null, label: 'All'),
    (value: 'assigned', label: 'Assigned'),
    (value: 'in_progress', label: 'In progress'),
    (value: 'submitted', label: 'Submitted'),
  ];

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 36,
      child: ListView.separated(
        scrollDirection: Axis.horizontal,
        itemCount: _options.length,
        separatorBuilder: (_, _) => const SizedBox(width: AppSpacing.sm),
        itemBuilder: (context, index) {
          final option = _options[index];
          final bool active = selected == option.value;
          return _FilterPill(
            label: option.label,
            active: active,
            onTap: () => onChanged(option.value),
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

/// Subtle banner shown when connectivity is down and data may be from cache.
class _OfflineBanner extends StatelessWidget {
  const _OfflineBanner();

  @override
  Widget build(BuildContext context) {
    final ColorScheme scheme = Theme.of(context).colorScheme;
    return Container(
      margin: const EdgeInsets.only(bottom: AppSpacing.md),
      padding: const EdgeInsets.symmetric(
        horizontal: AppSpacing.md,
        vertical: AppSpacing.sm,
      ),
      decoration: BoxDecoration(
        color: scheme.primaryContainer,
        borderRadius: BorderRadius.circular(AppRadius.md),
      ),
      child: Row(
        children: [
          Icon(
            Icons.cloud_off_rounded,
            size: 16,
            color: scheme.onPrimaryContainer,
          ),
          const SizedBox(width: AppSpacing.sm),
          Expanded(
            child: Text(
              'You are offline — showing saved inspections.',
              style: Theme.of(context).textTheme.bodySmall?.copyWith(
                color: scheme.onPrimaryContainer,
                fontWeight: FontWeight.w600,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _PendingSyncBanner extends StatelessWidget {
  const _PendingSyncBanner({required this.count, this.onTap});

  final int count;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final ColorScheme scheme = Theme.of(context).colorScheme;
    final Widget content = Container(
      margin: const EdgeInsets.only(bottom: AppSpacing.md),
      padding: const EdgeInsets.symmetric(
        horizontal: AppSpacing.md,
        vertical: AppSpacing.sm,
      ),
      decoration: BoxDecoration(
        color: scheme.tertiaryContainer,
        borderRadius: BorderRadius.circular(AppRadius.md),
      ),
      child: Row(
        children: [
          Icon(Icons.sync_rounded, size: 16, color: scheme.onTertiaryContainer),
          const SizedBox(width: AppSpacing.sm),
          Expanded(
            child: Text(
              '$count pending sync — will upload when online. Tap to view.',
              style: Theme.of(context).textTheme.bodySmall?.copyWith(
                color: scheme.onTertiaryContainer,
                fontWeight: FontWeight.w600,
              ),
            ),
          ),
          if (onTap != null)
            Icon(
              Icons.chevron_right_rounded,
              size: 18,
              color: scheme.onTertiaryContainer,
            ),
        ],
      ),
    );

    if (onTap == null) return content;
    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(AppRadius.md),
        child: content,
      ),
    );
  }
}

class _AssignmentCard extends StatelessWidget {
  const _AssignmentCard({required this.assignment});

  final InspectionAssignment assignment;

  @override
  Widget build(BuildContext context) {
    final ColorScheme scheme = Theme.of(context).colorScheme;
    final InspectionRequestSummary? request = assignment.inspectionRequest;
    final AppBadge statusBadge = _statusBadge(context, assignment.status);

    return AppCard(
      onTap: () =>
          context.push(AppRoutes.inspectionDetailPath('${assignment.id}')),
      padding: const EdgeInsets.all(AppSpacing.lg),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                width: 40,
                height: 40,
                decoration: BoxDecoration(
                  color: scheme.primaryContainer,
                  borderRadius: BorderRadius.circular(AppRadius.md),
                ),
                child: Icon(
                  Icons.storefront_rounded,
                  size: 20,
                  color: scheme.onPrimaryContainer,
                ),
              ),
              const SizedBox(width: AppSpacing.md),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      request?.displayName ?? 'Unnamed inspection',
                      style: Theme.of(context).textTheme.titleMedium?.copyWith(
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      request?.requestNumber ?? '#${assignment.id}',
                      style: Theme.of(context).textTheme.bodySmall?.copyWith(
                        color: scheme.onSurfaceVariant,
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(width: AppSpacing.sm),
              statusBadge,
            ],
          ),
          const SizedBox(height: AppSpacing.md),
          Wrap(
            spacing: AppSpacing.sm,
            runSpacing: AppSpacing.sm,
            children: [
              if (request?.inspectionCategory != null)
                AppBadge(
                  label: request!.inspectionCategory!.name,
                  variant: AppBadgeVariant.primary,
                  icon: Icons.fact_check_outlined,
                  compact: true,
                ),
              if (request?.applicationType != null)
                AppBadge(
                  label: request!.applicationType!.name,
                  variant: AppBadgeVariant.outline,
                  compact: true,
                ),
            ],
          ),
          const SizedBox(height: AppSpacing.md),
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Icon(
                Icons.location_on_outlined,
                size: 16,
                color: scheme.onSurfaceVariant,
              ),
              const SizedBox(width: AppSpacing.xs),
              Expanded(
                child: Text(
                  request?.addressLabel ?? 'Address not on file',
                  style: Theme.of(context).textTheme.bodyMedium,
                ),
              ),
            ],
          ),
          const SizedBox(height: AppSpacing.sm),
          Row(
            children: [
              Icon(
                Icons.event_outlined,
                size: 16,
                color: scheme.onSurfaceVariant,
              ),
              const SizedBox(width: AppSpacing.xs),
              Expanded(
                child: Text(
                  assignment.scheduleLabel,
                  style: Theme.of(context).textTheme.bodySmall?.copyWith(
                    color: scheme.onSurfaceVariant,
                  ),
                ),
              ),
              if (assignment.notes?.isNotEmpty == true)
                Icon(
                  Icons.sticky_note_2_outlined,
                  size: 16,
                  color: scheme.onSurfaceVariant,
                ),
            ],
          ),
        ],
      ),
    );
  }

  AppBadge _statusBadge(BuildContext context, String status) {
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

class _SkeletonList extends StatelessWidget {
  const _SkeletonList();

  @override
  Widget build(BuildContext context) {
    return ListView.builder(
      physics: const NeverScrollableScrollPhysics(),
      itemCount: 4,
      itemBuilder: (context, index) => Padding(
        padding: const EdgeInsets.only(bottom: AppSpacing.md),
        child: AppCard(
          padding: const EdgeInsets.all(AppSpacing.lg),
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
                        AppSkeletonBox(width: 120, height: 10),
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
