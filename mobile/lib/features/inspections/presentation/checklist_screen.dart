import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:image_picker/image_picker.dart';
import 'package:permission_handler/permission_handler.dart';

import 'package:go_router/go_router.dart';

import '../../../core/constants/app_constants.dart';
import '../../../core/errors/api_exception.dart';
import '../../../core/providers/app_providers.dart';
import '../../../router/app_routes.dart';
import '../../../widgets/badges/app_badge.dart';
import '../../../widgets/buttons/app_button.dart';
import '../../../widgets/cards/app_card.dart';
import '../../../widgets/feedback/app_error_state.dart';
import '../models/checklist.dart';
import '../providers/assignments_providers.dart';

/// Checklist execution screen for an assignment.
///
/// `GET /v1/inspection-assignments/{id}/checklist` → grouped checklists with
/// `compliant | non_compliant | needs_correction` radios, remarks, and
/// per-item photo evidence (`evidence_files` multipart). Saving uses
/// `POST /{id}/checklist` (`ChecklistDataSource.save`).
///
/// Validation: every `is_required` item must have a compliance status before
/// save is enabled. Read-only when the assignment is `submitted|cancelled`.
class ChecklistScreen extends ConsumerStatefulWidget {
  const ChecklistScreen({
    super.key,
    required this.assignmentId,
    this.readOnly = false,
  });

  final String assignmentId;
  final bool readOnly;

  @override
  ConsumerState<ChecklistScreen> createState() => _ChecklistScreenState();
}

class _ChecklistScreenState extends ConsumerState<ChecklistScreen> {
  final ImagePicker _picker = ImagePicker();

  // Local draft state: itemId -> selected status.
  final Map<int, String> _statusByItemId = {};
  final Map<int, TextEditingController> _remarksByItemId = {};
  final Map<int, List<XFile>> _pendingByItemId = {};

  bool _saving = false;
  bool _initialized = false;

