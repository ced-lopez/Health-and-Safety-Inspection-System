import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/constants/app_constants.dart';
import '../../../core/errors/api_exception.dart';
import '../../../core/providers/app_providers.dart';
import '../../../core/utils/formatters.dart';
import '../../../router/app_routes.dart';
import '../../../widgets/badges/app_badge.dart';
import '../../../widgets/cards/app_card.dart';
import '../../../widgets/feedback/app_empty_state.dart';
import '../../../widgets/feedback/app_error_state.dart';
import '../../../widgets/feedback/app_loading.dart';
import '../../authentication/models/auth_state.dart';
import '../../authentication/providers/auth_providers.dart';
import '../models/dashboard.dart';
import '../providers/dashboard_providers.dart';

/// Inspector dashboard — `GET /v1/dashboard` inspector branch.
///
/// Shows stats (`assigned/in_progress/completed/follow_ups/pending_sync`),
/// assigned inspections (tap → detail), inspection history, follow-ups, and
/// sync footer (server `pending_count/unsynced` + local `pendingSyncCount`).
class DashboardScreen extends ConsumerWidget {
  const DashboardScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final ColorScheme scheme = Theme.of(context).colorScheme;
    final AsyncValue<AuthState> auth = ref.watch(authControllerProvider);
    final String name = switch (auth.valueOrNull) {
      AuthAuthenticated(:final user) => user.name,
      _ => 'Inspector',
    };
    final String firstName = name.split(' ').first;
    final AsyncValue<InspectorDashboard> dashAsync = ref.watch(
      inspectorDashboardProvider,
    );
    final AsyncValue<int> localPendingAsync = ref.watch(
      pendingSyncCountProvider,
    );

