import 'package:flutter/material.dart';

abstract final class AppColors {
  static const canvas = Color(0xFFF6F4EF);
  static const ink = Color(0xFF242622);
  static const mutedInk = Color(0xFF777A73);
  static const terracotta = Color(0xFFB94E35);
  static const deepTerracotta = Color(0xFF9E3D2B);
  static const line = Color(0xFFE8E5DE);
  static const field = Color(0xFFFBFAF7);
  static const white = Color(0xFFFFFFFF);
  static const error = Color(0xFFB42318);
  static const errorSurface = Color(0xFFFFF1EF);
}

abstract final class AppTheme {
  static ThemeData get light {
    final colorScheme = ColorScheme.fromSeed(
      seedColor: AppColors.terracotta,
      brightness: Brightness.light,
      surface: AppColors.canvas,
    );

    return ThemeData(
      useMaterial3: true,
      colorScheme: colorScheme.copyWith(
        primary: AppColors.terracotta,
        onPrimary: AppColors.white,
        surface: AppColors.canvas,
        onSurface: AppColors.ink,
      ),
      scaffoldBackgroundColor: AppColors.canvas,
      textTheme: const TextTheme(
        headlineMedium: TextStyle(
          color: AppColors.ink,
          fontSize: 30,
          fontWeight: FontWeight.w700,
          height: 1.12,
          letterSpacing: -0.8,
        ),
        titleMedium: TextStyle(
          color: AppColors.ink,
          fontSize: 16,
          fontWeight: FontWeight.w600,
        ),
        bodyMedium: TextStyle(
          color: AppColors.mutedInk,
          fontSize: 14,
          height: 1.45,
        ),
        labelMedium: TextStyle(
          color: AppColors.mutedInk,
          fontSize: 12,
          fontWeight: FontWeight.w600,
          letterSpacing: 0.7,
        ),
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: AppColors.field,
        contentPadding: const EdgeInsets.symmetric(
          horizontal: 16,
          vertical: 17,
        ),
        prefixIconColor: AppColors.mutedInk,
        suffixIconColor: AppColors.mutedInk,
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(14),
          borderSide: const BorderSide(color: AppColors.line),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(14),
          borderSide: const BorderSide(color: AppColors.line),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(14),
          borderSide: const BorderSide(color: AppColors.terracotta, width: 1.5),
        ),
        errorBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(14),
          borderSide: const BorderSide(color: AppColors.error),
        ),
        focusedErrorBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(14),
          borderSide: const BorderSide(color: AppColors.error, width: 1.5),
        ),
        labelStyle: const TextStyle(color: AppColors.mutedInk),
        hintStyle: const TextStyle(color: Color(0xFF9A9C96)),
      ),
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          backgroundColor: AppColors.terracotta,
          foregroundColor: AppColors.white,
          disabledBackgroundColor: AppColors.terracotta.withValues(alpha: 0.6),
          disabledForegroundColor: AppColors.white,
          minimumSize: const Size.fromHeight(56),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(15),
          ),
          textStyle: const TextStyle(
            fontSize: 15,
            fontWeight: FontWeight.w700,
            letterSpacing: 0.1,
          ),
        ),
      ),
      textSelectionTheme: const TextSelectionThemeData(
        cursorColor: AppColors.terracotta,
        selectionHandleColor: AppColors.terracotta,
      ),
    );
  }
}
