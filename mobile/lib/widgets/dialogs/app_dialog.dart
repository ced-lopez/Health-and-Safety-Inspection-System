import 'package:flutter/material.dart';

import '../buttons/app_button.dart';

/// Dialog helpers: success, info, and confirmation prompts following the
/// design system.
abstract final class AppDialog {
  static Future<void> success(
    BuildContext context, {
    required String title,
    required String message,
    String confirmLabel = 'OK',
  }) {
    return showDialog<void>(
      context: context,
      builder: (context) => AlertDialog(
        icon: const Icon(
          Icons.check_circle_rounded,
          size: 44,
          color: Color(0xFF2E8B47),
        ),
        title: Text(title),
        content: Text(message),
        actionsAlignment: MainAxisAlignment.center,
        actions: [
          FilledButton(
            onPressed: () => Navigator.of(context).pop(),
            child: Text(confirmLabel),
          ),
        ],
      ),
    );
  }

  static Future<void> info(
    BuildContext context, {
    required String title,
    required String message,
    String confirmLabel = 'Got it',
  }) {
    return showDialog<void>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(title),
        content: Text(message),
        actionsAlignment: MainAxisAlignment.center,
        actions: [
          FilledButton(
            onPressed: () => Navigator.of(context).pop(),
            child: Text(confirmLabel),
          ),
        ],
      ),
    );
  }

  /// Returns true when the user confirms.
  static Future<bool> confirm(
    BuildContext context, {
    required String title,
    required String message,
    String confirmLabel = 'Confirm',
    String cancelLabel = 'Cancel',
    bool danger = false,
  }) async {
    final bool? result = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(title),
        content: Text(message),
        actionsAlignment: MainAxisAlignment.center,
        actions: [
          TextButton(
            onPressed: () => Navigator.of(context).pop(false),
            child: Text(cancelLabel),
          ),
          AppButton(
            onPressed: () => Navigator.of(context).pop(true),
            label: confirmLabel,
            variant: danger
                ? AppButtonVariant.danger
                : AppButtonVariant.primary,
            size: AppButtonSize.sm,
            expanded: false,
          ),
        ],
      ),
    );
    return result ?? false;
  }
}
