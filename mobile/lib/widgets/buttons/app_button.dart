import 'package:flutter/material.dart';

enum AppButtonVariant { primary, secondary, outline, danger, ghost }

enum AppButtonSize { sm, md, lg }

/// Reusable button following the Barangay Daylight design language.
///
/// Supports five variants, three sizes, a built-in loading spinner, and an
/// optional leading icon. Buttons stretch to fill their row unless [expanded]
/// is false.
class AppButton extends StatelessWidget {
  const AppButton({
    super.key,
    required this.onPressed,
    this.label,
    this.icon,
    this.variant = AppButtonVariant.primary,
    this.size = AppButtonSize.md,
    this.loading = false,
    this.expanded = true,
  }) : assert(label != null || icon != null, 'Provide a label or icon.');

  final VoidCallback onPressed;
  final String? label;
  final IconData? icon;
  final AppButtonVariant variant;
  final AppButtonSize size;
  final bool loading;
  final bool expanded;

  bool get _enabled => !loading;

  @override
  Widget build(BuildContext context) {
    final EdgeInsetsGeometry padding = switch (size) {
      AppButtonSize.sm => const EdgeInsets.symmetric(
        horizontal: 14,
        vertical: 10,
      ),
      AppButtonSize.md => const EdgeInsets.symmetric(
        horizontal: 18,
        vertical: 14,
      ),
      AppButtonSize.lg => const EdgeInsets.symmetric(
        horizontal: 22,
        vertical: 17,
      ),
    };

    final TextStyle baseStyle =
        (Theme.of(context).textTheme.labelLarge ?? const TextStyle()).copyWith(
          fontWeight: FontWeight.w600,
        );

    final ColorScheme scheme = Theme.of(context).colorScheme;

    final Widget child = Row(
      mainAxisSize: expanded ? MainAxisSize.max : MainAxisSize.min,
      mainAxisAlignment: MainAxisAlignment.center,
      children: [
        if (loading)
          const SizedBox(
            width: 18,
            height: 18,
            child: CircularProgressIndicator(strokeWidth: 2.2),
          )
        else if (icon != null)
          Icon(icon, size: 18)
        else
          const SizedBox.shrink(),
        if ((icon != null || loading) && label != null)
          const SizedBox(width: 8),
        if (label != null) Text(label!, style: baseStyle),
      ],
    );

    switch (variant) {
      case AppButtonVariant.primary:
        return FilledButton(
          onPressed: _enabled ? onPressed : null,
          style: FilledButton.styleFrom(
            padding: padding,
            backgroundColor: scheme.primary,
            foregroundColor: scheme.onPrimary,
            textStyle: baseStyle,
          ),
          child: child,
        );
      case AppButtonVariant.secondary:
        return FilledButton(
          onPressed: _enabled ? onPressed : null,
          style: FilledButton.styleFrom(
            padding: padding,
            backgroundColor: scheme.secondary,
            foregroundColor: scheme.onSecondary,
            textStyle: baseStyle,
          ),
          child: child,
        );
      case AppButtonVariant.danger:
        return FilledButton(
          onPressed: _enabled ? onPressed : null,
          style: FilledButton.styleFrom(
            padding: padding,
            backgroundColor: scheme.error,
            foregroundColor: Colors.white,
            textStyle: baseStyle,
          ),
          child: child,
        );
      case AppButtonVariant.outline:
        return OutlinedButton(
          onPressed: _enabled ? onPressed : null,
          style: OutlinedButton.styleFrom(
            padding: padding,
            foregroundColor: scheme.primary,
            side: BorderSide(color: scheme.outlineVariant),
            textStyle: baseStyle,
          ),
          child: child,
        );
      case AppButtonVariant.ghost:
        return TextButton(
          onPressed: _enabled ? onPressed : null,
          style: TextButton.styleFrom(
            padding: padding,
            foregroundColor: scheme.primary,
            textStyle: baseStyle,
          ),
          child: child,
        );
    }
  }
}

/// A compact circular spinner used inline (buttons, list rows).
class AppSpinner extends StatelessWidget {
  const AppSpinner({
    super.key,
    this.size = 20,
    this.strokeWidth = 2.4,
    this.color,
  });

  final double size;
  final double strokeWidth;
  final Color? color;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: size,
      height: size,
      child: CircularProgressIndicator(
        strokeWidth: strokeWidth,
        color: color ?? Theme.of(context).colorScheme.primary,
      ),
    );
  }
}