  @override
  void didUpdateWidget(covariant ChecklistScreen oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.assignmentId != widget.assignmentId) {
      for (final c in _remarksByItemId.values) {
        c.dispose();
      }
      _statusByItemId.clear();
      _remarksByItemId.clear();
      _pendingByItemId.clear();
      _initialized = false;
    }
  }

  @override
  void dispose() {
    for (final c in _remarksByItemId.values) {
      c.dispose();
    }
    super.dispose();
  }

  void _ensureInitialized(ChecklistBundle bundle) {
    if (_initialized) return;
    final Map<int, InspectionResult> byId = bundle.resultsByItemId;
    for (final item in bundle.allItems) {
      final InspectionResult? existing = byId[item.id];
      if (existing != null && existing.complianceStatus.isNotEmpty) {
        _statusByItemId[item.id] = existing.complianceStatus;
      }
      final TextEditingController ctrl = TextEditingController(
        text: existing?.remarks ?? '',
      );
      _remarksByItemId[item.id] = ctrl;
      _pendingByItemId[item.id] = [];
    }
    _initialized = true;
  }

  @override
  Widget build(BuildContext context) {
    final ColorScheme scheme = Theme.of(context).colorScheme;
    final AsyncValue<ChecklistBundle> bundleAsync = ref.watch(
      checklistBundleProvider(widget.assignmentId),
    );

    return Scaffold(
      backgroundColor: scheme.surface,
      appBar: AppBar(
        title: const Text('Checklist'),
        centerTitle: false,
        actions: [
          bundleAsync.when(
            data: (b) => Padding(
              padding: const EdgeInsets.only(right: AppSpacing.md),
              child: Center(
                child: Text(
                  '${b.results.length}/${b.allItems.length} saved',
                  style: Theme.of(context).textTheme.bodySmall?.copyWith(
                    color: scheme.onSurfaceVariant,
                  ),
                ),
              ),
            ),
            loading: () => const SizedBox.shrink(),
            error: (_, _) => const SizedBox.shrink(),
          ),
        ],
      ),
      body: bundleAsync.when(
        loading: () => const Center(child: AppSpinner(size: 28)),
        error: (error, _) => AppErrorState(
          message: error is ApiException
              ? error.message
              : 'Could not load checklist.',
          onRetry: () =>
              ref.invalidate(checklistBundleProvider(widget.assignmentId)),
        ),
        data: (bundle) {
          if (bundle.allItems.isEmpty) {
            return const Center(
              child: Padding(
                padding: EdgeInsets.all(AppSpacing.xl),
                child: Text('No checklist items for this category.'),
              ),
            );
          }
          _ensureInitialized(bundle);

          final bool readOnly = widget.readOnly;
          final List<ChecklistItem> requiredItems = bundle.allItems
              .where((it) => it.isRequired)
              .toList();
          final int answeredRequired = requiredItems
              .where((it) => (_statusByItemId[it.id] ?? '').isNotEmpty)
              .length;
          final bool allRequiredAnswered =
              answeredRequired == requiredItems.length;

          return Column(
            children: [
              if (!readOnly)
                Container(
                  width: double.infinity,
                  color: scheme.surfaceContainerHigh,
                  padding: const EdgeInsets.symmetric(
                    horizontal: AppSpacing.lg,
                    vertical: AppSpacing.sm,
                  ),
                  child: Row(
                    children: [
                      Icon(
                        Icons.info_outline_rounded,
                        size: 16,
                        color: scheme.onSurfaceVariant,
                      ),
                      const SizedBox(width: AppSpacing.sm),
                      Expanded(
                        child: Text(
                          'Required: $answeredRequired/${requiredItems.length} · Tap a status for each item, add remarks and photos.',
                          style: Theme.of(context).textTheme.bodySmall
                              ?.copyWith(color: scheme.onSurfaceVariant),
                        ),
                      ),
                    ],
                  ),
                ),
              Expanded(
                child: RefreshIndicator(
                  onRefresh: () async {
                    ref.invalidate(
                      checklistBundleProvider(widget.assignmentId),
                    );
                    await Future<void>.delayed(
                      const Duration(milliseconds: 350),
                    );
                    setState(() => _initialized = false);
                  },
                  child: ListView.builder(
                    physics: const AlwaysScrollableScrollPhysics(),
                    padding: const EdgeInsets.fromLTRB(
                      AppSpacing.lg,
                      AppSpacing.md,
                      AppSpacing.lg,
                      AppSpacing.xxl,
                    ),
                    itemCount: bundle.checklists.length + 1,
                    itemBuilder: (context, index) {
                      if (index == 0) {
                        final Map<String, dynamic> insp = bundle.inspection;
                        final String inspStatus =
                            insp['status'] as String? ?? '—';
                        return Padding(
                          padding: const EdgeInsets.only(bottom: AppSpacing.md),
                          child: AppCard(
                            child: Row(
                              children: [
                                Icon(
                                  Icons.assignment_turned_in_outlined,
                                  size: 18,
                                  color: scheme.primary,
                                ),
                                const SizedBox(width: AppSpacing.sm),
                                Expanded(
                                  child: Text(
                                    'Inspection #${insp['id'] ?? '—'} · $inspStatus',
                                    style: Theme.of(context).textTheme.bodySmall
                                        ?.copyWith(
                                          color: scheme.onSurfaceVariant,
                                          fontWeight: FontWeight.w600,
                                        ),
                                  ),
                                ),
                                AppBadge(
                                  label: '${bundle.allItems.length} items',
                                  variant: AppBadgeVariant.primary,
                                  compact: true,
                                ),
                              ],
                            ),
                          ),
                        );
                      }
                      final Checklist checklist = bundle.checklists[index - 1];
                      return _ChecklistSection(
                        checklist: checklist,
                        bundle: bundle,
                        statusByItemId: _statusByItemId,
                        remarksByItemId: _remarksByItemId,
                        pendingByItemId: _pendingByItemId,
                        readOnly: readOnly,
                        onStatusChanged: (itemId, status) {
                          setState(() => _statusByItemId[itemId] = status);
                        },
                        onPickCamera: (itemId) => _pickCamera(itemId),
                        onPickGallery: (itemId) => _pickGallery(itemId),
                        onRemovePending: (itemId, fileIndex) {
                          setState(
                            () => _pendingByItemId[itemId]!.removeAt(fileIndex),
                          );
                        },
                      );
                    },
                  ),
                ),
              ),
              if (!readOnly)
                Container(
                  padding: const EdgeInsets.fromLTRB(
                    AppSpacing.lg,
                    AppSpacing.md,
                    AppSpacing.lg,
                    AppSpacing.lg,
                  ),
                  decoration: BoxDecoration(
                    color: scheme.surface,
                    border: Border(
                      top: BorderSide(color: scheme.outlineVariant),
                    ),
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      if (!allRequiredAnswered)
                        Padding(
                          padding: const EdgeInsets.only(bottom: AppSpacing.sm),
                          child: Text(
                            'Complete all required items before saving.',
                            textAlign: TextAlign.center,
                            style: Theme.of(context).textTheme.bodySmall
                                ?.copyWith(
                                  color: scheme.error,
                                  fontWeight: FontWeight.w600,
                                ),
                          ),
                        ),
                      AppButton(
                        onPressed: (_saving || !allRequiredAnswered)
                            ? () {}
                            : () => _save(bundle),
                        label: 'Save checklist',
                        icon: Icons.save_outlined,
                        loading: _saving,
                      ),
                    ],
                  ),
                ),
            ],
          );
        },
      ),
    );
  }

  Future<void> _pickCamera(int itemId) async {
    final PermissionStatus perm = await Permission.camera.request();
    if (perm.isDenied || perm.isPermanentlyDenied) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Camera permission is required to take photos.'),
        ),
      );
      return;
    }
    final XFile? file = await _picker.pickImage(
      source: ImageSource.camera,
      imageQuality: 80,
      maxWidth: 1920,
    );
    if (file == null) return;
    setState(() => _pendingByItemId[itemId]!.add(file));
  }

  Future<void> _pickGallery(int itemId) async {
    final List<XFile> files = await _picker.pickMultiImage(
      imageQuality: 80,
      maxWidth: 1920,
    );
    if (files.isEmpty) return;
    // Enforce 5MB per file at save time (backend max:5120); filter oversized here.
    final List<XFile> valid = [];
    for (final f in files) {
      final int bytes = await f.length();
      if (bytes > 5 * 1024 * 1024) {
        if (!mounted) continue;
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('${f.name} exceeds 5MB and was skipped.')),
        );
        continue;
      }
      valid.add(f);
    }
    if (valid.isEmpty) return;
    setState(() => _pendingByItemId[itemId]!.addAll(valid));
  }

  Future<void> _save(ChecklistBundle bundle) async {
    final List<ChecklistItem> all = bundle.allItems;
    final List<ChecklistResultInput> results = [];
    for (final item in all) {
      final String? status = _statusByItemId[item.id];
      if (status == null || status.isEmpty) {
        if (item.isRequired) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(content: Text('Required item missing: ${item.title}')),
          );
          return;
        }
        continue;
      }
      results.add(
        ChecklistResultInput(
          checklistItemId: item.id,
          complianceStatus: status,
          remarks: _remarksByItemId[item.id]?.text,
        ),
      );
    }
    if (results.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Select at least one item before saving.'),
        ),
      );
      return;
    }

    final Map<int, List<XFile>> evidence = {
      for (final e in _pendingByItemId.entries)
        if (e.value.isNotEmpty) e.key: List<XFile>.from(e.value),
    };

    setState(() => _saving = true);
    try {
      final ds = ref.read(checklistDataSourceProvider);
      final List<int> savedIds = await ds.save(
        assignmentId: widget.assignmentId,
        results: results,
        evidenceFiles: evidence,
      );
      if (!mounted) return;
      // Only clear pending evidence for items the server confirmed (P0-5).
      setState(() {
        if (savedIds.isEmpty) {
          for (final k in _pendingByItemId.keys) {
            _pendingByItemId[k]!.clear();
          }
        } else {
          for (final int id in savedIds) {
            _pendingByItemId[id]?.clear();
          }
          final Set<int> requestedIds = results
              .map((r) => r.checklistItemId)
              .toSet();
          final Set<int> savedSet = savedIds.toSet();
          if (requestedIds.difference(savedSet).isNotEmpty) {
            ScaffoldMessenger.of(context).showSnackBar(
              SnackBar(
                content: Text(
                  'Saved ${savedIds.length}/${results.length} items. '
                  'Pending evidence kept for failed items.',
                ),
              ),
            );
          }
        }
      });
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Checklist saved. Evidence merged on server.'),
          ),
        );
      }
      ref.invalidate(checklistBundleProvider(widget.assignmentId));
      ref.invalidate(pendingSyncCountProvider);
    } on ApiException catch (e) {
      if (e.isNetwork) {
        await ref
            .read(syncServiceProvider)
            .enqueueChecklistSave(
              assignmentId: widget.assignmentId,
              results: results,
              evidenceFiles: evidence,
            );
        ref.invalidate(pendingSyncCountProvider);
        if (!mounted) return;
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('You are offline — checklist will sync when online.'),
          ),
        );
      } else {
        if (!mounted) return;
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(e.message)));
      }
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }
}

