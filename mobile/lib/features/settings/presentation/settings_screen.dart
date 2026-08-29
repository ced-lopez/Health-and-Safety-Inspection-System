import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/config/app_config.dart';
import '../../../core/constants/app_constants.dart';
import '../../../core/providers/app_providers.dart';
import '../../../widgets/cards/app_card.dart';
import '../../../widgets/layout/app_screen.dart';

/// Settings: appearance (theme mode) and app info. Additional preferences are
/// added as the foundation grows.
class SettingsScreen extends ConsumerWidget {
  const SettingsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final ColorScheme scheme = Theme.of(context).colorScheme;
    final ThemeMode mode = ref.watch(themeModeProvider);

    return AppScreen(
      title: 'Settings',
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          AppCard(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Appearance',
                  style: Theme.of(context).textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.w700,
                  ),
                ),
                const SizedBox(height: AppSpacing.sm),
                RadioGroup<ThemeMode>(
                  groupValue: mode,
                  onChanged: (ThemeMode? next) {
                    if (next != null) {
                      ref.read(themeModeProvider.notifier).state = next;
                    }
                  },
                  child: Column(
                    children: const [
                      RadioListTile<ThemeMode>(
                        value: ThemeMode.system,
                        title: Text('System default'),
                      ),
                      RadioListTile<ThemeMode>(
                        value: ThemeMode.light,
                        title: Text('Light'),
                      ),
                      RadioListTile<ThemeMode>(
                        value: ThemeMode.dark,
                        title: Text('Dark'),
                      ),
                    ],
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
                  'About',
                  style: Theme.of(context).textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.w700,
                  ),
                ),
                const SizedBox(height: AppSpacing.md),
                _InfoRow(label: 'Application', value: AppConfig.appName),
                const SizedBox(height: AppSpacing.sm),
                _InfoRow(label: 'Version', value: AppConfig.appVersion),
              ],
            ),
          ),
          const SizedBox(height: AppSpacing.lg),
          Text(
            'Barangay 178 · North Caloocan City',
            textAlign: TextAlign.center,
            style: Theme.of(
              context,
            ).textTheme.bodySmall?.copyWith(color: scheme.onSurfaceVariant),
          ),
        ],
      ),
    );
  }
}

class _InfoRow extends StatelessWidget {
  const _InfoRow({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    final ColorScheme scheme = Theme.of(context).colorScheme;
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Text(label, style: Theme.of(context).textTheme.bodyMedium),
        Text(
          value,
          style: Theme.of(
            context,
          ).textTheme.bodyMedium?.copyWith(color: scheme.onSurfaceVariant),
        ),
      ],
    );
  }
}
