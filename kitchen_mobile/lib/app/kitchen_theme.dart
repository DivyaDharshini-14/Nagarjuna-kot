import 'package:flutter/material.dart';

abstract final class KitchenColors {
  static const canvas = Color(0xFFF4F2ED);
  static const card = Color(0xFFFFFFFF);
  static const ink = Color(0xFF202522);
  static const muted = Color(0xFF747A74);
  static const line = Color(0xFFE3E1DA);
  static const green = Color(0xFF246B50);
  static const greenDark = Color(0xFF1C543E);
  static const orange = Color(0xFFC65336);
  static const amber = Color(0xFFE9A434);
  static const red = Color(0xFFB93731);
}

abstract final class KitchenTheme {
  static ThemeData get light {
    final scheme = ColorScheme.fromSeed(
      seedColor: KitchenColors.green,
      brightness: Brightness.light,
      surface: KitchenColors.canvas,
    );
    return ThemeData(
      useMaterial3: true,
      colorScheme: scheme.copyWith(
        primary: KitchenColors.green,
        onPrimary: Colors.white,
        surface: KitchenColors.canvas,
        onSurface: KitchenColors.ink,
      ),
      scaffoldBackgroundColor: KitchenColors.canvas,
      appBarTheme: const AppBarTheme(
        backgroundColor: KitchenColors.canvas,
        foregroundColor: KitchenColors.ink,
        elevation: 0,
        centerTitle: false,
      ),
      textTheme: const TextTheme(
        headlineMedium: TextStyle(
          color: KitchenColors.ink,
          fontSize: 28,
          fontWeight: FontWeight.w800,
          height: 1.1,
          letterSpacing: -0.7,
        ),
        titleLarge: TextStyle(
          color: KitchenColors.ink,
          fontSize: 20,
          fontWeight: FontWeight.w800,
          letterSpacing: -0.3,
        ),
        titleMedium: TextStyle(
          color: KitchenColors.ink,
          fontSize: 16,
          fontWeight: FontWeight.w700,
        ),
        bodyMedium: TextStyle(
          color: KitchenColors.muted,
          fontSize: 14,
          height: 1.4,
        ),
      ),
      cardTheme: CardThemeData(
        color: KitchenColors.card,
        elevation: 0,
        margin: EdgeInsets.zero,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(20),
          side: const BorderSide(color: KitchenColors.line),
        ),
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: KitchenColors.card,
        contentPadding: const EdgeInsets.symmetric(
          horizontal: 16,
          vertical: 17,
        ),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(14),
          borderSide: const BorderSide(color: KitchenColors.line),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(14),
          borderSide: const BorderSide(color: KitchenColors.line),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(14),
          borderSide: const BorderSide(color: KitchenColors.green, width: 1.5),
        ),
      ),
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          backgroundColor: KitchenColors.green,
          foregroundColor: Colors.white,
          minimumSize: const Size.fromHeight(52),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(14),
          ),
          textStyle: const TextStyle(fontWeight: FontWeight.w800),
        ),
      ),
    );
  }
}
