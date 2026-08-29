import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/config/app_config.dart';
import '../../../core/constants/app_constants.dart';
import '../../../core/database/app_database.dart';
import '../../../core/models/user.dart';
import '../../../core/providers/app_providers.dart';
import '../../../router/app_routes.dart';
import '../../../widgets/badges/app_badge.dart';
import '../../../widgets/buttons/app_button.dart';
import '../../../widgets/cards/app_card.dart';
import '../../../widgets/dialogs/app_dialog.dart';
import '../../../widgets/layout/app_screen.dart';
import '../../authentication/models/auth_state.dart';
import '../../authentication/providers/auth_providers.dart';
import '../../inspections/providers/assignments_providers.dart';

/// Inspector profile: account summary, settings entry, and sign out.
class ProfileScreen extends ConsumerWidget {
  const ProfileScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final ColorScheme scheme = Theme.of(context).colorScheme;
    final AsyncValue<AuthState> auth = ref.watch(authControllerProvider);
    final User? user = switch (auth.valueOrNull) {
      AuthAuthenticated(:final user) => user,
      _ => null,
    };

    return AppScreen(
      title: 'Profile',
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          AppCard(
            child: Row(
              children: [
                CircleAvatar(
                  radius: 28,
                  backgroundColor: scheme.primaryContainer,
                  child: Text(
                    _initials(user?.name ?? 'I'),
                    style: Theme.of(context).textTheme.titleLarge?.copyWith(
                      color: scheme.onPrimaryContainer,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
                const SizedBox(width: AppSpacing.lg),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        user?.name ?? 'Inspector',
                        style: Theme.of(context).textTheme.titleMedium
                            ?.copyWith(fontWeight: FontWeight.w800),
                      ),
                      const SizedBox(height: AppSpacing.xs),
                      Text(
                        user?.email ?? '',
                        style: Theme.of(context).textTheme.bodySmall?.copyWith(
                          color: scheme.onSurfaceVariant,
                        ),
                      ),
                    ],
                  ),
                ),
                AppBadge(
                  label: user?.role?.name ?? 'Inspector',
                  variant: AppBadgeVariant.success,
                  compact: true,
                ),
              ],
            ),
          ),
          const SizedBox(height: AppSpacing.lg),
          AppCard(
            child: Column(
              children: [
                ListTile(
                  contentPadding: EdgeInsets.zero,
                  leading: const Icon(Icons.settings_outlined),
                  title: const Text('Settings'),
                  subtitle: const Text('Appearance and preferences'),
                  trailing: const Icon(Icons.chevron_right_rounded),
                  onTap: () => context.go('${AppRoutes.profile}/settings'),
                ),
              ],
            ),
          ),
          const SizedBox(height: AppSpacing.lg),
          AppButton(
            onPressed: () => _confirmSignOut(context, ref),
            label: 'Sign out',
            icon: Icons.logout_rounded,
            variant: AppButtonVariant.danger,
          ),
          const Spacer(),
          Text(
            '${AppConfig.appName} · v${AppConfig.appVersion}',
            textAlign: TextAlign.center,
            style: Theme.of(
              context,
            ).textTheme.bodySmall?.copyWith(color: scheme.onSurfaceVariant),
          ),
        ],
      ),
    );
  }

  Future<void> _confirmSignOut(BuildContext context, WidgetRef ref) async {
    // Check for pending offline work before clearing.
    final AppDatabase db = ref.read(appDatabaseProvider);
    int pending = 0;
    try {
      pending = await db.pendingCount();
    } catch (_) {
      pending = ref.read(pendingSyncCountProvider).valueOrNull ?? 0;
    }

    if (pending > 0) {
      // Try to flush if online.
      final bool online = ref.read(onlineProvider).valueOrNull ?? false;
      if (online) {
        try {
          await ref.read(syncServiceProvider).processQueue();
          pending = await db.pendingCount();
        } catch (_) {
          pending = await db.pendingCount();
        }
        ref.invalidate(pendingSyncCountProvider);
      }
    }

    if (pending > 0) {
      if (!context.mounted) return;
      final bool discard = await AppDialog.confirm(
        context,
        title: 'Unsynced work',
        message:
            'You have $pending pending item(s) that have not been uploaded. '
            'Signing out will permanently discard them and clear cached inspections. '
            'Connect to the internet and sync first if you want to keep this work.',
        confirmLabel: 'Discard & sign out',
        danger: true,
      );
      if (!discard || !context.mounted) return;
    } else {
      if (!context.mounted) return;
      final bool confirmed = await AppDialog.confirm(
        context,
        title: 'Sign out',
        message: 'Are you sure you want to sign out of the inspector app?',
        confirmLabel: 'Sign out',
        danger: true,
      );
      if (!confirmed || !context.mounted) return;
    }
    await ref.read(authControllerProvider.notifier).logout();
  }

  String _initials(String name) {
    final List<String> parts = name.trim().split(RegExp(r'\s+'));
    if (parts.isEmpty || parts.first.isEmpty) return 'I';
    if (parts.length == 1) return parts.first[0].toUpperCase();
    return (parts.first[0] + parts.last[0]).toUpperCase();
  }
}
