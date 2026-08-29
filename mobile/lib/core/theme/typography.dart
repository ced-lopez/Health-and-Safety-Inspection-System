import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

/// Typography for the Barangay Daylight design system.
///
/// Headings use **Manrope** and body copy uses **Inter**, matching the web app.
abstract final class AppTypography {
  static const String headingFont = 'Manrope';
  static const String bodyFont = 'Inter';

  /// Builds the type scale. Headings carry the display/heading/title styles in
  /// Manrope; paragraphs and dense labels use Inter.
  static TextTheme buildTextTheme() {
    final TextTheme manrope = GoogleFonts.manropeTextTheme();
    final TextTheme inter = GoogleFonts.interTextTheme();

    return TextTheme(
      displayLarge: manrope.displayLarge,
      displayMedium: manrope.displayMedium,
      displaySmall: manrope.displaySmall,
      headlineLarge: manrope.headlineLarge,
      headlineMedium: manrope.headlineMedium,
      headlineSmall: manrope.headlineSmall,
      titleLarge: manrope.titleLarge,
      titleMedium: manrope.titleMedium,
      titleSmall: manrope.titleSmall,
      bodyLarge: inter.bodyLarge,
      bodyMedium: inter.bodyMedium,
      bodySmall: inter.bodySmall,
      labelLarge: manrope.labelLarge,
      labelMedium: manrope.labelMedium,
      labelSmall: inter.labelSmall,
    );
  }
}
