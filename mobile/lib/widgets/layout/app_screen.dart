import 'package:flutter/material.dart';

import '../../../core/constants/app_constants.dart';

/// Standard page layout: applies the surface background, optional app bar, and
/// consistent horizontal padding.
class AppScreen extends StatelessWidget {
  const AppScreen({
    super.key,
    this.title,
    this.child,
    this.actions,
    this.padding = const EdgeInsets.fromLTRB(
      AppSpacing.lg,
      AppSpacing.sm,
      AppSpacing.lg,
      AppSpacing.xl,
    ),
    this.resizeToAvoidBottomInset = true,
  });

  final String? title;
  final Widget? child;
  final List<Widget>? actions;
  final EdgeInsetsGeometry padding;
  final bool resizeToAvoidBottomInset;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Theme.of(context).colorScheme.surface,
      resizeToAvoidBottomInset: resizeToAvoidBottomInset,
      appBar: title == null
          ? null
          : AppBar(title: Text(title!), actions: actions),
      body: SafeArea(
        child: Padding(padding: padding, child: child),
      ),
    );
  }
}
