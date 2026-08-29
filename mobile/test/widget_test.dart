// Foundation smoke tests: the design system renders in both light and dark
// without requiring network or platform channels.

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:inspection_app/core/theme/theme.dart';
import 'package:inspection_app/widgets/buttons/app_button.dart';
import 'package:inspection_app/widgets/cards/app_card.dart';
import 'package:inspection_app/widgets/inputs/app_text_field.dart';

void main() {
  testWidgets('design system renders a card, field, and button in light mode',
      (WidgetTester tester) async {
    await tester.pumpWidget(
      MaterialApp(
        theme: AppTheme.light(),
        home: Scaffold(
          body: AppCard(
            child: Column(
              children: [
                AppTextField(controller: TextEditingController()),
                AppButton(onPressed: () {}, label: 'Continue'),
              ],
            ),
          ),
        ),
      ),
    );

    expect(find.text('Continue'), findsOneWidget);
  });

  testWidgets('design system renders in dark mode', (WidgetTester tester) async {
    await tester.pumpWidget(
      MaterialApp(
        theme: AppTheme.dark(),
        home: Scaffold(body: AppButton(onPressed: () {}, label: 'Save')),
      ),
    );

    expect(find.text('Save'), findsOneWidget);
  });

  test('light and dark themes expose a fixed surface tone', () {
    expect(AppTheme.light().colorScheme.surface, const Color(0xFFFAFBF7));
    expect(AppTheme.dark().colorScheme.surface, const Color(0xFF101410));
  });
}