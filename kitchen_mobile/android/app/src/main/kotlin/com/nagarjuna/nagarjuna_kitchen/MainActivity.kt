package com.nagarjuna.nagarjuna_kitchen

import android.app.NotificationChannel
import android.app.NotificationManager
import android.os.Build
import android.os.Handler
import android.os.Looper
import android.media.AudioManager
import android.media.ToneGenerator
import io.flutter.embedding.android.FlutterActivity
import io.flutter.embedding.engine.FlutterEngine
import io.flutter.plugin.common.MethodChannel

class MainActivity : FlutterActivity() {
    override fun configureFlutterEngine(flutterEngine: FlutterEngine) {
        super.configureFlutterEngine(flutterEngine)
        MethodChannel(flutterEngine.dartExecutor.binaryMessenger, "com.nagarjuna.kitchen/alerts")
            .setMethodCallHandler { call, result ->
                if (call.method != "playNewKotSound") {
                    result.notImplemented()
                    return@setMethodCallHandler
                }
                try {
                    val tone = ToneGenerator(AudioManager.STREAM_NOTIFICATION, 90)
                    val started = tone.startTone(ToneGenerator.TONE_PROP_BEEP2, 350)
                    Handler(Looper.getMainLooper()).postDelayed({ tone.release() }, 500)
                    result.success(started)
                } catch (_: RuntimeException) {
                    result.success(false)
                }
            }
    }

    override fun onCreate(savedInstanceState: android.os.Bundle?) {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            val channel = NotificationChannel(
                "kot_updates",
                "Kitchen ticket updates",
                NotificationManager.IMPORTANCE_HIGH,
            ).apply {
                description = "New and cancelled kitchen ticket notifications"
            }
            getSystemService(NotificationManager::class.java)
                .createNotificationChannel(channel)
        }
        super.onCreate(savedInstanceState)
    }
}
