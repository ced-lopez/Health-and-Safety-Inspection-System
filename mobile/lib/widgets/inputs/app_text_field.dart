import 'package:flutter/material.dart';

import '../../../core/constants/app_constants.dart';

/// Form input following the design system. Wraps [TextField] with a label,
/// error text, leading icon, and optional trailing widget.
class AppTextField extends StatelessWidget {
  const AppTextField({
    super.key,
    required this.controller,
    this.label,
    this.hint,
    this.icon,
    this.suffix,
    this.errorText,
    this.obscureText = false,
    this.keyboardType,
    this.textInputAction,
    this.maxLength,
    this.maxLines = 1,
    this.readOnly = false,
    this.autofillHints,
    this.onChanged,
    this.onSubmitted,
    this.enabled = true,
    this.enableSuggestions = true,
    this.autocorrect = true,
  });

  final TextEditingController controller;
  final String? label;
  final String? hint;
  final IconData? icon;
  final Widget? suffix;
  final String? errorText;
  final bool obscureText;
  final TextInputType? keyboardType;
  final TextInputAction? textInputAction;
  final int? maxLength;
  final int maxLines;
  final bool readOnly;
  final Iterable<String>? autofillHints;
  final ValueChanged<String>? onChanged;
  final ValueChanged<String>? onSubmitted;
  final bool enabled;
  final bool enableSuggestions;
  final bool autocorrect;

  @override
  Widget build(BuildContext context) {
    final ColorScheme scheme = Theme.of(context).colorScheme;
    final ThemeData theme = Theme.of(context);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        if (label != null) ...[
          Text(
            label!,
            style: theme.textTheme.bodyMedium?.copyWith(
              fontWeight: FontWeight.w600,
              color: scheme.onSurface,
            ),
          ),
          const SizedBox(height: AppSpacing.xs),
        ],
        TextField(
          controller: controller,
          obscureText: obscureText,
          keyboardType: keyboardType,
          textInputAction: textInputAction,
          maxLength: maxLength,
          maxLines: maxLines,
          readOnly: readOnly,
          enabled: enabled,
          enableSuggestions: enableSuggestions,
          autocorrect: autocorrect,
          autofillHints: autofillHints,
          onChanged: onChanged,
          onSubmitted: onSubmitted,
          decoration: InputDecoration(
            hintText: hint,
            prefixIcon: icon != null ? Icon(icon, size: 20) : null,
            suffixIcon: suffix,
            errorText: errorText,
            counterText: maxLength == null ? '' : null,
          ),
        ),
      ],
    );
  }
}

/// Password field with a built-in show/hide toggle.
class AppPasswordField extends StatefulWidget {
  const AppPasswordField({
    super.key,
    required this.controller,
    this.label = 'Password',
    this.hint,
    this.errorText,
    this.textInputAction,
    this.onSubmitted,
  });

  final TextEditingController controller;
  final String label;
  final String? hint;
  final String? errorText;
  final TextInputAction? textInputAction;
  final ValueChanged<String>? onSubmitted;

  @override
  State<AppPasswordField> createState() => _AppPasswordFieldState();
}

class _AppPasswordFieldState extends State<AppPasswordField> {
  bool _visible = false;

  @override
  Widget build(BuildContext context) {
    final ColorScheme scheme = Theme.of(context).colorScheme;

    return AppTextField(
      controller: widget.controller,
      label: widget.label,
      hint: widget.hint,
      errorText: widget.errorText,
      obscureText: !_visible,
      textInputAction: widget.textInputAction,
      onSubmitted: widget.onSubmitted,
      keyboardType: TextInputType.visiblePassword,
      autocorrect: false,
      enableSuggestions: false,
      autofillHints: const [AutofillHints.password],
      icon: Icons.lock_outline_rounded,
      suffix: IconButton(
        onPressed: () => setState(() => _visible = !_visible),
        icon: Icon(
          _visible ? Icons.visibility_off_outlined : Icons.visibility_outlined,
          size: 20,
          color: scheme.onSurfaceVariant,
        ),
      ),
    );
  }
}
