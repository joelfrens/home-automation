# Touch screens

The app is used on computers, phones and a touch screen on the Raspberry Pi 5. Every page must work
with a finger.

## Why screen width isn't enough

A Pi touch screen (for example the 7" Raspberry Pi Touch Display 2 in landscape, 1280×720) is
*wide*, so the browser shows the desktop layout: small inputs, small buttons. Width breakpoints
(`sm:`, `md:`) can't tell a touch screen from a laptop, so we detect touch instead with the CSS media
feature `any-pointer: coarse` (true when any touch input exists, even if a mouse is plugged in too).

## What's already done for you

[`web/resources/css/app.css`](../web/resources/css/app.css) has an `@media (any-pointer: coarse)`
block that, on touch screens only:

- makes inputs, selects and buttons (the shared `Input`, `Button`, native `<select>`) at least
  **44px** tall, and icon-only buttons (like ✕) 44px wide
- makes checkboxes 24px
- turns off double-tap zoom and the tap delay (`touch-action: manipulation`)
- stops long-press menus and text selection on buttons and links

On a computer with a mouse, nothing changes. Use the shared components in
`web/resources/js/components/ui/` and you get this for free.

## Rules for new pages

1. **Use the shared components** (`Button`, `Input`, `Checkbox`, …). For a native `<select>`, copy the
   classes from `selectClass` in `components/school/event-fields.tsx`.
2. **Nothing only on hover.** Hover may highlight, but every action needs a visible button or a tap
   target. No right-click menus, no tooltips that hold information.
3. **Big enough targets.** 44×44px minimum for anything you tap, with some space between targets.
   Whole cards can be links (see `EventCard`).
4. **Touch-only styles** with Tailwind's `any-pointer-coarse:` variant, for example
   `any-pointer-coarse:hidden` on the "paste with ⌘V" hint.
5. **Typing:** set `inputMode` (`numeric`, `email`, `search`) and `enterKeyHint` (`next`, `done`,
   `send`) so phone keyboards show the right keys. Input text is 16px on small screens so iPhones
   don't zoom in.
6. **Files:** a tap must open the picker (`<input type="file">` inside a `<label>`); drag and drop and
   paste are extras. Reuse `FileDrop` (`components/school/file-drop.tsx`).
7. **Dates and times:** use native `type="date"` / `type="time"` inputs; phones and Chromium show
   touch-friendly pickers.
8. **Keyboard space:** on the Pi the on-screen keyboard covers the bottom of the screen. Put the
   main action near the field, or use a sticky bar (see the review page).

## Testing

In Chrome DevTools, open the device toolbar (⌘⇧M), choose touch emulation and try:

- **1280×720** (Pi screen, landscape): desktop layout, but 44px targets
- **390×844** (phone)

Tap everything: buttons, ✕s, checkboxes, dropdowns, date pickers, file upload. Then on a real phone,
and later on the Pi itself.

## The Pi kiosk (for the deployment)

- **Screen:** the official Raspberry Pi Touch Display 2 (ribbon cable, multi-touch) or any HDMI
  monitor with USB touch. Raspberry Pi OS passes touch straight to Chromium. Rotate it in
  *Screen Configuration*.
- **Chromium in kiosk mode**, roughly:
  ```bash
  chromium-browser --kiosk --ozone-platform=wayland --enable-wayland-ime \
    --disable-pinch --overscroll-history-navigation=0 http://localhost:8000
  ```
  `--enable-wayland-ime` lets the on-screen keyboard type into Chromium; `--disable-pinch` and
  `--overscroll-history-navigation=0` stop accidental zoom and swipe-back. Add
  `--force-device-scale-factor=1.25` if everything looks small.
- **On-screen keyboard:** Raspberry Pi OS's own (*Raspberry Pi Configuration → Display → On-screen
  keyboard*). Check it appears when a field is tapped and doesn't hide it. Only if it misbehaves:
  an in-app keyboard (`react-simple-keyboard`) shown on the kiosk only.
- **Login:** tick "Remember me" so the kiosk stays logged in. Set screen blanking in Raspberry Pi
  Configuration.
