# Antigravity Prompt for Phase 1

Paste this into Antigravity while the Nagarjuna Laravel workspace is open:

```text
Implement Phase 1 only for the Nagarjuna waiter Android app. Read docs/mobile-app-plan.md and docs/master.md first, then inspect the workspace before changing files.

Scope:
- Create a standalone Flutter project under mobile/; do not move, replace, or rewrite the Laravel project.
- Build the Flutter app shell and a polished, production-minded sign-in screen for registered waiter credentials.
- Use Flutter Material 3 and a clear restaurant-operations visual style: warm off-white surfaces, dark readable text, and a restrained Nagarjuna-inspired terracotta accent. No logo or photography is supplied, so do not invent brand assets.
- Organize code so authentication can later connect through a repository/API layer; keep UI widgets separate from transport and session logic.
- Include email and password inputs, password visibility, inline validation, keyboard-safe scrolling, loading and error states, accessible labels, and touch targets suitable for a busy restaurant floor.
- Do not add a fake successful login, sample users, hard-coded credentials, or production URLs. The Laravel project currently has browser-session login but no mobile token endpoint. If the user submits the form before a real auth API exists, show a clear non-technical “Sign-in service is not connected yet” message.
- Do not edit Laravel files or add dependencies unless they are required to create the Flutter app and their use is explained.
- Do not add menu/order screens in this phase.

After implementation:
1. Run flutter analyze and an Android debug build if the installed toolchain permits. Do not run or create automated tests.
2. Fix all analyzer/build issues caused by your changes.
3. Report changed files, commands and results, any Flutter/Android SDK blocker, and confirm that no real sign-in is claimed until the API exists.
4. Do not claim the project is bug-free or production-ready based on a mock sign-in screen.
```
