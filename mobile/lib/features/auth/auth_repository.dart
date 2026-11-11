abstract interface class AuthRepository {
  Future<void> signIn({required String email, required String password});

  /// Validates a saved token with Laravel. Returns false if no valid session exists.
  Future<bool> restoreSession();

  Future<Set<String>> loadPermissions();

  Future<void> signOut();
}