    return Scaffold(
      backgroundColor: scheme.surface,
      body: SafeArea(
        child: RefreshIndicator(
          onRefresh: () async {
            ref.invalidate(inspectorDashboardProvider);
            ref.invalidate(pendingSyncCountProvider);
            await Future<void>.delayed(const Duration(milliseconds: 400));
          },
          child: SingleChildScrollView(
            physics: const AlwaysScrollableScrollPhysics(),
            padding: const EdgeInsets.fromLTRB(
              AppSpacing.lg,
              AppSpacing.lg,
              AppSpacing.lg,
              AppSpacing.xxl,
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Good day, $firstName 👋',
                  style: Theme.of(context).textTheme.headlineMedium?.copyWith(
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: AppSpacing.xs),
                Text(
                  'Here are your inspection assignments.',
                  style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                    color: scheme.onSurfaceVariant,
                  ),
                ),
                const SizedBox(height: AppSpacing.xl),
                dashAsync.when(
                  loading: () => const _DashboardSkeleton(),
                  error: (error, _) => AppErrorState(
                    message: error is ApiException
                        ? error.message
                        : 'Could not load dashboard. Pull to retry.',
                    onRetry: () => ref.invalidate(inspectorDashboardProvider),
                  ),
                  data: (dash) {
                    final int localPending = localPendingAsync.valueOrNull ?? 0;
                    return Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        _StatsGrid(
                          stats: dash.stats,
                          localPending: localPending,
                        ),
                        const SizedBox(height: AppSpacing.lg),
                        _SectionTitle(
                          title: 'Assigned to you',
                          actionLabel:
                              '${dash.assignedInspections.length} active',
                        ),
                        const SizedBox(height: AppSpacing.md),
                        if (dash.assignedInspections.isEmpty)
                          AppCard(
                            child: const AppEmptyState(
                              icon: Icons.assignment_turned_in_outlined,
                              title: 'No active assignments',
                              message:
                                  'Assigned inspections will appear here. Pull to refresh.',
                            ),
                          )
                        else
                          SizedBox(
                            height: 148,
                            child: ListView.separated(
                              scrollDirection: Axis.horizontal,
                              itemCount: dash.assignedInspections.length,
                              separatorBuilder: (_, _) =>
                                  const SizedBox(width: AppSpacing.md),
                              itemBuilder: (context, index) {
                                final DashboardAssignmentCard a =
                                    dash.assignedInspections[index];
                                return SizedBox(
                                  width: 280,
                                  child: _AssignedCard(card: a),
                                );
                              },
                            ),
                          ),
                        const SizedBox(height: AppSpacing.lg),
                        _SectionTitle(
                          title: 'Inspection history',
                          actionLabel:
                              '${dash.inspectionHistory.length} this year',
                        ),
                        const SizedBox(height: AppSpacing.md),
                        if (dash.inspectionHistory.isEmpty)
                          AppCard(
                            child: Text(
                              'No inspections completed yet.',
                              style: Theme.of(context).textTheme.bodySmall
                                  ?.copyWith(
                                    color: scheme.onSurfaceVariant,
                                    fontStyle: FontStyle.italic,
                                  ),
                            ),
                          )
                        else
                          ...dash.inspectionHistory.map(
                            (h) => Padding(
                              padding: const EdgeInsets.only(
                                bottom: AppSpacing.sm,
                              ),
                              child: _HistoryTile(item: h),
                            ),
                          ),
                        const SizedBox(height: AppSpacing.lg),
                        _SectionTitle(
                          title: 'Follow-ups',
                          actionLabel: '${dash.followUps.length}',
                        ),
                        const SizedBox(height: AppSpacing.md),
                        if (dash.followUps.isEmpty)
                          AppCard(
                            child: Text(
                              'No follow-ups requiring attention.',
                              style: Theme.of(context).textTheme.bodySmall
                                  ?.copyWith(
                                    color: scheme.onSurfaceVariant,
                                    fontStyle: FontStyle.italic,
                                  ),
                            ),
                          )
                        else
                          ...dash.followUps.map(
                            (f) => Padding(
                              padding: const EdgeInsets.only(
                                bottom: AppSpacing.sm,
                              ),
                              child: _FollowUpTile(item: f),
                            ),
                          ),
                        const SizedBox(height: AppSpacing.lg),
                        _SyncFooter(
                          sync: dash.sync,
                          localPending: localPending,
                          onTap: () => context.push('/sync-queue'),
                        ),
                      ],
                    );
                  },
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _StatsGrid extends StatelessWidget {
  const _StatsGrid({required this.stats, required this.localPending});

  final DashboardStats stats;
  final int localPending;

  @override
  Widget build(BuildContext context) {
    final List<
      ({String label, int value, IconData icon, Color color, String? tooltip})
    >
    items = [
      (
        label: 'Assigned',
        value: stats.assigned,
        icon: Icons.assignment_outlined,
        color: Theme.of(context).colorScheme.primary,
        tooltip: null,
      ),
      (
        label: 'In progress',
        value: stats.inProgress,
        icon: Icons.play_circle_outline_rounded,
        color: Theme.of(context).colorScheme.tertiary,
        tooltip: null,
      ),
      (
        label: 'Completed',
        value: stats.completed,
        icon: Icons.check_circle_outline_rounded,
        color: Colors.green,
        tooltip: null,
      ),
      (
        label: 'Follow-ups',
        value: stats.followUps,
        icon: Icons.notification_important_outlined,
        color: Colors.orange,
        tooltip: null,
      ),
      (
        label: 'Not yet uploaded\n(this device)',
        value: localPending,
        icon: Icons.sync_rounded,
        color: Theme.of(context).colorScheme.onSurfaceVariant,
        tooltip: 'Items queued on this device awaiting upload',
      ),
    ];

    return AppCard(
      padding: const EdgeInsets.all(AppSpacing.md),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'Overview',
            style: Theme.of(
              context,
            ).textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w700),
          ),
          const SizedBox(height: AppSpacing.md),
          Wrap(
            spacing: AppSpacing.sm,
            runSpacing: AppSpacing.sm,
            children: items
                .map(
                  (e) => SizedBox(
                    width: 96,
                    child: Tooltip(
                      message: e.tooltip ?? '',
                      child: _StatTile(
                        label: e.label,
                        value: e.value,
                        icon: e.icon,
                        color: e.color,
                      ),
                    ),
                  ),
                )
                .toList(),
          ),
          // Always show both counts with clear labels (P1-11) when they differ, and a help line.
          if (localPending != stats.pendingSync) ...[
            const SizedBox(height: AppSpacing.sm),
            Text(
              'Not yet uploaded (this device): $localPending · Awaiting server processing: ${stats.pendingSync}',
              style: Theme.of(context).textTheme.labelSmall?.copyWith(
                color: Theme.of(context).colorScheme.onSurfaceVariant,
              ),
            ),
          ],
        ],
      ),
    );
  }
}

class _StatTile extends StatelessWidget {
  const _StatTile({
    required this.label,
    required this.value,
    required this.icon,
    required this.color,
  });

  final String label;
  final int value;
  final IconData icon;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(AppSpacing.md),
      decoration: BoxDecoration(
        color: Theme.of(context).colorScheme.surfaceContainerLow,
        borderRadius: BorderRadius.circular(AppRadius.md),
        border: Border.all(color: Theme.of(context).colorScheme.outlineVariant),
      ),
      child: Column(
        children: [
          Icon(icon, size: 20, color: color),
          const SizedBox(height: AppSpacing.xs),
          Text(
            '$value',
            style: Theme.of(
              context,
            ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w800),
          ),
          Text(
            label,
            style: Theme.of(context).textTheme.labelSmall?.copyWith(
              color: Theme.of(context).colorScheme.onSurfaceVariant,
            ),
            textAlign: TextAlign.center,
          ),
        ],
      ),
    );
  }
}

