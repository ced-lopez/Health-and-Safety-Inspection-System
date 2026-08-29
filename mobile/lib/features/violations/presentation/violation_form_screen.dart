import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:image_picker/image_picker.dart';
import 'package:permission_handler/permission_handler.dart';

import '../../../core/constants/app_constants.dart';
import '../../../core/errors/api_exception.dart';
import '../../../core/providers/app_providers.dart';
import '../../../widgets/buttons/app_button.dart';
import '../../../widgets/cards/app_card.dart';
import '../../inspections/providers/assignments_providers.dart';
import '../data/violations_datasource.dart';
import '../models/violation.dart';
import '../providers/violations_providers.dart';

/// Form for creating a violation — `POST /v1/violations` + optional
/// `POST /{id}/evidence`.
///
/// Can be launched standalone (FAB → inspection picker) or from a
/// checklist row (`inspectionId` + `inspectionResultId` pre-filled).
class ViolationFormScreen extends ConsumerStatefulWidget {
  const ViolationFormScreen({
    super.key,
    this.initialInspectionId,
    this.initialInspectionResultId,
    this.initialTitle,
    this.initialDescription,
  });

  final int? initialInspectionId;
  final int? initialInspectionResultId;
  final String? initialTitle;
  final String? initialDescription;

  @override
  ConsumerState<ViolationFormScreen> createState() =>
      _ViolationFormScreenState();
}

class _ViolationFormScreenState extends ConsumerState<ViolationFormScreen> {
  final TextEditingController _title = TextEditingController();
  final TextEditingController _description = TextEditingController();
  String _severity = 'moderate';
  String _status = 'open';
  DateTime? _deadline;
  int? _selectedInspectionId;
  int? _selectedResultId;

  final ImagePicker _picker = ImagePicker();
  final List<XFile> _pendingEvidence = [];

  bool _submitting = false;
  String? _titleError;
  String? _descriptionError;
  String? _inspectionError;

  @override
  void initState() {
    super.initState();
    _title.text = widget.initialTitle ?? '';
    _description.text = widget.initialDescription ?? '';
    _selectedInspectionId = widget.initialInspectionId;
    _selectedResultId = widget.initialInspectionResultId;
  }