class _ChecklistSection extends StatelessWidget {
  const _ChecklistSection({
    required this.checklist,
    required this.bundle,
    required this.statusByItemId,
    required this.remarksByItemId,
    required this.pendingByItemId,
    required this.readOnly,
    required this.onStatusChanged,
    required this.onPickCamera,
    required this.onPickGallery,
    required this.onRemovePending,
  });

  final Checklist checklist;
  final ChecklistBundle bundle;
  final Map<int, String> statusByItemId;
  final Map<int, TextEditingController> remarksByItemId;
  final Map<int, List<XFile>> pendingByItemId;
  final bool readOnly;
  final void Function(int itemId, String status) onStatusChanged;
  final Future<void> Function(int itemId) onPickCamera;
  final Future<void> Function(int itemId) onPickGallery;
  final void Function(int itemId, int fileIndex) onRemovePending;

  @override
  Widget build(BuildContext context) {
    final ColorScheme scheme = Theme.of(context).colorScheme;
    return Padding(
      padding: const EdgeInsets.only(bottom: AppSpacing.lg),
      child: AppCard(
        padding: const EdgeInsets.all(AppSpacing.lg),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Container(
                  width: 32,
                  height: 32,
                  decoration: BoxDecoration(
                    color: scheme.primaryContainer,
                    borderRadius: BorderRadius.circular(AppRadius.sm),
                  ),
                  child: Icon(
                    Icons.list_alt_rounded,
                    size: 18,
                    color: scheme.onPrimaryContainer,
                  ),
                ),
                const SizedBox(width: AppSpacing.sm),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        checklist.name,
                        style: Theme.of(context).textTheme.titleSmall?.copyWith(
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                      if (checklist.description != null &&
                          checklist.description!.isNotEmpty)
                        Text(
                          checklist.description!,
                          style: Theme.of(context).textTheme.bodySmall
                              ?.copyWith(color: scheme.onSurfaceVariant),
                        ),
                    ],
                  ),
                ),
                AppBadge(
                  label: checklist.category,
                  variant: AppBadgeVariant.outline,
                  compact: true,
                ),
              ],
            ),
            const SizedBox(height: AppSpacing.md),
            const Divider(height: 1),
            const SizedBox(height: AppSpacing.md),
            ...checklist.items.map((item) {
              final String? selected = statusByItemId[item.id];
              final TextEditingController remarksCtrl =
                  remarksByItemId[item.id]!;
              final List<XFile> pending = pendingByItemId[item.id] ?? const [];
              final InspectionResult? existing =
                  bundle.resultsByItemId[item.id];
              final List<String> serverEvidence =
                  existing?.evidencePaths ?? const [];

              return Padding(
                padding: const EdgeInsets.only(bottom: AppSpacing.lg),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Expanded(
                          child: Text(
                            item.title,
                            style: Theme.of(context).textTheme.bodyMedium
                                ?.copyWith(fontWeight: FontWeight.w600),
                          ),
                        ),
                        const SizedBox(width: AppSpacing.sm),
                        if (item.isRequired)
                          AppBadge(
                            label: 'Required',
                            variant: AppBadgeVariant.danger,
                            compact: true,
                          )
                        else
                          AppBadge(
                            label: 'Optional',
                            variant: AppBadgeVariant.neutral,
                            compact: true,
                          ),
                      ],
                    ),
                    if (item.description != null &&
                        item.description!.isNotEmpty) ...[
                      const SizedBox(height: AppSpacing.xs),
                      Text(
                        item.description!,
                        style: Theme.of(context).textTheme.bodySmall?.copyWith(
                          color: scheme.onSurfaceVariant,
                        ),
                      ),
                    ],
                    const SizedBox(height: AppSpacing.sm),
                    if (readOnly)
                      AppBadge(
                        label: selected != null && selected.isNotEmpty
                            ? _labelFor(selected)
                            : 'Not answered',
                        variant: _variantFor(selected),
                        icon: _iconFor(selected),
                        compact: true,
                      )
                    else
                      _StatusSegment(
                        selected: selected,
                        isRequired: item.isRequired,
                        onChanged: (v) => onStatusChanged(item.id, v),
                      ),
                    const SizedBox(height: AppSpacing.sm),
                    if (readOnly)
                      if ((remarksCtrl.text).isNotEmpty)
                        Text(
                          remarksCtrl.text,
                          style: Theme.of(context).textTheme.bodySmall,
                        )
                      else
                        Text(
                          'No remarks.',
                          style: Theme.of(context).textTheme.bodySmall
                              ?.copyWith(
                                color: scheme.onSurfaceVariant,
                                fontStyle: FontStyle.italic,
                              ),
                        )
                    else
                      TextField(
                        controller: remarksCtrl,
                        maxLines: 2,
                        minLines: 1,
                        decoration: InputDecoration(
                          hintText: 'Remarks (optional)',
                          border: OutlineInputBorder(
                            borderRadius: BorderRadius.circular(AppRadius.md),
                          ),
                          contentPadding: const EdgeInsets.all(AppSpacing.md),
                          isDense: true,
                        ),
                      ),
                    const SizedBox(height: AppSpacing.sm),
                    if (serverEvidence.isNotEmpty)
                      Wrap(
                        spacing: AppSpacing.sm,
                        runSpacing: AppSpacing.sm,
                        children: serverEvidence
                            .map(
                              (p) => Chip(
                                label: Text(
                                  p.split('/').last,
                                  style: Theme.of(context).textTheme.labelSmall,
                                ),
                                avatar: const Icon(
                                  Icons.image_outlined,
                                  size: 16,
                                ),
                                visualDensity: VisualDensity.compact,
                              ),
                            )
                            .toList(),
                      ),
                    if (pending.isNotEmpty) ...[
                      const SizedBox(height: AppSpacing.sm),
                      SizedBox(
                        height: 72,
                        child: ListView.separated(
                          scrollDirection: Axis.horizontal,
                          itemCount: pending.length,
                          separatorBuilder: (_, _) =>
                              const SizedBox(width: AppSpacing.sm),
                          itemBuilder: (context, idx) {
                            final XFile f = pending[idx];
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
                                if (!readOnly)
                                  Positioned(
                                    top: 2,
                                    right: 2,
                                    child: InkWell(
                                      onTap: () =>
                                          onRemovePending(item.id, idx),
                                      child: Container(
                                        decoration: BoxDecoration(
                                          color: Colors.black54,
                                          borderRadius: BorderRadius.circular(
                                            12,
                                          ),
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
                    ],
                    if (!readOnly) ...[
                      const SizedBox(height: AppSpacing.sm),
                      Row(
                        children: [
                          OutlinedButton.icon(
                            onPressed: () => onPickCamera(item.id),
                            icon: const Icon(
                              Icons.photo_camera_outlined,
                              size: 18,
                            ),
                            label: const Text('Camera'),
                          ),
                          const SizedBox(width: AppSpacing.sm),
                          OutlinedButton.icon(
                            onPressed: () => onPickGallery(item.id),
                            icon: const Icon(
                              Icons.photo_library_outlined,
                              size: 18,
                            ),
                            label: const Text('Gallery'),
                          ),
                          const Spacer(),
                          if (pending.isNotEmpty)
                            Text(
                              '${pending.length} pending',
                              style: Theme.of(context).textTheme.labelSmall
                                  ?.copyWith(color: scheme.onSurfaceVariant),
                            ),
                        ],
                      ),
                    ],
                    if (!readOnly && selected == 'non_compliant')
                      Padding(
                        padding: const EdgeInsets.only(top: AppSpacing.sm),
                        child: Align(
                          alignment: Alignment.centerLeft,
                          child: TextButton.icon(
                            onPressed: () {
                              final int? inspectionId =
                                  (bundle.inspection['id'] as num?)?.toInt();
                              if (inspectionId == null) {
                                ScaffoldMessenger.of(context).showSnackBar(
                                  const SnackBar(
                                    content: Text(
                                      'Inspection not yet available. Save checklist first.',
                                    ),
                                  ),
                                );
                                return;
                              }
                              context.push(
                                AppRoutes.violationNew,
                                extra: {
                                  'inspectionId': inspectionId,
                                  'inspectionResultId': existing?.id,
                                  'title': item.title,
                                  'description': remarksCtrl.text.isNotEmpty
                                      ? remarksCtrl.text
                                      : item.description,
                                },
                              );
                            },
                            icon: const Icon(Icons.gavel_rounded, size: 16),
                            label: const Text('Record violation for this item'),
                          ),
                        ),
                      ),
                    if (item != checklist.items.last) ...[
                      const SizedBox(height: AppSpacing.lg),
                      Divider(color: scheme.outlineVariant, height: 1),
                    ],
                  ],
                ),
              );
            }),
          ],
        ),
      ),
    );
  }

  String _labelFor(String? v) => switch (v) {
    'compliant' => 'Compliant',
    'non_compliant' => 'Non-compliant',
    'needs_correction' => 'Needs correction',
    _ => '—',
  };

  AppBadgeVariant _variantFor(String? v) => switch (v) {
    'compliant' => AppBadgeVariant.success,
    'non_compliant' => AppBadgeVariant.danger,
    'needs_correction' => AppBadgeVariant.warning,
    _ => AppBadgeVariant.neutral,
  };

  IconData? _iconFor(String? v) => switch (v) {
    'compliant' => Icons.check_circle_outline_rounded,
    'non_compliant' => Icons.cancel_outlined,
    'needs_correction' => Icons.warning_amber_rounded,
    _ => null,
  };
}

