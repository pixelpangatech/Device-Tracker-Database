# TechVault - UI/UX Design System

## 1. Design Philosophy
TechVault employs a **Dark Glassmorphism** design language. The goal is to provide a premium, futuristic, and distraction-free environment for system administrators. The interface focuses on depth, translucency, and vibrant accent colors to guide the user's attention.

## 2. Color Palette
The application relies on CSS variables defined in `:root` for consistency across all pages.

### Backgrounds
- **Global Background:** Radial gradient from `#0f172a` (Deep Slate) to `#020617` (True Night).
- **Glass Cards:** `rgba(15, 23, 42, 0.6)` with a 12px backdrop blur.
- **Glass Inputs:** `rgba(255, 255, 255, 0.05)` for a subtle inset depth.

### Accents & Typography
- **Primary Accent (Cyan):** `#06b6d4`
- **Secondary Accent (Emerald):** `#10b981`
- **Warning/Alert (Orange):** `#f59e0b`
- **Primary Text:** `#f8fafc` (Off-white for reduced eye strain)
- **Muted Text:** `#94a3b8`

## 3. Core Components

### The "Glass Card" (`.glass-card`)
The fundamental building block of TechVault's UI. 
- Utilizes `backdrop-filter: blur(12px)` to allow the gradient background to bleed through softly.
- Features a subtle `1px solid rgba(255, 255, 255, 0.1)` border to define edges without harsh lines.
- Includes a hover transition (`box-shadow` elevation) to encourage interaction.

### Forms & Inputs (`.form-control-custom`, `.glass-input`)
Inputs are designed to look embedded within the glass cards. They have no harsh borders in their default state, only revealing an accent-colored border and a soft glow on `:focus`.

### Dynamic Stat Pills
Used on the Live Dashboard to differentiate device statuses instantly:
- **Allocated (Orange Glow):** Indicates devices currently in use.
- **Free (Emerald Glow):** Indicates devices ready for deployment.

## 4. Typography
- **Primary Font:** *Plus Jakarta Sans*
- Chosen for its geometric precision, high legibility at small sizes, and modern corporate feel. Weights 400 (Regular), 600 (Semi-bold), and 700 (Bold) are used to establish visual hierarchy.

## 5. Responsive Behavior
- The sidebar transforms into a bottom navigation bar or a collapsed drawer on mobile devices (`max-width: 992px`).
- The dashboard stats grid uses CSS Flexbox/Grid to seamlessly wrap cards from a 4-column layout down to a single column on smartphones.
