import 'package:flutter/material.dart';

/// Design tokens for the "Barangay Daylight" design language, mirrored from the
/// web application (frontend/src/index.css). Do not introduce new hues — reuse
/// these tokens so the web and mobile surfaces stay visually identical.
abstract final class AppColors {
  // ---------------------------------------------------------------------------
  // Light theme — Barangay Daylight
  // ---------------------------------------------------------------------------
  static const Color lightBackground = Color(0xFFFAFBF7);
  static const Color lightSurface = Color(0xFFFFFFFF);
  static const Color lightCard = Color(0xFFFFFFFF);
  static const Color lightForeground = Color(0xFF1A2E1F);
  static const Color lightPrimary = Color(0xFFFF8A3D);
  static const Color lightOnPrimary = Color(0xFFFFFFFF);
  static const Color lightSecondary = Color(0xFFE8F3EB);
  static const Color lightOnSecondary = Color(0xFF1A5C32);
  static const Color lightMuted = Color(0xFFF0F2EC);
  static const Color lightOnMuted = Color(0xFF5A6B5E);
  static const Color lightAccent = Color(0xFF2E8B47);
  static const Color lightOnAccent = Color(0xFFFFFFFF);
  static const Color lightDanger = Color(0xFFC0392B);
  static const Color lightBorder = Color(0xFFDDE5D9);
  static const Color lightRing = Color(0xFF2E8B47);
  static const Color lightSidebarAccent = Color(0xFFFFF1E6);
  static const Color lightSidebarAccentForeground = Color(0xFFB45F1F);

  // ---------------------------------------------------------------------------
  // Dark theme — Barangay Night
  // ---------------------------------------------------------------------------
  static const Color darkBackground = Color(0xFF101410);
  static const Color darkSurface = Color(0xFF171C16);
  static const Color darkCard = Color(0xFF171C16);
  static const Color darkForeground = Color(0xFFEEF2EA);
  static const Color darkPrimary = Color(0xFFFF8A3D);
  static const Color darkOnPrimary = Color(0xFFFFFFFF);
  static const Color darkSecondary = Color(0xFF1C2118);
  static const Color darkOnSecondary = Color(0xFFBFE3C9);
  static const Color darkMuted = Color(0xFF1A1F17);
  static const Color darkOnMuted = Color(0xFF9AA79A);
  static const Color darkAccent = Color(0xFF3FAE63);
  static const Color darkOnAccent = Color(0xFFFFFFFF);
  static const Color darkDanger = Color(0xFFE5484D);
  static const Color darkBorder = Color(0xFF2A3226);
  static const Color darkRing = Color(0xFF3FAE63);
}

/// Builds the [ColorScheme] for the Barangay Daylight / Night palettes.
abstract final class AppColorScheme {
  static ColorScheme light() => _build(
    Brightness.light,
    AppColors.lightBackground,
    AppColors.lightCard,
    AppColors.lightForeground,
    AppColors.lightPrimary,
    AppColors.lightSecondary,
    AppColors.lightMuted,
    AppColors.lightOnMuted,
    AppColors.lightAccent,
    AppColors.lightDanger,
    AppColors.lightBorder,
  );

  static ColorScheme dark() => _build(
    Brightness.dark,
    AppColors.darkBackground,
    AppColors.darkCard,
    AppColors.darkForeground,
    AppColors.darkPrimary,
    AppColors.darkSecondary,
    AppColors.darkMuted,
    AppColors.darkOnMuted,
    AppColors.darkAccent,
    AppColors.darkDanger,
    AppColors.darkBorder,
  );

  static ColorScheme _build(
    Brightness brightness,
    Color background,
    Color card,
    Color foreground,
    Color primary,
    Color secondary,
    Color muted,
    Color onMuted,
    Color accent,
    Color danger,
    Color border,
  ) {
    final bool dark = brightness == Brightness.dark;
    final Color onPrimary = dark
        ? AppColors.darkOnPrimary
        : AppColors.lightOnPrimary;
    final Color onSecondary = dark
        ? AppColors.darkOnSecondary
        : AppColors.lightOnSecondary;
    final Color onAccent = dark
        ? AppColors.darkOnAccent
        : AppColors.lightOnAccent;

    Color tinted(Color base, double alpha) =>
        Color.alphaBlend(base.withValues(alpha: alpha), background);

    return ColorScheme(
      brightness: brightness,
      primary: primary,
      onPrimary: onPrimary,
      primaryContainer: tinted(primary, dark ? 0.18 : 0.14),
      onPrimaryContainer: foreground,
      secondary: secondary,
      onSecondary: onSecondary,
      secondaryContainer: tinted(accent, dark ? 0.16 : 0.12),
      onSecondaryContainer: accent,
      tertiary: accent,
      onTertiary: onAccent,
      error: danger,
      onError: Colors.white,
      errorContainer: tinted(danger, 0.16),
      onErrorContainer: danger,
      surface: background,
      onSurface: foreground,
      onSurfaceVariant: onMuted,
      outline: border,
      outlineVariant: border,
      surfaceContainerLowest: dark
          ? const Color(0xFF0C0F0B)
          : const Color(0xFFFDFEFA),
      surfaceContainerLow: muted,
      surfaceContainer: card,
      surfaceContainerHigh: muted,
      surfaceContainerHighest: muted,
      surfaceTint: primary,
      inverseSurface: foreground,
      onInverseSurface: background,
      inversePrimary: accent,
      shadow: Colors.black,
      scrim: Colors.black,
    );
  }
}