class _StatusSegment extends StatelessWidget {
  const _StatusSegment({
    required this.selected,
    required this.onChanged,
    this.isRequired = false,
  });

  final String? selected;
  final ValueChanged<String> onChanged;
  final bool isRequired;

  @override
  Widget build(BuildContext context) {
    const List<({String value, String label, IconData icon})> options = [
      (value: 'compliant', label: 'Compliant', icon: Icons.check_rounded),
      (
        value: 'non_compliant',
        label: 'Non-compliant',
        icon: Icons.close_rounded,
      ),
      (
        value: 'needs_correction',
        label: 'Needs correction',
        icon: Icons.build_outlined,
      ),
    ];

    return SegmentedButton<String>(
      segments: options
          .map(
            (o) => ButtonSegment<String>(
              value: o.value,
              label: Text(o.label, style: const TextStyle(fontSize: 12)),
              icon: Icon(o.icon, size: 16),
            ),
          )
          .toList(),
      selected: selected == null || selected!.isEmpty ? const {} : {selected!},
      onSelectionChanged: (Set<String> next) {
        if (next.isEmpty) {
          // Required items cannot be deselected back to empty — ignore.
          if (!isRequired) onChanged('');
          return;
        }
        onChanged(next.first);
      },
      emptySelectionAllowed: true,
      multiSelectionEnabled: false,
      showSelectedIcon: false,
      style: ButtonStyle(
        visualDensity: VisualDensity.compact,
        tapTargetSize: MaterialTapTargetSize.shrinkWrap,
      ),
    );
  }
}
