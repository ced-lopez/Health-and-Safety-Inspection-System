import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/constants/app_constants.dart';
import '../../../core/database/app_database.dart';
import '../../../core/providers/app_providers.dart';
import '../../../widgets/cards/app_card.dart';
import '../../../widgets/feedback/app_empty_state.dart';
import '../../inspections/providers/assignments_providers.dart';

/// Dead-letter / pending sync queue viewer (P1-7).
///
/// Shows `sync_queue` items with `operation, attempts, last_error, created_at`
/// and allows manual retry (reset attempts) or discard (delete + file cleanup).
class SyncQueueScreen extends ConsumerWidget {
  const SyncQueueScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final ColorScheme scheme = Theme.of(context).colorScheme;
    final AsyncValue<List<Map<String, dynamic>>> queueAsync = ref.watch(
      _syncQueueProvider,
    );

    return Scaffold(
      backgroundColor: scheme.surface,
      appBar: AppBar(title: const Text('Pending sync'), centerTitle: false),
      body: queueAsync.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) => Center(child: Text('Could not load queue: $e')),
        data: (items) {
          if (items.isEmpty) {
            return const Center(
              child: AppEmptyState(
                icon: Icons.cloud_done_outlined,
                title: 'All synced',
                message: 'No pending items. Your work is up to date.',
              ),
            );
          }
          return RefreshIndicator(
            onRefresh: () async {
              ref.invalidate(_syncQueueProvider);
              ref.invalidate(pendingSyncCountProvider);
            },
            child: ListView.builder(
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.fromLTRB(
                AppSpacing.lg,
                AppSpacing.md,
                AppSpacing.lg,
                AppSpacing.xxl,
              ),
              itemCount: items.length + 1,
              itemBuilder: (context, index) {
                if (index == 0) {
                  return Padding(
                    padding: const EdgeInsets.only(bottom: AppSpacing.md),
                    child: Row(
                      children: [
                        Expanded(
                          child: Text(
                            '${items.length} pending item(s)',
                            style: Theme.of(context).textTheme.bodySmall
                                ?.copyWith(color: scheme.onSurfaceVariant),
                          ),
                        ),
                        TextButton(
                          onPressed: () async {
                            await ref.read(syncServiceProvider).processQueue();
                            ref.invalidate(_syncQueueProvider);
                            ref.invalidate(pendingSyncCountProvider);
                          },
                          child: const Text('Retry all'),
                        ),
                      ],
                    ),
                  );
                }
                final Map<String, dynamic> row = items[index - 1];
                return Padding(
                  padding: const EdgeInsets.only(bottom: AppSpacing.md),
                  child: _QueueCard(row: row),
                );
              },
            ),
          );
        },
      ),
    );
  }
}

final FutureProvider<List<Map<String, dynamic>>> _syncQueueProvider =
    FutureProvider<List<Map<String, dynamic>>>((ref) async {
      final AppDatabase db = ref.watch(appDatabaseProvider);
      return db.peekQueue(limit: 50);
    });

class _QueueCard extends ConsumerWidget {
  const _QueueCard({required this.row});

  final Map<String, dynamic> row;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final ColorScheme scheme = Theme.of(context).colorScheme;
    final int id = row['id'] as int;
    final String op = row['operation'] as String;
    final String? assignmentId = row['assignment_id'] as String?;
    final int attempts = row['attempts'] as int? ?? 0;
    final String? lastError = row['last_error'] as String?;
    final String? createdAt = row['created_at'] as String?;

    final bool deadLetter = attempts >= 5;

    return AppCard(
      padding: const EdgeInsets.all(AppSpacing.md),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                decoration: BoxDecoration(
                  color: deadLetter
                      ? scheme.errorContainer
                      : scheme.secondaryContainer,
                  borderRadius: BorderRadius.circular(AppRadius.pill),
                ),
                child: Text(
                  op,
                  style: Theme.of(context).textTheme.labelSmall?.copyWith(
                    color: deadLetter
                        ? scheme.onErrorContainer
                        : scheme.onSecondaryContainer,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ),
              const SizedBox(width: AppSpacing.sm),
              if (assignmentId != null)
                Text(
                  '#$assignmentId',
                  style: Theme.of(context).textTheme.labelSmall?.copyWith(
                    color: scheme.onSurfaceVariant,
                  ),
                ),
              const Spacer(),
              Text(
                'Attempts: $attempts',
                style: Theme.of(context).textTheme.labelSmall?.copyWith(
                  color: deadLetter ? scheme.error : scheme.onSurfaceVariant,
                ),
              ),
            ],
          ),
          const SizedBox(height: AppSpacing.sm),
          if (createdAt != null)
            Text(
              'Queued: $createdAt',
              style: Theme.of(
                context,
              ).textTheme.labelSmall?.copyWith(color: scheme.onSurfaceVariant),
            ),
          if (lastError != null && lastError.isNotEmpty) ...[
            const SizedBox(height: AppSpacing.sm),
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(AppSpacing.sm),
              decoration: BoxDecoration(
                color: scheme.errorContainer.withValues(alpha: 0.5),
                borderRadius: BorderRadius.circular(AppRadius.md),
              ),
              child: Text(
                lastError,
                style: Theme.of(context).textTheme.labelSmall?.copyWith(
                  color: scheme.onErrorContainer,
                ),
              ),
            ),
          ],
          const SizedBox(height: AppSpacing.md),
          Row(
            children: [
              OutlinedButton.icon(
                onPressed: () async {
                  final AppDatabase db = ref.read(appDatabaseProvider);
                  await db.rawUpdateRetry(id);
                  await ref.read(syncServiceProvider).processQueue();
                  ref.invalidate(_syncQueueProvider);
                  ref.invalidate(pendingSyncCountProvider);
                },
                icon: const Icon(Icons.refresh_rounded, size: 16),
                label: const Text('Retry'),
              ),
              const SizedBox(width: AppSpacing.sm),
              TextButton.icon(
                onPressed: () async {
                  final bool? confirm = await showDialog<bool>(
                    context: context,
                    builder: (ctx) => AlertDialog(
                      title: const Text('Discard item?'),
                      content: Text(
                        'This will permanently remove the queued $op and its evidence files.',
                      ),
                      actions: [
                        TextButton(
                          onPressed: () => Navigator.of(ctx).pop(false),
                          child: const Text('Cancel'),
                        ),
                        FilledButton(
                          onPressed: () => Navigator.of(ctx).pop(true),
                          child: const Text('Discard'),
                        ),
                      ],
                    ),
                  );
                  if (confirm != true) return;
                  final AppDatabase db = ref.read(appDatabaseProvider);
                  // Best-effort file cleanup is handled inside SyncService delete path,
                  // but also try to delete pending files directly.
                  await db.deleteQueueItem(id);
                  ref.invalidate(_syncQueueProvider);
                  ref.invalidate(pendingSyncCountProvider);
                  if (context.mounted) {
                    ScaffoldMessenger.of(
                      context,
                    ).showSnackBar(const SnackBar(content: Text('Discarded.')));
                  }
                },
                icon: const Icon(Icons.delete_outline_rounded, size: 16),
                label: const Text('Discard'),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

/// Helper extension to reset attempts for manual retry.
extension on AppDatabase {
  Future<void> rawUpdateRetry(int id) async {
    final db = await database;
    await db.rawUpdate(
      'UPDATE sync_queue SET attempts = 0, last_error = NULL WHERE id = ?',
      [id],
    );
  }
}
