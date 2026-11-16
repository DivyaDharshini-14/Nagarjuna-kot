# Kitchen mobile UI acceptance

Updated 13 November 2026. Use this manual walkthrough before approving a kitchen app release, and after changing typography, input themes, stock cards, dialogs, or board cards. The project uses manual acceptance rather than automated tests, as recorded in [master.md](master.md#development-rules).

Static analysis and a successful APK build confirm source and build validity. They do not establish that keyboard interaction, text scaling, or device layouts pass this walkthrough. Record each device result below; unrecorded cases remain pending.

## Device and text matrix

Use a staging branch with a kitchen-manager account and a stock-view-only account. Include the Android model and keyboard from the reported screenshots where available. Install the candidate APK, then verify these logical screen sizes with both portrait and landscape orientation:

| Width / viewport | Text setting | Required coverage |
| --- | --- | --- |
| 320 logical pixels wide | Default, approximately 150%, and largest supported / approximately 200% | Prepared stock, every stock dialog, sign-in, board, paging |
| 360 logical pixels wide | Default and largest supported | Prepared stock and every stock dialog with keyboard visible |
| 400 logical pixels wide | Default and largest supported | Prepared stock, board, long names and balances |
| Short landscape viewport, approximately 640 × 360 | Default and largest supported | Scroll access to fields, history paging, Retry, Back and Save / Close |
| Actual service phone | Its configured font and display size; repeat with largest settings | Full walkthrough using the installed keyboard |

Adjust Android font and display size separately. A wide physical screenshot can still represent a narrow logical layout because of display scaling. Include system status/navigation bars and the normal keyboard toolbar in the available space.

For every row, fail acceptance if a control is unreachable, text overlaps another control, a dialog extends behind the keyboard without a scroll path, or debug logs show a `RenderFlex` / `InputDecorator` overflow. A release build hides overflow stripes, so inspect a debug build as well as the candidate release build. Verify that full availability choices are readable when their menu is open.

## Staging data

Use or create staging menu data containing:

- The reported dish, `Andhra Style Chicken`, with four tracked portions and `Temporarily unavailable` selected.
- Available and Sold out dishes, one dish without stock tracking, and one dish without an opening quantity.
- A long dish name such as `Andhra Style Chicken Biriyani with special family portions`, multiple long kitchen names, and a large legal prepared-stock quantity.
- Enough menu dishes and stock movements for at least two pages.
- A stock history entry with a long reason and staff name, a timestamp, and large before/after balances.
- Board tickets with long KOT/order/table references, multiple dishes, long notes, cancellation quantities and every displayed status.

Use staging data for adjustments and cancellations. Keep the candidate production API, signing, and account checks in the existing [production readiness procedure](mobile-production-readiness.md).

## Prepared stock

1. Open Prepared stock. Read each dish name, kitchen name, quantity and availability. Confirm the quantity does not squeeze the name outside the card and the Availability label/history button remain usable.
2. Check Available, Temporarily unavailable and Sold out cards. Open each selector, read all three choices, select a different value and provide a reason. After success and refresh, confirm the server-approved status is shown.
3. Choose a different status, then cancel with Back. Repeat with Android back. The original status must remain selected and no stock availability change should appear in history.
4. Try a reason shorter than three characters, then correct it. Confirm validation is readable and Save succeeds only with a valid reason. Repeat with a long reason approaching the 500-character limit.
5. While the reason dialog is open, show the keyboard, rotate, and move focus within the reason. Confirm its counter, validation message and Save / Back actions can be reached by scrolling. Entered text should survive reflow.
6. Simulate a failed availability request. Confirm the error is readable, the card retains the persisted status, and retry remains possible.
7. Confirm the disabled-stock message wraps inside its card, stock adjustments are unavailable without tracking, and a view-only account cannot change stock or availability.
8. Navigate to the next and previous stock pages; refresh the current page. Confirm page text wraps and arrows stay accessible at large text sizes.

## Stock movement dialogs

Run the following checks for **Stock in**, **Wastage**, **Damaged**, and **Correction**. For Correction, repeat with both Add portions and Remove portions.

1. Open the dialog for the long dish name. Enter `5` as the portion quantity, leaving the numeric keyboard visible as in the reported screenshot.
2. Focus Reason and switch to the text keyboard. Enter several lines, then a reason near 500 characters. Its counter and bottom border must not overlap the next control or the dialog actions.
3. At each device/text setting, scroll from the title to quantity, correction selector when applicable, reason, and the actions. The focused field and Save movement / Back must remain reachable above the keyboard.
4. Try empty input, zero quantity, and a one-character reason. Confirm readable validation and no movement. Correct the values and save; verify exactly one movement with the intended type, quantity, reason and correction direction in history.
5. Reopen the dialog, enter text and cancel. Confirm no movement and unchanged balance. Rotate while another dialog is open and verify entered values remain intact.
6. Attempt a quantity larger than the balance for a decrease on staging. Confirm the server error is readable, the displayed balance remains correct, and controls become usable again.

## Stock history

1. Open history for the long-name dish. Read the full movement reason, staff name, timestamp, quantity change and before/after balances. They must wrap without competing for a fixed trailing area.
2. Scroll through the entries, navigate both directions, and close the dialog. At large text size in landscape, the title, paging controls and Close must remain reachable through scrolling.
3. Check empty history, a loading history request, and a failed history request. Empty/error messages and Retry must fit; Retry must load the requested page. Close must work in all states.

## Nearby kitchen screens

1. On sign-in, show the keyboard in portrait and landscape. Reach both credentials and the Sign in button by scrolling. Check server validation and long error text at largest font/display settings.
2. On the kitchen board, read long branch/staff names, kitchen filters, summary counts, ticket references, status labels, dish names, notes and cancellation details. Confirm cards grow vertically and buttons remain usable.
3. Switch between Active, Completed and cancellation history. Check empty/error states and multi-page history. Verify all page arrows, refresh, sound and stock controls remain accessible.
4. Use the Retry connection screen in landscape and at the largest text size. Confirm both retry and sign-in actions can be reached.

## Release record

| Field | Result |
| --- | --- |
| App version / build number | Local debug verification: 1.0.0+1; production candidate pending |
| Candidate APK or bundle identifier / checksum | Local debug APK: `kitchen_mobile/build/app/outputs/flutter-apk/app-debug.apk`; SHA256 `30791C9D36E0C82449390514015344E7F965D86E0DFC2E0BF8159E755C1EC802`. Production candidate pending. |
| Static analysis / build result | 13 November 2026: `flutter analyze --no-pub` passed with no issues. `flutter build apk --debug --no-pub --dart-define=API_BASE_URL=http://10.0.2.2:8000/api/v1` passed. Changed-source `git diff --check` passed. This APK uses the emulator development API, not a production URL. |
| Device model, Android version and keyboard | Pending |
| Font size, display size and orientations checked | Pending |
| Prepared stock / availability / movements | Pending |
| History / pagination / empty and failure states | Pending |
| Sign-in / board / retry screens | Pending |
| Screenshot or issue references for failures | Pending |
| Manual tester and acceptance date | Pending |

Approve the UI gate only after the supported service devices pass the matrix and any failed cases are corrected and repeated. Keep this record with the matching release artifact; a future theme or screen change requires another pass.

Local visual acceptance was not completed: the Windows inspection helper timed out, and the connected emulator's captured screen was blank while another application was foregrounded. No stock changes were submitted during verification. The device rows above intentionally remain pending.
