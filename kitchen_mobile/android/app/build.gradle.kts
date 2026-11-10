import java.io.FileInputStream
import java.net.URI
import java.util.Properties
import java.util.Base64

val encodedDartDefines = project.findProperty("dart-defines") as? String
val configuredApiBaseUrl = encodedDartDefines
    ?.split(",")
    ?.mapNotNull { encoded ->
        runCatching {
            String(Base64.getDecoder().decode(encoded), Charsets.UTF_8)
        }.getOrNull()
    }
    ?.firstOrNull { it.startsWith("API_BASE_URL=") }
    ?.substringAfter("=")
    ?.trim()

val releaseKeyPropertiesFile = rootProject.file("key.properties")
val releaseKeyProperties = Properties()
if (releaseKeyPropertiesFile.exists()) {
    FileInputStream(releaseKeyPropertiesFile).use { input ->
        releaseKeyProperties.load(input)
    }
}
val releaseBuildRequested = gradle.startParameter.taskNames.any {
    it.contains("release", ignoreCase = true)
}
if (releaseBuildRequested) {
    if (!releaseKeyPropertiesFile.isFile) {
        throw GradleException(
            "Release builds require kitchen_mobile/android/key.properties and an owner-managed upload keystore.",
        )
    }

    val requiredSigningProperties = listOf("keyAlias", "keyPassword", "storeFile", "storePassword")
    val missingSigningProperties = requiredSigningProperties.filter {
        releaseKeyProperties.getProperty(it).isNullOrBlank()
    }
    if (missingSigningProperties.isNotEmpty()) {
        throw GradleException(
            "Release signing configuration is missing: ${missingSigningProperties.joinToString()}",
        )
    }

    val keystoreFile = file(releaseKeyProperties.getProperty("storeFile").trim())
    if (!keystoreFile.isFile) {
        throw GradleException("Release keystore file does not exist: ${keystoreFile.path}")
    }

    val apiUri = configuredApiBaseUrl?.let { runCatching { URI(it) }.getOrNull() }
    val apiHost = apiUri?.host?.lowercase()
    val localOrPlaceholderHost = apiHost == null || apiHost in setOf(
        "localhost",
        "127.0.0.1",
        "10.0.2.2",
        "your_api_host",
    ) || apiHost.endsWith(".example")
    if (
        apiUri == null
        || apiUri.scheme != "https"
        || apiUri.path?.trimEnd('/') != "/api/v1"
        || apiUri.rawQuery != null
        || apiUri.rawFragment != null
        || apiUri.rawUserInfo != null
        || localOrPlaceholderHost
    ) {
        throw GradleException(
            "Release builds require a real HTTPS API_BASE_URL ending in /api/v1; local and placeholder hosts are not allowed.",
        )
    }
}

plugins {
    id("com.android.application")
    // The Flutter Gradle Plugin must be applied after the Android and Kotlin Gradle plugins.
    id("dev.flutter.flutter-gradle-plugin")
}

android {
    namespace = "com.nagarjuna.nagarjuna_kitchen"
    compileSdk = flutter.compileSdkVersion
    ndkVersion = flutter.ndkVersion

    compileOptions {
        sourceCompatibility = JavaVersion.VERSION_17
        targetCompatibility = JavaVersion.VERSION_17
    }

    defaultConfig {
        applicationId = "com.nagarjuna.nagarjuna_kitchen"
        minSdk = maxOf(flutter.minSdkVersion, 23)
        targetSdk = flutter.targetSdkVersion
        // Uses the version code from pubspec.yaml. When using split APKs, 1000 * ABI_VERSION
        // is added automatically by Flutter. (https://developer.android.com/studio/build/configure-apk-splits#configure-APK-versions)
        // You can force using the value of versionCode by specifying the `-P force-version-code-ignoring-abi=true`
        // flag during build.
        versionCode = flutter.versionCode
        versionName = flutter.versionName
    }

    if (releaseKeyPropertiesFile.exists()) {
        signingConfigs {
            create("release") {
                keyAlias = releaseKeyProperties["keyAlias"] as String
                keyPassword = releaseKeyProperties["keyPassword"] as String
                storeFile = file(releaseKeyProperties["storeFile"] as String)
                storePassword = releaseKeyProperties["storePassword"] as String
            }
        }
    }

    buildTypes {
        release {
            if (releaseKeyPropertiesFile.exists()) {
                signingConfig = signingConfigs.getByName("release")
            }
        }
    }
}

kotlin {
    compilerOptions {
        jvmTarget = org.jetbrains.kotlin.gradle.dsl.JvmTarget.JVM_17
    }
}

flutter {
    source = "../.."
}