class _SectionTitle extends StatelessWidget {
  const _SectionTitle({required this.title, this.actionLabel});

  final String title;
  final String? actionLabel;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Text(
          title,
          style: Theme.of(
            context,
          ).textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w700),
        ),
        const Spacer(),
        if (actionLabel != null)
          Text(
            actionLabel!,
            style: Theme.of(context).textTheme.labelSmall?.copyWith(
              color: Theme.of(context).colorScheme.onSurfaceVariant,
            ),
          ),
      ],
    );
  }
}

class _AssignedCard extends StatelessWidget {
  const _AssignedCard({required this.card});

  final DashboardAssignmentCard card;

  @override
  Widget build(BuildContext context) {
    final ColorScheme scheme = Theme.of(context).colorScheme;
    return AppCard(
      onTap: () => context.push(AppRoutes.inspectionDetailPath('${card.id}')),
      padding: const EdgeInsets.all(AppSpacing.md),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: 32,
                height: 32,
                decoration: BoxDecoration(
                  color: scheme.primaryContainer,
                  borderRadius: BorderRadius.circular(AppRadius.sm),
                ),
                child: Icon(
                  Icons.storefront_rounded,
                  size: 16,
                  color: scheme.onPrimaryContainer,
                ),
              ),
              const SizedBox(width: AppSpacing.sm),
              Expanded(
                child: Text(
                  card.displayName,
                  style: Theme.of(
                    context,
                  ).textTheme.bodyMedium?.copyWith(fontWeight: FontWeight.w700),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
              ),
              AppBadge(
                label: Formatters.humanize(card.status),
                variant: card.status == 'in_progress'
                    ? AppBadgeVariant.warning
                    : AppBadgeVariant.outline,
                compact: true,
              ),
            ],
          ),
          const SizedBox(height: AppSpacing.sm),
          Text(
            card.requestNumber ?? '#${card.id}',
            style: Theme.of(
              context,
            ).textTheme.labelSmall?.copyWith(color: scheme.onSurfaceVariant),
          ),
          if (card.category != null)
            Padding(
              padding: const EdgeInsets.only(top: 2),
              child: Text(
                card.category!,
                style: Theme.of(context).textTheme.labelSmall?.copyWith(
                  color: scheme.onSurfaceVariant,
                ),
              ),
            ),
          const Spacer(),
          Row(
            children: [
              Icon(
                Icons.event_outlined,
                size: 14,
                color: scheme.onSurfaceVariant,
              ),
              const SizedBox(width: 4),
              Expanded(
                child: Text(
                  card.scheduledAt ?? card.assignedAt ?? 'No schedule',
                  style: Theme.of(context).textTheme.labelSmall?.copyWith(
                    color: scheme.onSurfaceVariant,
                  ),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _HistoryTile extends StatelessWidget {
  const _HistoryTile({required this.item});

  final InspectionHistoryItem item;

  @override
  Widget build(BuildContext context) {
    final ColorScheme scheme = Theme.of(context).colorScheme;
    return AppCard(
      padding: const EdgeInsets.all(AppSpacing.md),
      child: Row(
        children: [
          Container(
            width: 36,
            height: 36,
            decoration: BoxDecoration(
              color: scheme.secondaryContainer,
              borderRadius: BorderRadius.circular(AppRadius.sm),
            ),
            child: Icon(
              Icons.history_rounded,
              size: 18,
              color: scheme.onSecondaryContainer,
            ),
          ),
          const SizedBox(width: AppSpacing.md),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  item.establishmentName,
                  style: Theme.of(
                    context,
                  ).textTheme.bodyMedium?.copyWith(fontWeight: FontWeight.w600),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
                if (item.businessType != null)
                  Text(
                    item.businessType!,
                    style: Theme.of(context).textTheme.labelSmall?.copyWith(
                      color: scheme.onSurfaceVariant,
                    ),
                  ),
                Text(
                  item.date,
                  style: Theme.of(context).textTheme.labelSmall?.copyWith(
                    color: scheme.onSurfaceVariant,
                  ),
                ),
              ],
            ),
          ),
          AppBadge(
            label: Formatters.humanize(item.status),
            variant: item.status.toLowerCase() == 'completed'
                ? AppBadgeVariant.success
                : AppBadgeVariant.neutral,
            compact: true,
          ),
        ],
      ),
    );
  }
}

class _FollowUpTile extends StatelessWidget {
  const _FollowUpTile({required this.item});

  final FollowUpItem item;

  @override
  Widget build(BuildContext context) {
    final ColorScheme scheme = Theme.of(context).colorScheme;
    return AppCard(
      onTap: item.assignmentId != null
          ? () => context.push(
              AppRoutes.inspectionDetailPath('${item.assignmentId}'),
            )
          : null,
      padding: const EdgeInsets.all(AppSpacing.md),
      child: Row(
        children: [
          Container(
            width: 36,
            height: 36,
            decoration: BoxDecoration(
              color: Colors.orange.withValues(alpha: 0.15),
              borderRadius: BorderRadius.circular(AppRadius.sm),
            ),
            child: const Icon(
              Icons.warning_amber_rounded,
              size: 18,
              color: Colors.orange,
            ),
          ),
          const SizedBox(width: AppSpacing.md),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  item.businessName,
                  style: Theme.of(
                    context,
                  ).textTheme.bodyMedium?.copyWith(fontWeight: FontWeight.w600),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
                Text(
                  '${item.requestNumber} · ${item.category}',
                  style: Theme.of(context).textTheme.labelSmall?.copyWith(
                    color: scheme.onSurfaceVariant,
                  ),
                ),
                Text(
                  item.updatedAt,
                  style: Theme.of(context).textTheme.labelSmall?.copyWith(
                    color: scheme.onSurfaceVariant,
                  ),
                ),
              ],
            ),
          ),
          AppBadge(
            label: Formatters.humanize(item.status),
            variant: AppBadgeVariant.warning,
            compact: true,
          ),
          if (item.assignmentId != null) ...[
            const SizedBox(width: AppSpacing.sm),
            Icon(
              Icons.chevron_right_rounded,
              size: 18,
              color: scheme.onSurfaceVariant,
            ),
          ],
        ],
      ),
    );
  }
}