  @override
  void dispose() {
    _title.dispose();
    _description.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final ColorScheme scheme = Theme.of(context).colorScheme;
    final AsyncValue<ViolationOptions> optionsAsync = ref.watch(
      violationOptionsProvider,
    );

    return Scaffold(
      backgroundColor: scheme.surface,
      appBar: AppBar(title: const Text('Record violation'), centerTitle: false),
      body: optionsAsync.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (error, _) => _OptionsError(
          message: error is ApiException
              ? error.message
              : 'Could not load inspections.',
          onRetry: () => ref.invalidate(violationOptionsProvider),
        ),
        data: (options) {
          // If initialInspectionId not in options, keep it anyway (from checklist bundle).
          final List<ViolationInspectionOption> inspections =
              options.inspections;

          final bool hasNoInspections =
              inspections.isEmpty && widget.initialInspectionId == null;

          // When coming from checklist, we may not have the inspection in options list
          // (options limits to 100 recent). Fall back to using the passed id directly.
          final bool hasInitialInspections = inspections.isNotEmpty;

          return SingleChildScrollView(
            padding: const EdgeInsets.fromLTRB(
              AppSpacing.lg,
              AppSpacing.md,
              AppSpacing.lg,
              AppSpacing.xxl,
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                if (widget.initialInspectionId == null)
                  AppCard(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'Inspection',
                          style: Theme.of(context).textTheme.titleSmall
                              ?.copyWith(fontWeight: FontWeight.w700),
                        ),
                        const SizedBox(height: AppSpacing.sm),
                        if (!hasInitialInspections)
                          Text(
                            'No inspections available. File from the checklist instead.',
                            style: Theme.of(context).textTheme.bodySmall
                                ?.copyWith(color: scheme.onSurfaceVariant),
                          )
                        else
                          DropdownButtonFormField<int>(
                            initialValue: _selectedInspectionId,
                            decoration: InputDecoration(
                              hintText: 'Select inspection',
                              border: OutlineInputBorder(
                                borderRadius: BorderRadius.circular(
                                  AppRadius.md,
                                ),
                              ),
                              errorText: _inspectionError,
                            ),
                            items: inspections
                                .map(
                                  (ins) => DropdownMenuItem<int>(
                                    value: ins.id,
                                    child: Text(
                                      '${ins.establishmentName ?? 'Inspection #${ins.id}'} · ${ins.status}',
                                      overflow: TextOverflow.ellipsis,
                                    ),
                                  ),
                                )
                                .toList(),
                            onChanged: (v) => setState(() {
                              _selectedInspectionId = v;
                              _selectedResultId = null;
                              _inspectionError = null;
                            }),
                          ),
                        if (_selectedInspectionId != null) ...[
                          const SizedBox(height: AppSpacing.sm),
                          _ResultPicker(
                            inspections: inspections,
                            selectedInspectionId: _selectedInspectionId!,
                            selectedResultId: _selectedResultId,
                            onChanged: (v) =>
                                setState(() => _selectedResultId = v),
                          ),
                        ],
                      ],
                    ),
                  )
                else
                  AppCard(
                    color: scheme.primaryContainer,
                    borderColor: scheme.primaryContainer,
                    child: Row(
                      children: [
                        Icon(
                          Icons.link_rounded,
                          color: scheme.onPrimaryContainer,
                        ),
                        const SizedBox(width: AppSpacing.sm),
                        Expanded(
                          child: Text(
                            'Linked to inspection #${widget.initialInspectionId}'
                            '${widget.initialInspectionResultId != null ? ' · item #${widget.initialInspectionResultId}' : ''}',
                            style: Theme.of(context).textTheme.bodyMedium
                                ?.copyWith(
                                  color: scheme.onPrimaryContainer,
                                  fontWeight: FontWeight.w600,
                                ),
                          ),
                        ),
                      ],
                    ),
                  ),
                const SizedBox(height: AppSpacing.lg),
                AppCard(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        'Violation details',
                        style: Theme.of(context).textTheme.titleSmall?.copyWith(
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                      const SizedBox(height: AppSpacing.md),
                      TextField(
                        controller: _title,
                        decoration: InputDecoration(
                          labelText: 'Title *',
                          hintText: 'e.g. Improper food storage',
                          border: OutlineInputBorder(
                            borderRadius: BorderRadius.circular(AppRadius.md),
                          ),
                          errorText: _titleError,
                        ),
                        onChanged: (_) {
                          if (_titleError != null) {
                            setState(() => _titleError = null);
                          }
                        },
                      ),
                      const SizedBox(height: AppSpacing.md),
                      TextField(
                        controller: _description,
                        maxLines: 4,
                        minLines: 3,
                        decoration: InputDecoration(
                          labelText: 'Description *',
                          hintText: 'Describe the violation in detail',
                          border: OutlineInputBorder(
                            borderRadius: BorderRadius.circular(AppRadius.md),
                          ),
                          errorText: _descriptionError,
                        ),
                        onChanged: (_) {
                          if (_descriptionError != null) {
                            setState(() => _descriptionError = null);
                          }
                        },
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: AppSpacing.lg),
                AppCard(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        'Classification',
                        style: Theme.of(context).textTheme.titleSmall?.copyWith(
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                      const SizedBox(height: AppSpacing.md),
                      Text(
                        'Severity *',
                        style: Theme.of(context).textTheme.labelMedium
                            ?.copyWith(
                              color: scheme.onSurfaceVariant,
                              fontWeight: FontWeight.w600,
                            ),
                      ),
                      const SizedBox(height: AppSpacing.sm),
                      _SeveritySegment(
                        selected: _severity,
                        onChanged: (v) => setState(() => _severity = v),
                      ),
                      const SizedBox(height: AppSpacing.md),
                      Text(
                        'Status *',
                        style: Theme.of(context).textTheme.labelMedium
                            ?.copyWith(
                              color: scheme.onSurfaceVariant,
                              fontWeight: FontWeight.w600,
                            ),
                      ),
                      const SizedBox(height: AppSpacing.sm),
                      _StatusSegment(
                        selected: _status,
                        onChanged: (v) => setState(() => _status = v),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: AppSpacing.lg),
                AppCard(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        'Correction deadline',
                        style: Theme.of(context).textTheme.titleSmall?.copyWith(
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                      const SizedBox(height: AppSpacing.sm),
                      Text(
                        'Applicant has 7 days to comply after notice (advisory).',
                        style: Theme.of(context).textTheme.bodySmall?.copyWith(
                          color: scheme.onSurfaceVariant,
                        ),
                      ),
                      const SizedBox(height: AppSpacing.md),
                      OutlinedButton.icon(
                        onPressed: () async {
                          final DateTime now = DateTime.now();
                          final DateTime? picked = await showDatePicker(
                            context: context,
                            initialDate:
                                _deadline ?? now.add(const Duration(days: 7)),
                            firstDate: now,
                            lastDate: now.add(const Duration(days: 365)),
                          );
                          if (picked != null) {
                            setState(() => _deadline = picked);
                          }
                        },
                        icon: const Icon(Icons.event_outlined, size: 18),
                        label: Text(
                          _deadline == null
                              ? 'Pick deadline (optional)'
                              : 'Deadline: ${_deadline!.toIso8601String().split('T').first}',
                        ),
                      ),
                      if (_deadline != null)
                        TextButton(
                          onPressed: () => setState(() => _deadline = null),
                          child: const Text('Clear deadline'),
                        ),
                    ],
                  ),
                ),
                const SizedBox(height: AppSpacing.lg),
                AppCard(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        'Photo evidence',
                        style: Theme.of(context).textTheme.titleSmall?.copyWith(
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                      const SizedBox(height: AppSpacing.sm),
                      Text(
                        'Up to 10MB per file. Attached after the violation is created.',
                        style: Theme.of(context).textTheme.bodySmall?.copyWith(
                          color: scheme.onSurfaceVariant,
                        ),
                      ),
                      const SizedBox(height: AppSpacing.md),
                      if (_pendingEvidence.isNotEmpty)
                        SizedBox(
                          height: 72,
                          child: ListView.separated(
                            scrollDirection: Axis.horizontal,
                            itemCount: _pendingEvidence.length,
                            separatorBuilder: (_, _) =>
                                const SizedBox(width: AppSpacing.sm),
                            itemBuilder: (context, idx) {
                              final XFile f = _pendingEvidence[idx];
                              return Stack(
                                children: [
                                  ClipRRect(
                                    borderRadius: BorderRadius.circular(
                                      AppRadius.md,
                                    ),
                                    child: Image.file(
                                      File(f.path),
                                      width: 72,
                                      height: 72,
                                      fit: BoxFit.cover,
                                      errorBuilder: (_, _, _) => Container(
                                        width: 72,
                                        height: 72,
                                        color: scheme.surfaceContainerHigh,
                                        child: const Icon(
                                          Icons.broken_image_outlined,
                                        ),
                                      ),
                                    ),
                                  ),
                                  Positioned(
                                    top: 2,
                                    right: 2,
                                    child: InkWell(
                                      onTap: () => setState(
                                        () => _pendingEvidence.removeAt(idx),
                                      ),
                                      child: Container(
                                        decoration: const BoxDecoration(
                                          color: Colors.black54,
                                          shape: BoxShape.circle,
                                        ),
                                        padding: const EdgeInsets.all(4),
                                        child: const Icon(
                                          Icons.close_rounded,
                                          size: 14,
                                          color: Colors.white,
                                        ),
                                      ),
                                    ),
                                  ),
                                ],
                              );
                            },
                          ),
                        ),
                      if (_pendingEvidence.isNotEmpty)
                        const SizedBox(height: AppSpacing.md),
                      Row(
                        children: [
                          OutlinedButton.icon(
                            onPressed: _pickCamera,
                            icon: const Icon(
                              Icons.photo_camera_outlined,
                              size: 18,
                            ),
                            label: const Text('Camera'),
                          ),
                          const SizedBox(width: AppSpacing.sm),
                          OutlinedButton.icon(
                            onPressed: _pickGallery,
                            icon: const Icon(
                              Icons.photo_library_outlined,
                              size: 18,
                            ),
                            label: const Text('Gallery'),
                          ),
                          const Spacer(),
                          if (_pendingEvidence.isNotEmpty)
                            Text(
                              '${_pendingEvidence.length} pending',
                              style: Theme.of(context).textTheme.labelSmall
                                  ?.copyWith(color: scheme.onSurfaceVariant),
                            ),
                        ],
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: AppSpacing.xl),
                if (hasNoInspections)
                  Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      Opacity(
                        opacity: 0.5,
                        child: IgnorePointer(
                          child: AppButton(
                            onPressed: () {},
                            label: 'Create violation',
                            icon: Icons.gavel_rounded,
                          ),
                        ),
                      ),
                      const SizedBox(height: AppSpacing.sm),
                      Text(
                        'No inspections available. Create a violation from the checklist (non-compliant item) or wait for an assignment.',
                        textAlign: TextAlign.center,
                        style: Theme.of(context).textTheme.bodySmall?.copyWith(
                          color: scheme.onSurfaceVariant,
                        ),
                      ),
                    ],
                  )
                else
                  AppButton(
                    onPressed: _submitting ? () {} : _submit,
                    label: 'Create violation',
                    icon: Icons.gavel_rounded,
                    loading: _submitting,
                  ),
              ],
            ),
          );
        },
      ),
    );
  }

  Future<void> _pickCamera() async {
    final PermissionStatus perm = await Permission.camera.request();
    if (perm.isDenied || perm.isPermanentlyDenied) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Camera permission required.')),
      );
      return;
    }
    final XFile? f = await _picker.pickImage(
      source: ImageSource.camera,
      imageQuality: 80,
      maxWidth: 1920,
    );
    if (f == null) return;
    setState(() => _pendingEvidence.add(f));
  }

  Future<void> _pickGallery() async {
    final List<XFile> files = await _picker.pickMultiImage(
      imageQuality: 80,
      maxWidth: 1920,
    );
    if (files.isEmpty) return;
    final List<XFile> valid = [];
    for (final f in files) {
      if (await f.length() > 10 * 1024 * 1024) {
        if (!mounted) continue;
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('${f.name} exceeds 10MB and was skipped.')),
        );
        continue;
      }
      valid.add(f);
    }
    setState(() => _pendingEvidence.addAll(valid));
  }

  Future<void> _submit() async {
    final String title = _title.text.trim();
    final String desc = _description.text.trim();
    final int? inspectionId =
        _selectedInspectionId ?? widget.initialInspectionId;

    setState(() {
      _titleError = title.isEmpty ? 'Title is required' : null;
      _descriptionError = desc.isEmpty ? 'Description is required' : null;
      _inspectionError = inspectionId == null ? 'Select an inspection' : null;
    });
    if (title.isEmpty || desc.isEmpty || inspectionId == null) return;

    setState(() => _submitting = true);
    try {
      final ViolationsDataSource ds = ref.read(violationsDataSourceProvider);
      final Violation created = await ds.create(
        inspectionId: inspectionId,
        inspectionResultId:
            _selectedResultId ?? widget.initialInspectionResultId,
        title: title,
        description: desc,
        severity: _severity,
        status: _status,
        correctionDeadline: _deadline?.toIso8601String().split('T').first,
      );

      if (_pendingEvidence.isNotEmpty) {
        try {
          await ds.uploadEvidence(
            violationId: '${created.id}',
            evidenceType: 'initial',
            files: List<XFile>.from(_pendingEvidence),
          );
        } on ApiException catch (e) {
          if (mounted) {
            ScaffoldMessenger.of(context).showSnackBar(
              SnackBar(
                content: Text(
                  'Violation created, but evidence failed: ${e.message}',
                ),
              ),
            );
          }
        }
      }

      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Violation "${created.title}" created.')),
      );
      // Invalidate lists.
      ref.invalidate(violationsPageProvider);
      ref.invalidate(violationOptionsProvider);
      ref.invalidate(pendingSyncCountProvider);
      if (mounted) Navigator.of(context).pop(true);
    } on ApiException catch (e) {
      if (e.isNetwork) {
        await ref
            .read(syncServiceProvider)
            .enqueueViolationCreate(
              inspectionId: inspectionId,
              inspectionResultId:
                  _selectedResultId ?? widget.initialInspectionResultId,
              title: title,
              description: desc,
              severity: _severity,
              status: _status,
              correctionDeadline: _deadline?.toIso8601String().split('T').first,
              evidenceFiles: List<XFile>.from(_pendingEvidence),
            );
        ref.invalidate(pendingSyncCountProvider);
        if (!mounted) return;
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('You are offline — violation will sync when online.'),
          ),
        );
        if (mounted) Navigator.of(context).pop(true);
      } else {
        if (!mounted) return;
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(e.message)));
      }
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }
}

