import 'package:flutter/material.dart';

import '../../../core/constants/app_constants.dart';

/// Reusable surface card with the standard rounded border and soft padding.
/// Optionally tappable.
class AppCard extends StatelessWidget {
  const AppCard({
    super.key,
    required this.child,
    this.padding = const EdgeInsets.all(AppSpacing.lg),
    this.margin,
    this.color,
    this.borderColor,
    this.onTap,
    this.borderRadius = const BorderRadius.all(Radius.circular(AppRadius.lg)),
  });

  final Widget child;
  final EdgeInsetsGeometry padding;
  final EdgeInsetsGeometry? margin;
  final Color? color;
  final Color? borderColor;
  final VoidCallback? onTap;
  final BorderRadius borderRadius;

  @override
  Widget build(BuildContext context) {
    final ColorScheme scheme = Theme.of(context).colorScheme;

    final Widget surface = AnimatedContainer(
      duration: AppDurations.fast,
      margin: margin,
      padding: padding,
      decoration: BoxDecoration(
        color: color ?? scheme.surfaceContainerHigh,
        borderRadius: borderRadius,
        border: Border.all(color: borderColor ?? scheme.outlineVariant),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.03),
            blurRadius: 12,
            offset: const Offset(0, 4),
          ),
        ],
      ),
      child: child,
    );

    if (onTap == null) return surface;

    return Material(
      color: Colors.transparent,
      child: InkWell(onTap: onTap, borderRadius: borderRadius, child: surface),
    );
  }
}
