DateTime restaurantDayStart(DateTime now) {
  const offset = Duration(hours: 5, minutes: 30);
  final local = now.toUtc().add(offset);
  return DateTime.utc(local.year, local.month, local.day).subtract(offset);
}

bool isFromPreviousRestaurantDay(DateTime? activityAt) =>
    activityAt != null &&
    activityAt.isBefore(restaurantDayStart(DateTime.now()));