class _SeveritySegment extends StatelessWidget {
  const _SeveritySegment({required this.selected, required this.onChanged});

  final String selected;
  final ValueChanged<String> onChanged;

  @override
  Widget build(BuildContext context) {
    return SegmentedButton<String>(
      segments: const [
        ButtonSegment(
          value: 'minor',
          label: Text('Minor'),
          icon: Icon(Icons.info_outline_rounded, size: 16),
        ),
        ButtonSegment(
          value: 'moderate',
          label: Text('Moderate'),
          icon: Icon(Icons.warning_amber_rounded, size: 16),
        ),
        ButtonSegment(
          value: 'major',
          label: Text('Major'),
          icon: Icon(Icons.priority_high_rounded, size: 16),
        ),
      ],
      selected: {selected},
      onSelectionChanged: (s) => onChanged(s.first),
      showSelectedIcon: false,
    );
  }
}

class _StatusSegment extends StatelessWidget {
  const _StatusSegment({required this.selected, required this.onChanged});

  final String selected;
  final ValueChanged<String> onChanged;

  @override
  Widget build(BuildContext context) {
    return SegmentedButton<String>(
      segments: const [
        ButtonSegment(
          value: 'open',
          label: Text('Open'),
          icon: Icon(Icons.gavel_outlined, size: 16),
        ),
        ButtonSegment(
          value: 'under_review',
          label: Text('Under review'),
          icon: Icon(Icons.rate_review_outlined, size: 16),
        ),
        ButtonSegment(
          value: 'resolved',
          label: Text('Resolved'),
          icon: Icon(Icons.check_circle_outline_rounded, size: 16),
        ),
      ],
      selected: {selected},
      onSelectionChanged: (s) => onChanged(s.first),
      showSelectedIcon: false,
    );
  }
}

