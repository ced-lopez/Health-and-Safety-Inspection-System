import 'package:flutter/material.dart';

import '../../../core/constants/app_constants.dart';
import '../buttons/app_button.dart';

/// Full-section error state with an optional retry action.
class AppErrorState extends StatelessWidget {
  const AppErrorState({
    super.key,
    this.message,
    this.onRetry,
    this.compact = false,
  });

  final String? message;
  final VoidCallback? onRetry;

  /// When true the widget is rendered as a compact inline card rather than a
  /// full-height centered layout (useful inside list sections).
  final bool compact;

  @override
  Widget build(BuildContext context) {
    final ColorScheme scheme = Theme.of(context).colorScheme;
    final String text = message ?? 'Something went wrong. Please try again.';
    final Widget content = Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        Container(
          width: 56,
          height: 56,
          decoration: BoxDecoration(
            color: scheme.errorContainer,
            shape: BoxShape.circle,
          ),
          child: Icon(
            Icons.error_outline_rounded,
            color: scheme.onErrorContainer,
          ),
        ),
        const SizedBox(height: AppSpacing.md),
        Text(
          text,
          textAlign: TextAlign.center,
          style: Theme.of(
            context,
          ).textTheme.bodyMedium?.copyWith(color: scheme.onSurfaceVariant),
        ),
        if (onRetry != null) ...[
          const SizedBox(height: AppSpacing.lg),
          AppButton(
            onPressed: onRetry!,
            label: 'Try again',
            icon: Icons.refresh_rounded,
            variant: AppButtonVariant.outline,
            size: AppButtonSize.sm,
            expanded: false,
          ),
        ],
      ],
    );

    if (compact) {
      return Padding(
        padding: const EdgeInsets.all(AppSpacing.lg),
        child: content,
      );
    }

    return Center(
      child: Padding(
        padding: const EdgeInsets.all(AppSpacing.xl),
        child: ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 320),
          child: content,
        ),
      ),
    );
  }
}
