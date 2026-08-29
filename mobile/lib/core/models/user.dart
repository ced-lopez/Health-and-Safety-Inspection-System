/// Domain models for the authenticated inspector user.
library;

/// System role, matching the Laravel RoleSeeder slugs.
class Role {
  const Role({required this.id, required this.slug, required this.name});

  factory Role.fromJson(Map<String, dynamic> json) => Role(
    id: (json['id'] as num).toInt(),
    slug: json['slug'] as String? ?? '',
    name: json['name'] as String? ?? '',
  );

  final int id;
  final String slug;
  final String name;
}

/// Authenticated user returned by `GET /v1/auth/me` (UserResource).
class User {
  const User({
    required this.id,
    required this.name,
    required this.email,
    this.phone,
    this.address,
    this.age,
    this.isActive = true,
    this.role,
  });

  factory User.fromJson(Map<String, dynamic> json) => User(
    id: (json['id'] as num).toInt(),
    name: json['name'] as String? ?? '',
    email: json['email'] as String? ?? '',
    phone: json['phone'] as String?,
    address: json['address'] as String?,
    age: (json['age'] as num?)?.toInt(),
    isActive: json['is_active'] as bool? ?? true,
    role: json['role'] is Map<String, dynamic>
        ? Role.fromJson(json['role'] as Map<String, dynamic>)
        : null,
  );

  final int id;
  final String name;
  final String email;
  final String? phone;
  final String? address;
  final int? age;
  final bool isActive;
  final Role? role;

  bool get isInspector => role?.slug == 'inspector';

  Map<String, dynamic> toJson() => {
    'id': id,
    'name': name,
    'email': email,
    'phone': phone,
    'address': address,
    'age': age,
    'is_active': isActive,
    'role': role == null
        ? null
        : {'id': role!.id, 'slug': role!.slug, 'name': role!.name},
  };
}
