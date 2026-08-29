import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/constants/app_constants.dart';
import '../../../core/errors/api_exception.dart';
import '../../../core/utils/validators.dart';
import '../../../widgets/buttons/app_button.dart';
import '../../../widgets/brand/app_brand.dart';
import '../../../widgets/cards/app_card.dart';
import '../../../widgets/inputs/app_text_field.dart';
import '../providers/auth_providers.dart';

/// Inspector sign-in screen. Uses the Sanctum-backed `POST /v1/auth/login`
/// endpoint with `portal: 'staff'`.
class LoginScreen extends ConsumerStatefulWidget {
  const LoginScreen({super.key});

  @override
  ConsumerState<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends ConsumerState<LoginScreen> {
  final TextEditingController _email = TextEditingController();
  final TextEditingController _password = TextEditingController();

  String? _emailError;
  String? _passwordError;
  String? _formError;
  bool _rememberMe = false;
  bool _signingIn = false;

  @override
  void dispose() {
    _email.dispose();
    _password.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    FocusScope.of(context).unfocus();
    setState(() {
      _emailError = Validators.email(_email.text);
      _passwordError = Validators.password(_password.text, minLength: 6);
    });
    if (_emailError != null || _passwordError != null) return;

    setState(() {
      _signingIn = true;
      _formError = null;
    });

    final controller = ref.read(authControllerProvider.notifier);
    await controller.login(
      email: _email.text.trim(),
      password: _password.text,
      rememberMe: _rememberMe,
    );

    if (!mounted) return;
    final Object? error = ref.read(authControllerProvider).error;
    setState(() {
      _signingIn = false;
      _formError = error is ApiException
          ? error.message
          : (error == null ? null : 'Unable to sign in. Please try again.');
    });
  }

  @override
  Widget build(BuildContext context) {
    final ColorScheme scheme = Theme.of(context).colorScheme;

    return Scaffold(
      backgroundColor: scheme.surface,
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.symmetric(
              horizontal: AppSpacing.xl,
              vertical: AppSpacing.xl,
            ),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 420),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const AppBrand(),
                  const SizedBox(height: AppSpacing.xxl),
                  AppCard(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        Text(
                          'Sign in',
                          style: Theme.of(context).textTheme.headlineMedium
                              ?.copyWith(fontWeight: FontWeight.w800),
                        ),
                        const SizedBox(height: AppSpacing.xs),
                        Text(
                          'Inspector portal · Barangay 178',
                          style: Theme.of(context).textTheme.bodyMedium
                              ?.copyWith(color: scheme.onSurfaceVariant),
                        ),
                        const SizedBox(height: AppSpacing.xl),
                        AppTextField(
                          controller: _email,
                          label: 'Email address',
                          hint: 'name@example.com',
                          icon: Icons.mail_outline_rounded,
                          keyboardType: TextInputType.emailAddress,
                          textInputAction: TextInputAction.next,
                          errorText: _emailError,
                          autofillHints: const [AutofillHints.email],
                          onChanged: (_) {
                            if (_formError != null) {
                              setState(() => _formError = null);
                            }
                          },
                        ),
                        const SizedBox(height: AppSpacing.lg),
                        AppPasswordField(
                          controller: _password,
                          hint: 'Enter your password',
                          errorText: _passwordError,
                          textInputAction: TextInputAction.done,
                          onSubmitted: (_) => _submit(),
                        ),
                        if (_formError != null) ...[
                          const SizedBox(height: AppSpacing.lg),
                          _InlineError(message: _formError!),
                        ],
                        const SizedBox(height: AppSpacing.md),
                        Row(
                          children: [
                            Transform.scale(
                              scale: 1.1,
                              child: Checkbox(
                                value: _rememberMe,
                                onChanged: (bool? v) =>
                                    setState(() => _rememberMe = v ?? false),
                              ),
                            ),
                            const SizedBox(width: AppSpacing.sm),
                            Text(
                              'Remember me',
                              style: Theme.of(context).textTheme.bodyMedium,
                            ),
                          ],
                        ),
                        const SizedBox(height: AppSpacing.lg),
                        AppButton(
                          onPressed: _signingIn ? () {} : _submit,
                          label: 'Sign in',
                          icon: Icons.login_rounded,
                          size: AppButtonSize.lg,
                          loading: _signingIn,
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: AppSpacing.lg),
                  Text(
                    'Only assigned inspectors can sign in.',
                    textAlign: TextAlign.center,
                    style: Theme.of(context).textTheme.bodySmall?.copyWith(
                      color: scheme.onSurfaceVariant,
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}

class _InlineError extends StatelessWidget {
  const _InlineError({required this.message});

  final String message;

  @override
  Widget build(BuildContext context) {
    final ColorScheme scheme = Theme.of(context).colorScheme;
    return Container(
      padding: const EdgeInsets.all(AppSpacing.md),
      decoration: BoxDecoration(
        color: scheme.errorContainer,
        borderRadius: BorderRadius.circular(AppRadius.md),
      ),
      child: Row(
        children: [
          Icon(
            Icons.error_outline_rounded,
            size: 18,
            color: scheme.onErrorContainer,
          ),
          const SizedBox(width: AppSpacing.sm),
          Expanded(
            child: Text(
              message,
              style: Theme.of(
                context,
              ).textTheme.bodySmall?.copyWith(color: scheme.onErrorContainer),
            ),
          ),
        ],
      ),
    );
  }
}