class _ResultPicker extends StatelessWidget {
  const _ResultPicker({
    required this.inspections,
    required this.selectedInspectionId,
    required this.selectedResultId,
    required this.onChanged,
  });

  final List<ViolationInspectionOption> inspections;
  final int selectedInspectionId;
  final int? selectedResultId;
  final ValueChanged<int?> onChanged;

  @override
  Widget build(BuildContext context) {
    final ViolationInspectionOption? ins = inspections
        .where((i) => i.id == selectedInspectionId)
        .firstOrNull;
    if (ins == null || ins.results.isEmpty) {
      return Text(
        'No checklist results for this inspection.',
        style: Theme.of(context).textTheme.bodySmall?.copyWith(
          color: Theme.of(context).colorScheme.onSurfaceVariant,
          fontStyle: FontStyle.italic,
        ),
      );
    }
    return DropdownButtonFormField<int>(
      initialValue: selectedResultId,
      decoration: InputDecoration(
        labelText: 'Link to checklist item (optional)',
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(AppRadius.md),
        ),
      ),
      items: [
        const DropdownMenuItem<int>(value: null, child: Text('— Not linked —')),
        ...ins.results.map(
          (r) => DropdownMenuItem<int>(
            value: r.id,
            child: Text(
              r.itemTitle ??
                  'Item #${r.checklistItemId} · ${r.complianceStatus ?? '—'}',
              overflow: TextOverflow.ellipsis,
            ),
          ),
        ),
      ],
      onChanged: onChanged,
    );
  }
}

class _OptionsError extends StatelessWidget {
  const _OptionsError({required this.message, required this.onRetry});

  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(AppSpacing.xl),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(Icons.error_outline_rounded, size: 48),
            const SizedBox(height: AppSpacing.md),
            Text(message, textAlign: TextAlign.center),
            const SizedBox(height: AppSpacing.lg),
            OutlinedButton.icon(
              onPressed: onRetry,
              icon: const Icon(Icons.refresh_rounded),
              label: const Text('Retry'),
            ),
          ],
        ),
      ),
    );
  }
}
