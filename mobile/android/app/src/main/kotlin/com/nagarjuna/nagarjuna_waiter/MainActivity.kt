package com.nagarjuna.nagarjuna_waiter

import android.app.NotificationChannel
import android.app.NotificationManager
import android.os.Build
import io.flutter.embedding.android.FlutterActivity

class MainActivity : FlutterActivity() {
    override fun onCreate(savedInstanceState: android.os.Bundle?) {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            val channel = NotificationChannel(
                "kot_updates",
                "Order and kitchen updates",
                NotificationManager.IMPORTANCE_HIGH,
            ).apply {
                description = "Kitchen ticket and order status notifications"
            }
            getSystemService(NotificationManager::class.java)
                .createNotificationChannel(channel)
        }
        super.onCreate(savedInstanceState)
    }
}