class _SyncFooter extends StatelessWidget {
  const _SyncFooter({
    required this.sync,
    required this.localPending,
    this.onTap,
  });

  final DashboardSync sync;
  final int localPending;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final ColorScheme scheme = Theme.of(context).colorScheme;
    final String lastSynced = sync.lastSyncedAt != null
        ? Formatters.dateTime(sync.lastSyncedAt)
        : 'Never';
    return AppCard(
      color: scheme.tertiaryContainer,
      borderColor: scheme.tertiaryContainer,
      onTap: onTap,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(
                Icons.sync_rounded,
                size: 18,
                color: scheme.onTertiaryContainer,
              ),
              const SizedBox(width: AppSpacing.sm),
              Text(
                'Sync status',
                style: Theme.of(context).textTheme.titleSmall?.copyWith(
                  fontWeight: FontWeight.w700,
                  color: scheme.onTertiaryContainer,
                ),
              ),
              const Spacer(),
              if (localPending > 0)
                AppBadge(
                  label: '$localPending pending',
                  variant: AppBadgeVariant.warning,
                  compact: true,
                )
              else
                AppBadge(
                  label: 'Up to date',
                  variant: AppBadgeVariant.success,
                  compact: true,
                ),
              if (onTap != null) ...[
                const SizedBox(width: AppSpacing.sm),
                Icon(
                  Icons.chevron_right_rounded,
                  size: 18,
                  color: scheme.onTertiaryContainer,
                ),
              ],
            ],
          ),
          const SizedBox(height: AppSpacing.sm),
          Text(
            'Last synced: $lastSynced',
            style: Theme.of(
              context,
            ).textTheme.bodySmall?.copyWith(color: scheme.onTertiaryContainer),
          ),
          const SizedBox(height: 4),
          Text(
            'Not yet uploaded (this device): $localPending · Awaiting server processing: ${sync.pendingCount} · Unsynced assignments: ${sync.unsyncedAssignments}',
            style: Theme.of(
              context,
            ).textTheme.labelSmall?.copyWith(color: scheme.onTertiaryContainer),
          ),
          const SizedBox(height: 4),
          Text(
            '“Not yet uploaded” = queued on this device (offline). “Awaiting server” = received by server but not yet processed. Tap to view queue.',
            style: Theme.of(context).textTheme.labelSmall?.copyWith(
              color: scheme.onTertiaryContainer.withValues(alpha: 0.8),
              fontStyle: FontStyle.italic,
            ),
          ),
        ],
      ),
    );
  }
}

class _DashboardSkeleton extends StatelessWidget {
  const _DashboardSkeleton();

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        AppCard(
          child: Wrap(
            spacing: AppSpacing.sm,
            runSpacing: AppSpacing.sm,
            children: List.generate(
              5,
              (i) => const AppSkeletonBox(
                width: 96,
                height: 72,
                radius: AppRadius.md,
              ),
            ),
          ),
        ),
        const SizedBox(height: AppSpacing.lg),
        const AppSkeletonBox(height: 148),
        const SizedBox(height: AppSpacing.lg),
        const AppSkeletonBox(height: 72),
        const SizedBox(height: AppSpacing.md),
        const AppSkeletonBox(height: 72),
      ],
    );
  }
}
