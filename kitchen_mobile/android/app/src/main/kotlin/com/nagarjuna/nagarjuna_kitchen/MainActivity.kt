package com.nagarjuna.nagarjuna_kitchen

import android.app.NotificationChannel
import android.app.NotificationManager
import android.os.Build
import io.flutter.embedding.android.FlutterActivity

class MainActivity : FlutterActivity() {
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
