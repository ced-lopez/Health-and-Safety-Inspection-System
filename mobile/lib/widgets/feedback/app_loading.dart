import 'package:flutter/material.dart';

import '../../../core/constants/app_constants.dart';
import '../buttons/app_button.dart';

/// Full-page loading state (centered spinner + optional label).
class AppPageLoader extends StatelessWidget {
  const AppPageLoader({super.key, this.label});

  final String? label;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const AppSpinner(size: 28),
          if (label != null) ...[
            const SizedBox(height: AppSpacing.lg),
            Text(
              label!,
              style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                color: Theme.of(context).colorScheme.onSurfaceVariant,
              ),
            ),
          ],
        ],
      ),
    );
  }
}

/// A shimmering placeholder block used while real content loads.
class AppSkeletonBox extends StatefulWidget {
  const AppSkeletonBox({
    super.key,
    this.width,
    this.height = 16,
    this.radius = 8,
  });

  final double? width;
  final double height;
  final double radius;

  @override
  State<AppSkeletonBox> createState() => _AppSkeletonBoxState();
}

class _AppSkeletonBoxState extends State<AppSkeletonBox>
    with SingleTickerProviderStateMixin {
  late final AnimationController _controller = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 1200),
  )..repeat(reverse: true);

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final ColorScheme scheme = Theme.of(context).colorScheme;

    return AnimatedBuilder(
      animation: _controller,
      builder: (context, _) {
        final double t = _controller.value;
        final Color base = scheme.surfaceContainerHigh;
        final Color highlight = Color.alphaBlend(
          scheme.onSurface.withValues(alpha: 0.06),
          base,
        );
        return Container(
          width: widget.width,
          height: widget.height,
          decoration: BoxDecoration(
            color: Color.lerp(base, highlight, t),
            borderRadius: BorderRadius.circular(widget.radius),
          ),
        );
      },
    );
  }
}
