import 'package:flutter/material.dart';

import '../../../core/constants/app_constants.dart';

/// Brand mark used on the login screen and app bars: a rounded tile with the
/// barangay initials and the system name.
class AppBrand extends StatelessWidget {
  const AppBrand({super.key, this.title, this.subtitle, this.dense = false});

  final String? title;
  final String? subtitle;
  final bool dense;

  @override
  Widget build(BuildContext context) {
    final ColorScheme scheme = Theme.of(context).colorScheme;
    final TextTheme textTheme = Theme.of(context).textTheme;

    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Container(
          width: dense ? 36 : 48,
          height: dense ? 36 : 48,
          decoration: BoxDecoration(
            color: scheme.primary,
            borderRadius: BorderRadius.circular(AppRadius.md),
          ),
          child: Center(
            child: Text(
              'B178',
              style: textTheme.labelLarge?.copyWith(
                color: scheme.onPrimary,
                fontWeight: FontWeight.w800,
                fontSize: dense ? 11 : 13,
              ),
            ),
          ),
        ),
        const SizedBox(width: AppSpacing.md),
        Flexible(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(
                title ?? 'Barangay 178 Inspector',
                style: textTheme.titleMedium?.copyWith(
                  fontWeight: FontWeight.w800,
                ),
                overflow: TextOverflow.ellipsis,
              ),
              if (subtitle != null)
                Text(
                  subtitle!,
                  style: textTheme.bodySmall?.copyWith(
                    color: scheme.onSurfaceVariant,
                  ),
                  overflow: TextOverflow.ellipsis,
                ),
            ],
          ),
        ),
      ],
    );
  }
}
