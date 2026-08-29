import 'package:flutter/material.dart';

import '../../../core/constants/app_constants.dart';

enum AppBadgeVariant { neutral, success, warning, danger, primary, outline }

/// Small status pill, mirroring the web app's Badge component.
class AppBadge extends StatelessWidget {
  const AppBadge({
    super.key,
    required this.label,
    this.variant = AppBadgeVariant.neutral,
    this.icon,
    this.compact = false,
  });

  final String label;
  final AppBadgeVariant variant;
  final IconData? icon;
  final bool compact;

  @override
  Widget build(BuildContext context) {
    final ColorScheme scheme = Theme.of(context).colorScheme;

    final Color background;
    final Color foreground;
    switch (variant) {
      case AppBadgeVariant.success:
        background = scheme.secondaryContainer;
        foreground = scheme.onSecondaryContainer;
      case AppBadgeVariant.warning:
        background = scheme.primaryContainer;
        foreground = scheme.onPrimaryContainer;
      case AppBadgeVariant.danger:
        background = scheme.errorContainer;
        foreground = scheme.onErrorContainer;
      case AppBadgeVariant.primary:
        background = scheme.primary;
        foreground = scheme.onPrimary;
      case AppBadgeVariant.outline:
        background = Colors.transparent;
        foreground = scheme.onSurfaceVariant;
      case AppBadgeVariant.neutral:
        background = scheme.surfaceContainerHigh;
        foreground = scheme.onSurfaceVariant;
    }

    return Container(
      padding: EdgeInsets.symmetric(
        horizontal: compact ? 8 : 10,
        vertical: compact ? 3 : 5,
      ),
      decoration: BoxDecoration(
        color: background,
        borderRadius: BorderRadius.circular(AppRadius.pill),
        border: variant == AppBadgeVariant.outline
            ? Border.all(color: scheme.outlineVariant)
            : null,
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          if (icon != null) ...[
            Icon(icon, size: 13, color: foreground),
            const SizedBox(width: 5),
          ],
          Text(
            label,
            style:
                (Theme.of(context).textTheme.labelMedium ?? const TextStyle())
                    .copyWith(color: foreground, fontWeight: FontWeight.w600),
          ),
        ],
      ),
    );
  }
}
